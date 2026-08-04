<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Order;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

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

            foreach ($files as $file) {
                $filePath = $fileUplService->filesystem(
                    $file,
                    $order->intellicareLog->reference_number,
                    'checkout-prescriptions'
                );

                $prescriptions[] = $order->prescriptions()->create([
                    'location' => config('app.env'),
                    'file_path' => $filePath['file_path'],
                    'file_name' => $filePath['file_name'],
                    'account_number' => $order->intellicareLog->account_no,
                    'reference_number' => $order->intellicareLog->reference_number,
                ]);
            }

            return $this->sendResponse($prescriptions, 'Successfully uploaded your prescriptions');
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong.', $e->getMessage());
        }
    }
}
