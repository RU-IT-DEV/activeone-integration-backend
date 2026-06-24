<?php

namespace App\Jobs;

use App\Exceptions\RowValidationException;
use App\Http\Controllers\Api\FileSystemController;
use App\Mail\BulkUploadErrorMail;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Helper\ClaimsHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use App\Models\Members;
use App\Models\AuditLogs;
use Throwable;
use Validator;
use Exception;

class MemberBulkUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath, $company, $userId, $userEmail, $existingEmails, $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $company, $userId, $userEmail, $jobId = null)
    {
        $this->filePath = $filePath;
        $this->company = $company;
        $this->userId = $userId;
        $this->userEmail = $userEmail;
        $this->jobId = $jobId;
        $this->existingEmails = Members::pluck('email')->flip();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::reconnect();
        logger()->info("MemberBulkUploadJob started.");
        $file_system = new FileSystemController();
        $object = $file_system->getGSObject($this->filePath);

        if (!$object) {
            logger()->error('File not found in GCS', ['filePath' => $this->filePath]);
            return;
        }

        // Create a temp stream
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $object->downloadAsString());
        rewind($stream);
        $header = null;

        $errorRows = [];
        $batch = [];
        $batchSize = 200;
        $rowNumber = 1;
        $batchResult = [];

        while (($row = fgetcsv($stream)) !== false) {
            if ($rowNumber === 1) {
                $header = $row;
                $rowNumber++;
                continue;
            }

            $raw = array_combine($header, $row);

            try {
                $mapped = $this->mapRowData($raw, $rowNumber);
                $batch[] = $mapped;
            } catch (RowValidationException $e) {
                $errorRows[] = [
                    'row_number' => $e->rowNumber,
                    'errors' => json_encode($e->errors),
                    'rawRow' => $raw
                ];
            }

            if (count($batch) === $batchSize) {
                logger()->info("MemberBulkUploadJob processing batch.", ['batch' => $batch]);
                $batchResult = $this->uploadBulkBatch($batch);
                foreach ($batchResult['errors'] as $error) {
                    $errorRows[] = [
                        'row_number' => $error['row']['_row_number'] ?? null,
                        'errors' => $error['error'],
                        'rawRow' => $error['row'],
                    ];
                }
                $batch = [];
            }

            $rowNumber++;
        }

        if (!empty($batch)) {
            $batchResult = $this->uploadBulkBatch($batch);

            foreach ($batchResult['errors'] as $error) {
                $errorRows[] = [
                    'row_number' => $error['row']['_row_number'] ?? null,
                    'errors' => $error['error'],
                    'rawRow' => $error['row'],
                ];
            }
        }

        fclose($stream);

        logger()->info("MemberBulkUploadJob completed with errors.", ['errorCount' => count($errorRows)]);
        $localPath = $this->generateErrorCsv($errorRows);
        $gcsPath = 'bulk-errors/' . basename($localPath);

        $file_system->uploadToGCS($localPath, $gcsPath);

        $publicUrl = $file_system->getGSObject($gcsPath)
            ->signedUrl(
                Carbon::now()->addYears(5)
            );

        // Mail::to($this->userEmail)->send(
        //     new BulkUploadErrorMail(
        //         $publicUrl,
        //         $errorRows
        //     )
        // );
    }

    private function uploadBulkBatch(array $rows): array
    {
        $result = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $arr_member) {
                try {
                    $this->processSingleMember($arr_member, $index);
                    $result['success']++;
                } catch (Throwable $e) {
                    // Row-level failure
                    $result['failed']++;
                    $result['errors'][] = [
                        'row' => $arr_member,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw new RowValidationException(
                ['error' => $e->getMessage()],
                count($rows),
                [ $rows[0], $rows[count($rows) - 1] ]
            );
        }

        return $result;
    }

    private function mapRowData(array $input, int $rowNumber): array
    {
        $cleanInput = [];

        foreach ($input as $key => $value) {
            $cleanKey = trim($key);
            $cleanKey = preg_replace('/^\xEF\xBB\xBF/', '', $cleanKey); // remove UTF‑8 BOM
            $cleanInput[$cleanKey] = $value;
        }

        $input = $cleanInput;

        if (empty($input['ID'])) {
            throw new RowValidationException(
                ['ID' => ['ID is required']],
                $rowNumber,
                $input
            );
        }

        $mapped = [
            'flexicare_id' => $input['ID'],
            'first_name'   => $input['First Name'] ?? null,
            'last_name'    => $input['Last Name'] ?? null,
            'email'        => $input['Email Address'] ?? null,
            'date_hired'   => $input['Entry Date'] ?? null,
            'benefit' => ClaimsHelper::mapMemberBenefits($input),
            'action' => strtolower($input['Add/Remove/Rehire'] ?? 'add'),
            'deactivation_date' => $input['Leaving Date'] == "" ? null : $input['Leaving Date'],
            'salary_grade' => $input['JG'] ?? null,
            'division' => $input['Division'] ?? null,
        ];

        if (!empty($input['Bank Name'])) {
            $mapped['bank_details'] = [
                'bank_name' => $input['Bank Name'],
                'account_number' => $input['Bank Account Number'] ?? null,
                'account_name' => $input['Bank Account Name'] ?? null,
            ];
        }

        if (
            $mapped['action'] == 'add' &&
            isset($this->existingEmails[$mapped['email']])
        ) {
            throw new RowValidationException(
                ['email' => ['Email already exists']],
                $rowNumber,
                $input
            );
        }

        $validator = Validator::make($mapped, [
            'flexicare_id' => ['required'],
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => [
                'required',
                'email',
                Rule::when(
                    $mapped['action'] == ['add'],
                    Rule::unique('members', 'email')
                ),
            ],
            'date_hired' => 'required|date',
            'deactivation_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new RowValidationException(
                $validator->errors()->toArray(),
                $rowNumber,
                $input
            );
        }

        return $mapped;
    }

    private function processSingleMember(array $arr_member, int $row_number): void
    {
        $arr_new_member = [
            'company_code' => $this->company,
            'status' => 'active',
            'flexicare_id' => $arr_member['flexicare_id'],
            'first_name' => $arr_member['first_name'],
            'last_name' => $arr_member['last_name'],
            'email' => $arr_member['email'],
            'date_hired' => $arr_member['date_hired'],
            'date_endorsed' => now(),
            'enrollment_date' => $arr_member['date_hired'],
            'deactivation_date' => empty($arr_member['deactivation_date']) ? null:$arr_member['deactivation_date'],
            'division' => $arr_member['division'] ?? null,
            'salary_grade' => $arr_member['salary_grade'] ?? null,
        ];

        match ($arr_member['action']) {
            'add'     => $this->handleAdd($arr_new_member, $arr_member, $row_number),
            'remove'  => $this->handleRemove($arr_member),
            'rehire'  => $this->handleRehire($arr_member),
            'update'  => $this->handleUpdate($arr_new_member, $arr_member),
            default   => throw new \RuntimeException('Invalid action'),
        };
    }

    private function handleAdd(array $new_member, array $arr_member, int $row_number): void
    {
        try {
            $new_member = Members::create($new_member);
            if (isset($arr_member['bank_details'])) {
                $new_member->bankDetails()->create($arr_member['bank_details']);
            }

            if (isset($arr_member['benefit'])) {
                // create benefits
                $new_member->setBenefitsFromBU($arr_member['benefit']);
            }

            $hasBuckets = $new_member->planLink()
                ->whereHas('planActiveBuckets')
                ->exists();

            if (!$hasBuckets) {
                throw new \RuntimeException('Bucket is not created');
            }
    
        } catch (Throwable $e) {
            logger()->error('Error creating member', [
                'error' => $e->getMessage(),
                'member_data' => $new_member,
            ]);
            throw new RowValidationException(
                ['error' => $e->getMessage()],
                $row_number,
                [$new_member]
            );
        }
    }

    private function handleRemove(array $arr_member): void
    {
        $obj_member = Members::where('email', $arr_member['email'])
            ->where('status', 'active')
            ->first();

        $deactivationDate = Carbon::parse($arr_member['deactivation_date']);
        $setInactive = $deactivationDate->isPast() ? 'inactive' : 'active';

        if ($obj_member) {
            $obj_member->update([
                'status' => $setInactive,
                'deactivation_date' => $arr_member['deactivation_date']
            ]);

            if ($setInactive == 'inactive') {
                $obj_member->deactivateBenefitFromBU_updateTag($arr_member['benefit']);
            }
        }
    }

    private function handleRehire(array $arr_member) : void
    {
        $obj_member = Members::where('email', $arr_member['email'])
            ->where('status', 'inactive')
            ->first();

        $employmentHistory = [
            'hire_date' => $obj_member->date_hired, 
            'leave_date' => $obj_member->deactivation_date, 
            'salary_grade' => $obj_member->salary_grade,
            'remarks' => "Updated from MemberBulkUpload.handleRehire"
        ];

        try {
            $obj_member->employmentHistory()->create($employmentHistory);
            $obj_member->update([
                'status' => 'active',
                'date_hired' => $arr_member['date_hired'],
                'deactivation_date' => null,
            ]);
    
            // create benefits
            $obj_member->setBenefitsFromBUForRehired($arr_member['benefit']);

            $hasBuckets = $obj_member->planLink()
                ->whereHas('planActiveBuckets')
                ->exists();

            if (!$hasBuckets) {
                throw new \RuntimeException('Bucket is not created');
            }
        } catch (Throwable $e) {
            logger()->error('Error creating member', [
                'error' => $e->getMessage(),
                'member_data' => $arr_member,
            ]);
            throw new RowValidationException(
                ['error' => 'Error rehiring member'],
                $e->getMessage(),
                $arr_member
            );
        }
    }

    private function handleUpdate(array $new_member, array $arr_member): void
    {
        $obj_member = Members::where('flexicare_id', $arr_member['flexicare_id'])
            ->where('email', $arr_member['email'])
            ->where('status', 'active')
            ->first();

        $obj_member->update($new_member);

        $obj_member->deactivateBenefitFromBU_updateTag($arr_member['benefit']);
        // create benefits
        $obj_member->setBenefitsFromBU($arr_member['benefit']);
    }

    private function generateErrorCsv(array $errorRows): string
    {

        $basePath = sys_get_temp_dir() . '/bulk_upload_errors';

        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        $filename = "bulk-upload-errors-{$this->jobId}.csv";
        $path = "{$basePath}/{$filename}";

        $headers = [
            'row_number',
            'error_messages',
            'raw_data'
        ];

        $fp = fopen($path, 'w');
        fputcsv($fp, $headers);

        foreach ($errorRows as $row) {
            fputcsv($fp, [
                $row['row_number'],
                json_encode($row['errors'], JSON_UNESCAPED_UNICODE),
                json_encode($row['rawRow'], JSON_UNESCAPED_UNICODE),
            ]);
        }

        fclose($fp);

        return $path;
    }
}
