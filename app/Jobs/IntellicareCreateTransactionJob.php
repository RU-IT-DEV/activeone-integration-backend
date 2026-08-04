<?php

namespace App\Jobs;

use App\Helper\IntellicareHelper;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use App\Services\CustomCrypt;

class IntellicareCreateTransactionJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $transaction, $orderModel, $intellicareHelper;

    protected $custom_crypt;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $orderId)
    {

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::findOrFail($this->orderId);

        $this->intellicareHelper = new IntellicareHelper;
        $this->custom_crypt = new CustomCrypt;
        $this->orderModel = $order;
        $this->transaction = $this->intellicareHelper->transformTransactionData($order->intellicareLog);

        $request = [
            'Value' => $this->custom_crypt->encrypt(json_encode($this->transaction))
        ];

        logger()->info("Intellicare Create Transaction start: ", $this->transaction);

        try {
            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->intellicareHelper->access_key
            ])->post(config('services.intellicare.url') . '/transaction/create', $request);
            $response = $this->intellicareHelper->clientResponse($client->json());
            logger()->info("IntellicareJob: Transaction is created. Response: ", $response['data']);

            if ($client->failed()) {
                $resp_status = $response['status'];
                $this->orderModel->intellicare_status = "TRXN_ERROR";
                $this->orderModel->save();
                throw new \Exception($resp_status['message']);
            } else {
                $this->orderModel->intellicare_status = "VERIFYING";
                $this->orderModel->save();
                $this->orderModel->intellicareLog->reference_number = $response['data']['approval_code'];
                $this->orderModel->intellicareLog->loa_date = $response['data']['loa_date'];
                $this->orderModel->intellicareLog->save();
            }
        } catch (\Exception $e) {
            \Log::error('Intellicare createTransaction failed: ' . $e->getMessage());
            throw new \Exception('Intellicare createTransaction failed: ' . $e->getMessage(), 400);
        }
    }
}
