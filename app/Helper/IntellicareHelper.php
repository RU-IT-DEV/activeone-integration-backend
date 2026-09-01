<?php

namespace App\Helper;

use App\Models\OrderIntellicareLog;
use App\Models\ShopifyIntegrationAuth;
use Illuminate\Support\Facades\Crypt;
use App\Services\CustomCrypt;
use Illuminate\Support\Facades\Http;

class IntellicareHelper
{
    public $access_key;
    protected $custom_crypt;

    public function __construct()
    {
        $this->custom_crypt = new CustomCrypt();
        $botika_auth = ShopifyIntegrationAuth::where('shop_client_id', 'Intellicare:Botika API')->first();
        if (empty($botika_auth)) {
            $this->authenticate();
        } else if ($botika_auth->expires_at < now()) {
            $botika_auth->delete();
            $this->authenticate();
        } else {
            $this->access_key = $botika_auth->access_token;
        }
    }

    private function authenticate()
    {
        $data = json_encode([
            'Username' => config('services.intellicare.username'),
            'Password' => config('services.intellicare.password')
        ]);

        $encrypted_request = $this->custom_crypt->encrypt($data);
        
        $request = [
            'Value' => $encrypted_request
        ];

        try {
            $client = Http::post(config('services.intellicare.url') . '/auth', $request);

            if ($client->serverError()) {
                $client = Http::post(config('services.intellicare.backup_url') . '/auth', $request);
            }

            $response = $client->json();
            if ($client->failed()) {

                $resp_status = $this->custom_crypt->decrypt($response['status']);
                throw new \Exception($resp_status['message']);
            } else {
                $response = $this->clientResponse($client->json());
        
                if ($response['status']['success'] === FALSE) {
                    throw new \Exception($response['status']['message']);
                }

                logger()->info("Botika Access Token Created Successfully.", $response['data']);
                $this->access_key = $response['data']['access_token'];
                ShopifyIntegrationAuth::updateOrCreate([
                    'access_token' => $this->access_key,
                    'expires_at' => now()->addSeconds(intval($response['data']['expires_in']))
                ], [
                    'shop_client_id' => 'Intellicare:Botika API'
                ]);

                return $this;
            }
        } catch (\Exception $e) {
            // Handle authentication error
            \Log::error('Intellicare authentication failed: ' . $e->getMessage());
            throw new \Exception('Intellicare authentication failed: ' . $e->getMessage());
            
        }
    }

    /**
     * Summary of clientResponse
     * @param mixed $response
     * @throws \Exception
     * @return array{data: mixed, status: mixed}
     */
    public function clientResponse ($response)
    {
        $str_resp_status = $this->custom_crypt->decrypt($response['status']);
        $arr_resp_status = json_decode($str_resp_status, true);

        if (isset($arr_resp_status['success'])) {
            if ($arr_resp_status['success'] === FALSE) {
                throw new \Exception($arr_resp_status['message']);
            }
        } else if (isset($arr_resp_status['Success'])) {
            if ($arr_resp_status['Success'] === FALSE) {
                throw new \Exception($arr_resp_status['Message']);
            }
        }

        $str_resp_data = "";
        $arr_resp_data = [];
        if (is_array($response['data'])) {
            foreach ($response['data'] as $key => $response_data) {
                $str_resp_data = $this->custom_crypt->decrypt($response_data);
                $arr_resp_data[] = json_decode($str_resp_data, true);
            }
        } else {
            $str_resp_data = $this->custom_crypt->decrypt($response['data']);
            $arr_resp_data = json_decode($str_resp_data, true);
        }

        return [
            'status' => $arr_resp_status,
            'data' => $arr_resp_data
        ];
    }

    public function validateMember($data)
    {
        $request = [
            'Value' => $this->custom_crypt->encrypt(json_encode($data))
        ];

        try {
            $httprequest = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->access_key
            ]);

            $client = $httprequest->post(config('services.intellicare.url') . '/auth', $request);

            if ($client->serverError()) {
                $client = $httprequest->post(config('services.intellicare.backup_url') . '/auth', $request);
            }
            $response = $this->clientResponse($client->json());

            if ($client->failed()) {
                $resp_status = $response['status'];
                throw new \Exception($resp_status['message']);
            } else {
                logger()->info("Validated member: ", $response['data']);
                
                return $response['data'];
            }
            
        } catch (\Exception $e) {
            \Log::error('Intellicare member validation failed: ' . $e->getMessage());
            throw new \Exception('Intellicare member validation failed: ' . $e->getMessage(), 400);
        }
    }

    public function getDoctors($reqData)
    {
        try {
            $httprequest = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->access_key,
                'page' => 1,
                'size' => 1,
                'prccode' => $reqData['prccode']
            ]);

            $client = $httprequest->get(config('services.intellicare.url') . '/doctor/doctors');

            if ($client->serverError()) {
                $client = $httprequest->get(config('services.intellicare.backup_url') . '/doctor/doctors');
            }

            $response = $this->clientResponse($client->json());
            if ($client->failed()) {
                throw new \Exception("Error Processing Request", 1);
            } else {
                logger()->info("Validated member: ", $response['data']);
                
                return $response['data'];
            }
        } catch (\Exception $e) {
            \Log::error('Intellicare search doctor PRC failed: ' . $e->getMessage());
            throw new \Exception('Intellicare search doctor PRC failed: ' . $e->getMessage(), 400);
        }
    }

    private function diagnosis ($arr_diagnosis)
    {
        $data = [];
        if (!is_null($arr_diagnosis)) {
            foreach ($arr_diagnosis as $key => $code) {
                $data[] = [
                    'code' => $code,
                    'name' => "",
                    'primary' => $key == 0,
                ];
            }
        }
        return $data;
    }

    public function transformTransactionData(OrderIntellicareLog $intellicareLog): array
    {
        return [
            'account_no' => $intellicareLog->account_no,
            'contract' => (int) $intellicareLog->contract,
            'first_name' => $intellicareLog->first_name,
            'last_name' => $intellicareLog->last_name,
            'branch' => $intellicareLog->branch,
            'birth_date' => $intellicareLog->birth_date,
            'receipt_number' => $intellicareLog->receipt_number,
            'prccode' => $intellicareLog->prccode,
            'diagnosis' => $this->diagnosis($intellicareLog->diagnosis),
            'medicines' => $intellicareLog->medicines->map(function ($item) {
                return [
                    'code' => $item->code,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'gross' => 1,
                    'gross_wo_vat' => 0.8,
                    'vat_amount' => 0.2,
                    'type' => $item->type,
                    'with_prescription' => (bool) $item->is_prescribed  
                ];
            })->values()->all(),
        ];
    }
}
