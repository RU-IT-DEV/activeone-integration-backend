<?php

namespace App\Jobs;

use App\Helper\IntellicareHelper;
use App\Models\Order;
use App\Services\FileUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use App\Services\CustomCrypt;
use Illuminate\Support\Facades\DB;

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
        $order = Order::with([
            'intellicareLog.medicines',
            'intellicareLog.prescriptions'
        ])->findOrFail($this->orderId);

        $this->intellicareHelper = new IntellicareHelper;
        $this->custom_crypt = new CustomCrypt;
        $this->orderModel = $order;
        $this->transaction = $this->intellicareHelper->transformTransactionData($order->intellicareLog);

        $request = [
            'Value' => $this->custom_crypt->encrypt(json_encode($this->transaction))
        ];

        logger()->info("Intellicare Create Transaction start: ", $this->transaction);

        try {
            $client = Http::withToken(
                $this->intellicareHelper->access_key
            )->post(config('services.intellicare.url') . '/transaction/create', $request);
            $response = $this->intellicareHelper->clientResponse($client->json());
            logger()->info("IntellicareJob: Transaction is created. Response: ", $response['data']);

            if ($client->failed()) {
                $resp_status = $response['status'];
                throw new \Exception($resp_status['message']);
            } else {
                $this->orderModel->intellicare_status = "VERIFYING";
                $this->orderModel->save();
                $this->orderModel->intellicareLog->reference_number = $response['data']['approval_code'];
                $this->orderModel->intellicareLog->loa_date = $response['data']['loa_date'];
                $this->orderModel->intellicareLog->save();

                $this->uploadPrescriptions();
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === "Duplicate receipt number.") {
                logger()->info("Trying to reupload order {$this->orderModel->id} prescription.");
                $this->uploadPrescriptions();
            } else {
                $this->orderModel->intellicare_status = "TRXN_ERROR";
                $this->orderModel->save();
                \Log::error('Intellicare createTransaction failed: ' . $e->getMessage());
                throw new \Exception('Intellicare createTransaction failed: ' . $e->getMessage(), 400);
            }
        }
    }

    private function uploadPrescriptions ()
    {
        $fileUplService = new FileUploadService();

        $intellicareLog = $this->orderModel->intellicareLog;
        $intellicareLog->load([
            'prescriptions'
        ]);

        try {
            $request = Http::withToken($this->intellicareHelper->access_key)
                ->attach('acctno', $this->custom_crypt->encrypt($intellicareLog->account_no))
                ->attach('reference_no', $this->custom_crypt->encrypt($intellicareLog->reference_number));

            $streams = [];

            foreach ($intellicareLog->prescriptions as $prescription) {
                $prescription->reference_number = $intellicareLog->reference_number;
                $prescription->save();

                $stream = $fileUplService->getStream($prescription->file_path);

                if ($stream === false) {
                    throw new \Exception("Unable to read {$prescription->file_path}");
                }

                $streams[] = $stream;

                $newFileName = $intellicareLog->reference_number . $prescription->file_name;

                $request = $request->attach(
                    'prescription_file',
                    $stream,
                    $newFileName
                );
            }

            $client = $request->post(config('services.intellicare.url') . '/prescription/upload');
            $response = $this->intellicareHelper->clientResponse($client->json());
            $this->orderModel->intellicare_status = "SUCCESS";
            $this->orderModel->save();
            // logger()->info("Response from upload prescription: ", $client->json());

            // Always close the streams
            foreach ($streams as $stream) {
                fclose($stream);
            }

            logger()->info("Intellicare Prescription Upload response: ", $response['data']);
        } catch (\Exception $e) {
            $this->orderModel->intellicare_status = "TRXN_PRX_ERROR";
            $this->orderModel->save();
            logger()->error('Intellicare uploadPrescription failed: ' . $e->getMessage());
            throw new \Exception('Intellicare uploadPrescription failed: ' . $e->getMessage(), 400);
        }
    }
}
