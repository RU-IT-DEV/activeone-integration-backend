<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Jobs\ShopifyCreateOrderJob;
use App\Http\Controllers\Api\BaseController;
use App\Models\Order;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

logger()->info([
    'Client' => class_exists(\Google\Cloud\SecretManager\V1\Client::class),
    'SecretManagerServiceClient' => class_exists(\Google\Cloud\SecretManager\V1\SecretManagerServiceClient::class),
]);

class OrderPrescriptionController extends BaseController
{
    public function store(Request $request, Order $order, FileUploadService $fileUplService)
    {
        $files = $request->file('attachments', []);

        $this->validate($request, [
            'attachments' => 'required|array',
            'attachments.*' => 'file'
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
                JobDispatcher::dispatch(new ShopifyCreateOrderJob($order->id));
            });

            return $this->sendResponse($prescriptions, 'Successfully uploaded your prescriptions');
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong.', $e->getMessage());
        }
    }
}
