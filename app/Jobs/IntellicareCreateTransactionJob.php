<?php

namespace App\Jobs;

use App\Helper\IntellicareHelper;
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
    public function __construct($order)
    {
        $this->intellicareHelper = new IntellicareHelper;
        $this->custom_crypt = new CustomCrypt;
        $this->orderModel = $order;
        $this->transaction = $this->intellicareHelper->transformTransactionData($order->intellicareLog);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $request = [
            'Value' => $this->custom_crypt->encrypt(json_encode($this->transaction))
        ];

        logger()->info("Intellicare Create Transaction start: ", $this->transaction);

        try {
            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->intellicareHelper->access_key
            ])->post(config('services.intellicare.url') . '/transaction/create', $request);
            $response = $this->intellicareHelper->clientResponse($client->json());

            if ($client->failed()) {
                $resp_status = $response['status'];
                $this->orderModel->intellicare_status = "TRXN_ERROR";
                $this->orderModel->save();
                throw new \Exception($resp_status['message']);
            } else {
                $this->orderModel->intellicare_status = "VERIFYING";
                $this->orderModel->save();
                logger()->info("Transaction is created: ", $response['data']);
            }
        } catch (\Exception $e) {
            \Log::error('Intellicare member validation failed: ' . $e->getMessage());
            throw new \Exception('Intellicare member validation failed: ' . $e->getMessage(), 400);
        }
    }
}
