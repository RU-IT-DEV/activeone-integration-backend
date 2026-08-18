<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Helper\ShopifyHelper;
use App\Jobs\ShopifyCreateOrderJob;
use App\Http\Controllers\Api\BaseController;
use App\Models\Order;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderPrescriptionController extends BaseController
{
    public function store(Request $request, Order $order, FileUploadService $fileUplService)
    {
        $files = $request->file('attachments', []);

        $this->validate($request, [
            'attachments' => 'required|array',
            'attachments.*' => 'file|mimes:jpeg,png,pdf,jpg'
        ]);

        try {
            $prescriptions = [];

            DB::beginTransaction();
            foreach ($files as $file) {
                $filePath = $fileUplService->filesystem(
                    $file,
                    "order{$order->intellicareLog->order_id}",
                    'checkout-prescriptions'
                );

                $prescriptions[] = $order->prescriptions()->create([
                    'location' => config('app.env'),
                    'file_path' => $filePath['file_path'],
                    'file_name' => $filePath['file_name'],
                    'account_number' => $order->intellicareLog->account_no
                ]);
            }
            DB::commit();

            // Runs ONLY if the outer transaction succeeds completely
            DB::afterCommit(function () use ($order) {
                $shopifyHelper = new ShopifyHelper();
                $shopifyHelper
                    ->transformOrderData($order)
                    ->orderCreate($order)
                    ->clearCart($order);
            });
                
            $order->refresh();
            $response = [
                ...$prescriptions,
                'order_url' => $order->order_url,
            ];

            return $this->sendResponse($response, 'Successfully uploaded your prescriptions');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Something went wrong.');
        }
    }
}
