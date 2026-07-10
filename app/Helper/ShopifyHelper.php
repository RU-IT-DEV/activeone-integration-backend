<?php

namespace App\Helper;

use App\Models\ShopifyIntegrationAuth;
use Illuminate\Support\Facades\Http;

class ShopifyHelper
{
    private $x_access_token;

    private $customer_input_constructed;


    public function __construct()
    {
        $shopify = ShopifyIntegrationAuth::where('shop_client_id', config('services.shopify.client_id'))->first();

        if (!$shopify) {
            $this->createAccessToken();
        } else if ($shopify->expires_at < now()) {
            $shopify->delete();
            $this->createAccessToken();
        } else {
            $this->x_access_token = $shopify->access_token;
        }
    }

    public function createAccessToken()
    {
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.shopify.client_id'),
            'client_secret' => config('services.shopify.access_token')
        ];

        $client = Http::asForm()->post(config('services.shopify.url') . '/oauth/access_token', $data);

        if ($client->failed()) {
            logger()->error("Shopify Access Token Error.");
            throw new \Exception("Error Processing Request", 1);
        } else {
            $response = $client->json();
            logger()->info("Shopify Access Token Created Successfully.", $response);

            ShopifyIntegrationAuth::create([
                'shop_client_id' => config('services.shopify.client_id'),
                'access_token' => $response['access_token'],
                'expires_at' => now()->addSeconds($response['expires_in'])
            ]);
            $this->x_access_token = $response['access_token'];
        }
    }

    public function processCreateCustomerInput($data)
    {
        $this->customer_input_constructed = [
            'email' => $data['email_address'],
            'firstName' => $data['first_name'],
            'lastName' => $data['last_name'],
            'metafields' => [
                [
                    'namespace' => 'custom',
                    'key' => 'account_number',
                    'value' => $data['account_no'],
                    'type' => 'single_line_text_field'
                ],
                [
                    'namespace' => 'custom',
                    'key' => 'company',
                    'value' => $data['company'],
                    'type' => 'single_line_text_field'
                ],
                [
                    'namespace' => 'custom',
                    'key' => 'status',
                    'value' => 'active',
                    'type' => 'single_line_text_field'
                ],
                [
                    'namespace' => 'custom',
                    'key' => 'coverage_end',
                    'value' => $data['end_date'],
                    'type' => 'single_line_text_field'
                ]
            ]
        ];

        return $this;
    }

    public function createUser()
    {
        $apiUrl = config('services.shopify.url');
        $query = 'mutation customerCreate($input: CustomerInput!) { customerCreate(input: $input) { userErrors { field message } customer { id email taxExempt firstName lastName metafields(first: 5) { edges { node { namespace key  value } } } } } }';

        $client = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'input' => $this->customer_input_constructed
            ]
        ]);

        if ($client->failed()) {
            throw new \Exception("Error in creating Shopify customer.", 1);
        } else {
            $response = $client->json();
            if (array_key_exists('errors', $response)) {
                throw new \Exception("Error in creating Shopify customer.", 1);
            } else {
                if (isset($response['data']['customerCreate']['userErrors'])) {
                    $errs = $response['data']['customerCreate']['userErrors'];

                    $messages = collect($errs)
                        ->pluck('message')
                        ->implode(', ');
                    throw new \Exception($messages, 1);
                }
            }

            return $response;
        }
    }
}
