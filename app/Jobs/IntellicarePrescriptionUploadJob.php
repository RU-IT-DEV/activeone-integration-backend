<?php

namespace App\Jobs;

use App\Helper\IntellicareHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use App\Services\CustomCrypt;

class IntellicarePrescriptionUploadJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $prescription, $orderModel, $intellicareHelper;

    protected $custom_crypt;

    /**
     * Create a new job instance.
     */
    public function __construct($order, $request)
    {
        $this->intellicareHelper = new IntellicareHelper;
        $this->custom_crypt = new CustomCrypt;
        $this->orderModel = $order;
        $this->prescription = $request;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $client = Http::asForm()->withHeaders([
                'Authorization' => 'Bearer ' . $this->intellicareHelper->access_key
            ])->post(config('services.intellicare.url') . '/prescription/upload', $this->prescription);
            $response = $this->intellicareHelper->clientResponse($client->json());
        } catch (\Exception $e) {
            
        }
    }
}
