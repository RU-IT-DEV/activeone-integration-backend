<?php

namespace App\Http\Controllers\Api\Orders;

use App\Http\Controllers\Api\BaseController;
use App\Mail\DailyReportMail;
use App\Models\DailyReportEvents;
use App\Models\User;
use App\Models\Order;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DailyReportController extends BaseController
{
    /**
     * Store
     * 
     * Create the JSON Report File.
     * Creating the file for tomorrows report. Setting up from 17:00 upto 20:30 today.
     * @param Request $request
     * @param FileUploadService $fileUploadService
     */
    public function store(Request $request, FileUploadService $fileUploadService)
    {
        $now = Carbon::now('Asia/Manila')->format('Y-m-d');
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
                    'email'
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
            'status' => 'success',
            'mailable' => false,
            'is_mail_sent' => false,
        ];

        DailyReportEvents::create($new_daily_report);

        foreach ($orders as $order) {
            $order->jsonUpdate()->create([
                'update_type' => 'create',
                'started_at' => Carbon::now('Asia/Manila'),
                'status' => 'success',
            ]);
        }

        return response()->noContent();
    }

    /**
     * Create the CSV File
     * 
     * @param Request $request
     * @param FileUploadService $fileUploadService
     */
    public function createCSVReport(Request $request, FileUploadService $fileUploadService)
    {
        $request->input('report_name');

        return response()->noContent();
    }
    

    /**
     * Summary of eightThirtyAmReport
     * 
     * This method is for sending the CSV Report
     * @param Request $request
     * @param FileUploadService $fileUploadService
     */
    public function eightThirtyAmReport(Request $request, FileUploadService $fileUploadService)
    {
        $validated = $request->validate([
            'report_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reportDate = $validated['report_date'];
        $filename = 'orders-0830.csv';
        $objectPath = "daily-orders/{$reportDate}/{$filename}";

        $recipients = User::dailyReportReceivers();

        if ($recipients === []) {
            logger()->warning('Daily order report has no eligible recipients', [
                'report_date' => $reportDate,
                'slot' => '0830',
            ]);

            return response()->noContent();
        }

        $object = $fileUploadService->getGSObject($objectPath);
        $csv = $object->downloadAsString();

        Mail::to($recipients)
            ->send(new DailyReportMail($reportDate, "8:30 AM", $filename, $csv));

        return response()->noContent();
    }

    /**
     * Summary of onePmReport
     * 
     * This method is for sending the CSV Report
     * @param Request $request
     * @param FileUploadService $fileUploadService
     */
    public function onePmReport(Request $request, FileUploadService $fileUploadService)
    {
        // daily-orders/YYYY-MM-DD/orders-1300.csv
        $validated = $request->validate([
            'report_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reportDate = $validated['report_date'];
        $filename = 'orders-1300.csv';
        $objectPath = "daily-orders/{$reportDate}/{$filename}";

        $recipients = User::dailyReportReceivers();

        if ($recipients === []) {
            logger()->warning('Daily order report has no eligible recipients', [
                'report_date' => $reportDate,
                'slot' => '1300',
            ]);

            return response()->noContent();
        }

        $object = $fileUploadService->getGSObject($objectPath);
        $csv = $object->downloadAsString();

        Mail::to($recipients)
            ->send(new DailyReportMail($reportDate, "1:00 PM", $filename, $csv));

        return response()->noContent();
    }

    /**
     * Summary of fivePmReport
     * 
     * This method is for sending CSV Report
     * @param Request $request
     * @param FileUploadService $fileUploadService
     */
    public function fivePmReport(Request $request, FileUploadService $fileUploadService)
    {
        // daily-orders/YYYY-MM-DD/orders-1700.csv
        $validated = $request->validate([
            'report_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reportDate = $validated['report_date'];
        $filename = 'orders-1700.csv';
        $objectPath = "daily-orders/{$reportDate}/{$filename}";

        $recipients = User::dailyReportReceivers();

        if ($recipients === []) {
            logger()->warning('Daily order report has no eligible recipients', [
                'report_date' => $reportDate,
                'slot' => '1700',
            ]);

            return response()->noContent();
        }

        $object = $fileUploadService->getGSObject($objectPath);
        $csv = $object->downloadAsString();

        Mail::to($recipients)
            ->send(new DailyReportMail($reportDate, "5:00 PM", $filename, $csv));

        return response()->noContent();
    }
        
}
