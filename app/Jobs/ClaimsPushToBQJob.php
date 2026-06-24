<?php

namespace App\Jobs;

use App\Http\Controllers\Api\FileSystemController;
use App\Models\AuditLogs;
use App\Models\ClaimsResponse;
use App\Models\MemberClaims;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BQClaimsUpload;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\DB;

class ClaimsPushToBQJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $claimsResponseId, $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $claimsResponseId, int $userId)
    {
        $this->claimsResponseId = $claimsResponseId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::reconnect();
        $claimsResponse = ClaimsResponse::find($this->claimsResponseId);

        if (! $claimsResponse) {
            logger()->warning('ClaimsResponse not found', [
                'id' => $this->claimsResponseId,
            ]);
            return;
        }

        // ✅ Store temporarily (never commit this)
        $tempPath = storage_path('bq-key.json');
        
        $bigQ_config = config('bigq.connection');
        $file_path = $bigQ_config['key_file_path'];
        $bigQ_datasetConfig = $bigQ_config[config('app.env')];
        
        if (!$tempPath) {
            $gcsConfig = config('filesystems.disks.gcs');
            $storage = new StorageClient([
                'keyFilePath' => $gcsConfig['key_file_path'],
            ]);
            $bucket = $storage->bucket($gcsConfig['bucket']);
            $object = $bucket->object($file_path);

            $object->downloadToFile($tempPath);
            
            if (! $object->exists()) {
                throw new \Exception("Service account file not found in GCS");
            }
        }

        try {
            
            $bigQuery = new BigQueryClient([
                'projectId' => $bigQ_config['project_id'],
                'keyFilePath' => $tempPath,
            ]);

            // END of mapping and inserting claim data for BigQuery
            $arr_forUpload = BQClaimsUpload::where('is_pushed', 0)
                ->where('processing_at', null)
                ->limit(500)->get();
            // Process each item for upload
            foreach ($arr_forUpload as $item) {
                $claim = MemberClaims::find($item->member_claim_id);
    
                $item->processing_at = now();
                $item->save();
                
                // Insert raw and staging data
                $tables = $bigQ_datasetConfig[$claim->type]['table_name'];

                $raw_data = $item->data;
                unset($raw_data['ACCOUNT']);
                unset($raw_data['NAME_OF_BANK']);
                unset($raw_data['START_BALANCE']);
                unset($raw_data['ACCOUNT_NAME']);

                $raw_result = $bigQuery->dataset($bigQ_datasetConfig[$claim->type]['dataset'])
                    ->table($tables['raw'])
                    ->insertRow($raw_data);
                
                $staging_data = $item->data;
                if ($claim->type == 'fsa') {
                    $staging_data['RECEIVED_DATE'] = Carbon::parse($staging_data['RECEIVED_DATE'])->format('Y-m-d');
                    $staging_data['PROCESSED_DATE'] = Carbon::parse($staging_data['PROCESSED_DATE'])->format('Y-m-d');
                }
                
                $stag_result = $bigQuery->dataset($bigQ_datasetConfig[$claim->type]['dataset'])
                    ->table($tables['staging'])
                    ->insertRow($staging_data);

                // Check if the insert was successful
                if ($raw_result->isSuccessful() && $stag_result->isSuccessful()) {
                    $item->is_pushed = 1;
                    $item->pushed_at = now();
                } else {
                    AuditLogs::create([
                        'user_id' => $item->user_id,
                        'event' => 'pushtobq',
                        'auditable_type' => "BQClaimsUpload",
                        'auditable_id' => $item->id,
                        'summary' => json_encode([
                            'member_claim_id' => $item->member_claim_id,
                            'raw_errors' => $raw_result->failedRows(),
                            'staging_errors' => $stag_result->failedRows(),
                        ]),
                        'severity' => 3,
                        'status' => "fail"
                    ]);
                    $item->processing_at = null; // Reset processing time to allow retry
                }
                $item->save();
            }
        } catch (\Throwable $th) {
            $error_str = "Failed to push claims data to BigQuery: " . $th->getMessage();
            AuditLogs::create([
                'user_id' => $this->userId,
                'event' => 'pushtobq',
                'auditable_type' => "BQClaimsUpload",
                'auditable_id' => 0,
                'summary' => $error_str,
                'severity' => 2,
                'status' => "fail"
            ]);
            throw new \Exception($error_str);
        }

    }
}
