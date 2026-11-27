<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Mail;
use App\Mail\DailyReportMail;
use App\Models\DailyReportEvents;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OrderReportSendMailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $reportDate, public string $timeslot)
    {
        
    }

    /**
     * Execute the job.
     */
    public function handle(FileUploadService $fileUploadService): void
    {
        $reportDate = $this->reportDate;
        $filename = "orders-{$this->timeslot}.csv";
        $objectPath = "daily-orders/{$reportDate}/reports/{$filename}";

        $recipients = User::dailyReportReceivers();

        if ($recipients === []) {
            logger()->warning('Daily order report has no eligible recipients', [
                'report_date' => $reportDate,
                'slot' => $this->timeslot,
            ]);

            return response()->noContent();
        }

        $object = $fileUploadService->getGSObject($objectPath);
        if ($object->exists()) {
            $csv = $object->downloadAsString();
    
            $dailyReport = DailyReportEvents::where('file_path', $objectPath)->first();

            if ($dailyReport === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report record not found.',
                ], 404);
            }

            if ($dailyReport->is_mail_sent) {
                return response()->noContent();
            }

            try {
                if (! $object->exists()) {
                    logger()->info('Report CSV is missing from Cloud Storage.', [
                        'object_path' => $objectPath,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Report CSV is not available yet.',
                    ], 503);
                }

                $csv = $object->downloadAsString();

                $times = [
                    '0830' => '8:30 AM',
                    '1300' => '1:00 PM',
                    '1700' => '5:00 PM'
                ];

                Mail::to($recipients)
                    ->send(new DailyReportMail($reportDate, $times[$this->timeslot], $filename, $csv));

                $dailyReport->status = "sent";
                $dailyReport->is_mail_sent = true;
                $dailyReport->save();

                return response()->noContent();
            } catch (\Exception $e) {
                report($e);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to send the daily report.',
                ], 500);
            }
        } else {
            logger()->info("Object $objectPath does not exists.");
            return response('', 400);
        }
    }
}
