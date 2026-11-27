<?php

namespace App\Http\Controllers\Api\Orders;

use App\Dispatchers\JobDispatcher;
use App\Http\Controllers\Api\BaseController;
use App\Jobs\OrderReportSendMailJob;
use App\Models\DailyReportEvents;
use App\Models\Order;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DailyReportController extends BaseController
{
    /**
     * Store
     * 
     * Create the JSON Report File.
     * Creating the file for tomorrows report. Setting up from 17:00 upto 21:30 today.
     * @param Request $request
     * @param FileUploadService $fileUploadService
     */
    public function store(Request $request, FileUploadService $fileUploadService)
    {
        $now = Carbon::now('Asia/Manila')->format('Y-m-d');
        // $now = "2026-11-18";
        $tomorrow = Carbon::tomorrow('Asia/Manila')->format('Y-m-d');

        $orders = Order::with([
            'lineItems' => function($query) {
                return $query->select([
                    'order_id', 'sku', 'title', 
                    'variantTitle', 'type', 'icd', 'icdcode',
                    'quantity', 'amount', 'vat_amount', 
                    'no_vat_amount', 'reason'
                ])->whereNull('deleted_at');
            }, 'intellicareLog' => function ($query) {
                return $query->select([
                    'order_id', 'reference_number', 'account_no', 'loa_date'
                ]);
            }, 'statusUpdatedBy.user' => function ($query) {
                return $query->select([
                    'id', 'email'
                ]);
            }
        ])->select([
            'id', 'customer_email', 'customer_email_2', 
            'customer_name', 'shopify_order_name', 
            'activeone_status', 'intellicare_status', 'created_at'
        ])->where('created_at', '>=', "$now 17:00")
        ->get()->toArray();

        $objectFileName = $fileUploadService->storeFile($orders, "daily-orders/$tomorrow");

        $new_daily_report = [
            'report_name' => $tomorrow,
            'file_path' => $objectFileName,
            'generated_at' => Carbon::now('Asia/Manila'),
            'status' => 'created for the first time',
            'mailable' => false,
            'is_mail_sent' => false,
        ];

        DailyReportEvents::create($new_daily_report);

        return response()->noContent();
    }

    public function jsonRewrite(Request $request, FileUploadService $fileUploadService) 
    {
        $validated = $request->validate([
            'timeslot' => ['nullable', 'string', 'regex:/\A(?:[01][0-9]|2[0-3])[0-5][0-9]\z/'],
        ]);

        $time = $validated['timeslot'] ?? Carbon::now('Asia/Manila')->format('Hi');
        $day = Carbon::now('Asia/Manila')->startOfDay();
        $today = $day->toDateString();

        $dailyReport = DailyReportEvents::where('report_name', $today)
            ->where('mailable', false)
            ->firstOrFail();

        if (in_array($dailyReport->status, ['rewriting', 'sent'])) {
            return response()->noContent();
        } else {
            $dailyReport->status = "rewriting";
            $dailyReport->save();
        }

        $orders = Order::with([
            'lineItems' => function($query) {
                return $query->select([
                    'order_id', 'sku', 'title', 
                    'variantTitle', 'type', 'icd', 'icdcode',
                    'quantity', 'amount', 'vat_amount', 
                    'no_vat_amount', 'reason'
                ])->whereNull('deleted_at');
            }, 'intellicareLog' => function ($query) {
                return $query->select([
                    'order_id', 'reference_number', 'account_no', 'loa_date'
                ]);
            }, 'statusUpdatedBy.user' => function ($query) {
                return $query->select([
                    'id', 'email'
                ]);
            }
        ])->select([
            'id', 'customer_email', 'customer_email_2', 
            'customer_name', 'shopify_order_name', 
            'activeone_status', 'intellicare_status', 'created_at'
        ]);
        
        $cutoff = $day->copy()->setTime(17, 0);

        if ((int) $time <= 1700) {
            $start = $day;
            $end = $cutoff;
        } else {
            $start = $cutoff;
            $end = $day->copy()->addDay();
        }

        // This assumes orders.created_at is stored and queried in UTC.
        $rows = $orders
            ->where('created_at', '>=', $start->copy()->utc()->toDateTimeString())
            ->where('created_at', '<', $end->copy()->utc()->toDateTimeString())
            ->orderBy('id')
            ->get()
            ->toArray();

        try {
            $fileUploadService->storeCSV(
                json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $dailyReport->file_path,
                "application/json"
            );

            $dailyReport->status = "updated @ $today $time";
            $dailyReport->save();
    
            logger()->info("Daily report JSON updated @ $today $time.", [
                'order_count' => count($rows),
            ]);
    
            if (!in_array($time, ["0830", "1300", "1700"])) {
                return response()->json([
                    'message' => 'Daily report JSON updated.',
                    'order_count' => count($rows),
                ]);
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $dailyReport->status = "failed @ $today $time";
            $dailyReport->save();

            if (!in_array($time, ["0830", "1300", "1700"])) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'data' => $dailyReport
                ], 400);
            } else {
                return false;
            }
        }
    }
    

    /**
     * Create the CSV File
     * 
     * @param Request $request
     * @param FileUploadService $fileUploadService
     */
    public function createCSVReport(Request $request, FileUploadService $fileUploadService)
    {
        $today = Carbon::now()->format('Y-m-d');
        // $today = "2026-11-27";
        $time = $request->input('timeslot');
        $dailyReport = DailyReportEvents::where('report_name', $today)
            ->where('mailable', false)
            ->first();

        if (!$dailyReport) {
            throw new \Exception("No daily report from database $today");
        }

        $filename = "orders-$time.csv";
        $objectPath = "daily-orders/{$today}/reports/$filename";

        $csvDailyReport = DailyReportEvents::where('file_path', $objectPath)
            ->first();

        DailyReportEvents::where('report_name', $today)
            ->where('mailable', true)
            ->where('is_mail_sent', false)
            ->where('id', '<', $csvDailyReport->id)
            ->update([
                'status' => 'overdue'
            ]);
        
        if ($csvDailyReport->status == 'overdue') {
            return response()->noContent();
        }

        if (!$csvDailyReport) {
            $csvDailyReport = DailyReportEvents::create([
                'report_name' => $today,
                'file_path' => $objectPath,
                'status' => 'processing',
                'mailable' => true,
                'is_mail_sent' => false,
            ]);
        }

        if (!$this->jsonRewrite($request, $fileUploadService)) {
            throw new \Exception("Json rewrite failed.", 400);
        }

        $dailyReport->status = "started compiling for csv generation";
        $dailyReport->save();

        $json_file = $fileUploadService->getGSObject($dailyReport->file_path);
        $decoded = json_decode(
            $json_file->downloadAsString(), true, 512, JSON_THROW_ON_ERROR
        );

        $orders = is_array($decoded) ? $decoded : ($decoded->orders ?? null);

        if (!is_array($orders)) {
            throw new \Exception('Expected a JSON array of orders.');
        }

        if (empty($orders)) {
            return response()->json([
                "message" => "Empty report."
            ], 200);
        }

        // extract json
        // Collect all fields so optional fields on later orders are included.

        // transform to csv
        $csv = fopen('php://temp', 'w+b');

        if ($csv === false) {
            throw new \Exception('Unable to open the temporary CSV stream.');
        }

        if ($csvDailyReport->status == 'failed') {
            $time = Carbon::now()->format('Hi');
        }

        try {
            $headerWritten = false;

            foreach ($this->flattenOrderRows($orders) as $row) {
                if (! $headerWritten) {
                    fputcsv($csv, array_keys($row), ',', '"', '');
                    $headerWritten = true;
                }

                fputcsv($csv, array_values($row), ',', '"', '');
            }

            rewind($csv);

            try {
                if ($fileUploadService->storeCSV($csv, $objectPath)) {
                    $csvDailyReport->generated_at = Carbon::now('Asia/Manila');
                    $csvDailyReport->status = 'success';
                    $csvDailyReport->save();
                }
            } catch (\Exception $exception) {
                $csvDailyReport->status = 'failed';
                // The first successful upload owns this date and time slot.
                if ((int) $exception->getCode() !== 412) {
                    throw $exception;
                }
            }
        } finally {
            if (is_resource($csv)) {
                fclose($csv);
            }
        }

        $dailyReport->status = "ready for mail sending @ $today $time";
        $dailyReport->save();

        JobDispatcher::dispatch(
            new OrderReportSendMailJob($today, $time)
        );

        return response()->noContent();
    }

    private function flattenOrderRows(iterable $orders): \Generator
    {
        foreach ($orders as $order) {
            $orderFields = [
                'order_id' => $order['id'],
                'shopify_order_name' => $order['shopify_order_name'] ?? '',
                'customer_name' => $order['customer_name'] ?? '',
                'customer_email' => $order['customer_email'] ?? '',
                'customer_email_2' => $order['customer_email_2'] ?? '',
                'activeone_status' => $order['activeone_status'] ?? '',
                'intellicare_status' => $order['intellicare_status'] ?? '',
                'created_at' => $order['created_at'] ?? '',
                'intellicare_reference_number' => data_get($order, 'intellicare_log.reference_number') ?? '',
                'intellicare_account_no' => data_get($order, 'intellicare_log.account_no') ?? '',
                'intellicare_loa_date' => data_get($order, 'intellicare_log.loa_date') ?? '',
                'status_updated_by_email' => data_get($order, 'status_updated_by.user.email') ?? '',
            ];

            // Keep orders with no line items visible in the report.
            foreach (($order['line_items'] ?? []) ?: [[]] as $item) {
                yield array_merge($orderFields, [
                    'sku' => $item['sku'] ?? '',
                    'title' => $item['title'] ?? '',
                    'variant_title' => $item['variantTitle'] ?? '',
                    'type' => $item['type'] ?? '',
                    'icd' => $item['icd'] ?? '',
                    'icd_code' => $item['icdcode'] ?? '',
                    'quantity' => $item['quantity'] ?? '',
                    'amount' => $item['amount'] ?? '',
                    'vat_amount' => $item['vat_amount'] ?? '',
                    'no_vat_amount' => $item['no_vat_amount'] ?? '',
                    'reason' => $item['reason'] ?? '',
                ]);
            }
        }
    }
        
}
