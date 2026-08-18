<?php

namespace App\Helper;

use App\Dispatchers\JobDispatcher;
use App\Jobs\IntellicareCreateTransactionJob;
use App\Models\Order;
use App\Models\ShopifyIntegrationAuth;
use Illuminate\Support\Facades\Http;

class ShopifyHelper
{
    public $x_access_token, $x_storefront_response, $apiUrl;

    private $customer_input_constructed, $customerAddress_input_constructed;

    private $c_order, $r_order;

    private $shopifyCustomer;


    public function __construct()
    {
        $this->apiUrl = config('services.shopify.url');
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

        $client = Http::asForm()->post(config('services.shopify.url') . '/admin/oauth/access_token', $data);

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
                    'key' => 'birth_date',
                    'value' => $data['birth_date'],
                    'type' => 'date'
                ],
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
                ],
                [
                    'namespace' => 'custom',
                    'key' => 'contract',
                    'value' => $data['contract'],
                    'type' => 'single_line_text_field'
                ]
            ]
        ];

        $this->customerAddress_input_constructed = [
            'countryCode' => $data['address']['country'],
            'provinceCode' => $data['address']['province'],
            'city' => $data['address']['city'],
            'address2' => $data['address']['address2'],
            'address1' => $data['address']['address1'],
        ];

        return $this;
    }

    public function createUser()
    {
        $apiUrl = $this->apiUrl;
        $query = 'mutation customerCreate($input: CustomerInput!) { customerCreate(input: $input) { userErrors { field message } customer { id email taxExempt firstName lastName metafields(first: 5) { edges { node { namespace key  value } } } } } }';

        $client = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'input' => $this->customer_input_constructed
            ]
        ]);

        if ($client->failed()) {
            throw new \Exception("Error in creating Shopify customer.", 1);
        } else {
            $response = $client->json();
            logger()->info("Shopify User is created: ", $response);

            if (array_key_exists('errors', $response)) {
                throw new \Exception("Error in creating Shopify customer.", 1);
            } else {
                $response_customerCreate = $response['data']['customerCreate'];
                if (isset($response_customerCreate['userErrors'])) {
                    if (count($response_customerCreate['userErrors']) > 0) {
                        $errs = $response_customerCreate['userErrors'];
    
                        $messages = collect($errs)
                            ->pluck('message')
                            ->implode(', ');
                        throw new \Exception($messages, 400);
                    } else {
                        $this->shopifyCustomer = $response_customerCreate['customer'];
                    }
                }
            }
        }

        return $this;
    }

    public function createUserAddress()
    {
        $apiUrl = $this->apiUrl;
        $query = 'mutation customerAddressCreate ($address: MailingAddressInput!, $customerId: ID!, $setAsDefault: Boolean) { customerAddressCreate(address: $address, customerId: $customerId, setAsDefault: $setAsDefault) { address { countryCode provinceCode city address2 address1 } userErrors { field message } } }';

        $client = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'address' => $this->customerAddress_input_constructed,
                'customerId' => $this->shopifyCustomer['id'],
                'setAsDefault' => true
            ]
        ]);

        if ($client->failed()) {
            throw new \Exception("Error in creating Shopify customer address.", 1);
        } else {
            $response = $client->json();
            logger()->info("An address has been created.", $response);
            if (array_key_exists("errors", $response)) {
                $this->customerDelete();
                throw new \Exception("Error creating customer address.", 422);
            } else {
                $this->shopifyCustomer['address'] = $response['data']['customerAddressCreate'];
            }
        }
        
        return $this;
    }

    public function sendAccountInvite()
    {
        $apiUrl = $this->apiUrl;
        $query = file_get_contents(
            app_path("Helper/GraphQL/Queries/customerSendAccountInviteEmail.graphql")
        );

        $client = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'customerId' => $this->shopifyCustomer['id']
            ]
        ]);

        if ($client->failed()) {
            throw new \Exception("Error in creating Shopify customer address.", 1);
        } else {
            $response = $client->json();
            logger()->info("An address has been created.", $response);
            if (array_key_exists("errors", $response)) {
                throw new \Exception("Error creating customer address.", 422);
            } else {
                $this->shopifyCustomer['id'] = $response['data']['id'];
            }
        }
        
        return $this;
    }

    private function customerDelete()
    {
        $apiUrl = $this->apiUrl;
        $query = 'mutation customerDelete($id: ID!) { customerDelete(input: {id: $id}) { shop { id } userErrors { field message } deletedCustomerId } }';

        $client = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'id' => $this->shopifyCustomer['id'],
            ]
        ]);

        if ($client->failed()) {
            throw new \Exception("Error in creating customer address.", 1);
        } else {
            throw new \Exception("An error occured while creating customer. Please try again later.", 420);
        }

        return $this;
    }

    public function getCart($cartToken)
    {
        $apiUrl = $this->apiUrl;
        $cartId = "gid://shopify/Cart/{$cartToken}";
        $query = file_get_contents(
            app_path("Helper/GraphQL/Queries/GetCart.graphql")
        );

        $accessToken = config('services.shopify.storefront_access_token');
        $client = Http::withHeaders([
            'Content-Type' => "application/json",
            'X-Shopify-Storefront-Access-Token' => $accessToken
        ])->post("$apiUrl/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'cartId' => $cartId
            ]
        ]);

        if ($client->failed()) {
            $response = $client->json();
            $err_message = array_key_exists("errors", $response) ? $response['errors']:"";
            logger()->info($err_message);
            throw new \Exception($err_message, 422);
        } else {
            $response = $client->json();
            if (array_key_exists("errors", $response)) {
                $err_message = $response['errors'][0]['message'];
                throw new \Exception($err_message, 422);
            } else {
                return $response;
            }
        }
    }

    public function getCartLinesOnly($cartId)
    {
        $apiUrl = $this->apiUrl;
        $query = file_get_contents(
            app_path("Helper/GraphQL/Queries/GetCartLinesOnly.graphql")
        );

        $accessToken = config('services.shopify.storefront_access_token');
        $client = Http::withHeaders([
            'Content-Type' => "application/json",
            'X-Shopify-Storefront-Access-Token' => $accessToken
        ])->post("$apiUrl/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'cartId' => $cartId
            ]
        ]);

        if ($client->failed()) {
            $response = $client->json();
            $err_message = array_key_exists("errors", $response) ? $response['errors']:"";
            logger()->info($err_message);
            throw new \Exception($err_message, 422);
        } else {
            $response = $client->json();
            if (array_key_exists("errors", $response)) {
                $err_message = $response['errors'][0]['message'];
                throw new \Exception($err_message, 422);
            } else {
                return $response;
            }
        }
    }

    public function transformOrderData(Order $order)
    {
        $this->c_order = [
            'billingAddress' => $order->billingAddress->only([
                'address1',
                'address2',
                'city',
                'countryCode',
                'provinceCode',
                'zip',
                'firstName',
                'lastName',
                'phone',
            ]),

            'shippingAddress' => $order->shippingAddress->only([
                'address1',
                'address2',
                'city',
                'countryCode',
                'provinceCode',
                'zip',
                'firstName',
                'lastName',
                'phone',
            ]),

            'customer' => [
                'toUpsert' => [
                    'email' => $order->customer_email,
                    'id' => $order->customer_id
                ]
            ],
            'financialStatus' => $order->financialStatus,

            'lineItems' => $order->lineItems->map(function ($item) {
                return [
                    'priceSet' => [
                        'shopMoney' => [
                            'amount' => $item->shopify_product_price,
                            'currencyCode' => 'PHP',
                        ],
                    ],
                    'productId' => $item->shopify_productId,
                    'quantity' => (int) $item->quantity,
                    'sku' => $item->sku,
                    'taxable' => (bool) $item->taxable,
                    'title' => $item->title,
                    'variantTitle' => $item->variantTitle,
                ];
            })->values()->all(),

            'test' => (bool) $order->test,
        ];

        return $this;
    }

    public function getMetaobject($id)
    {
        $apiUrl = $this->apiUrl;
        $query = file_get_contents(
            app_path("Helper/GraphQL/Queries/GetMetaobj.graphql")
        );

        $client = Http::withHeaders([
            'Content-Type' => "application/json",
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'id' => $id
            ]
        ]);

        if ($client->failed()) {
            $response = $client->json();
            $err_message = array_key_exists("errors", $response) ? $response['errors']:"";
            logger()->info($err_message);
            throw new \Exception($err_message[0]['message'], 422);
        } else {
            $response = $client->json();
            if (array_key_exists("errors", $response)) {
                $err_message = $response['errors'][0]['message'];
                throw new \Exception($err_message, 422);
            } else {
                return $response['data']['metaobject'];
            }
        }
    }

    public function getCustomer($id)
    {
        $apiUrl = $this->apiUrl;
        $query = file_get_contents(
            app_path("Helper/GraphQL/Queries/GetCustomerDefaultAddress.graphql")
        );

        $client = Http::withHeaders([
            'Content-Type' => "application/json",
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'customerId' => $id
            ]
        ]);

        if ($client->failed()) {
            $response = $client->json();
            $err_message = array_key_exists("errors", $response) ? $response['errors']:"";
            logger()->info($err_message);
            throw new \Exception($err_message[0]['message'], 422);
        } else {
            $response = $client->json();
            if (array_key_exists("errors", $response)) {
                $err_message = $response['errors'][0]['message'];
                throw new \Exception($err_message, 422);
            } else {
                return $response['data']['customer'];
            }
        }
    }

    public function orderCreate(Order $orderModel)
    {
        $apiUrl = $this->apiUrl;
        $query = file_get_contents(
            app_path('Helper/GraphQL/Mutations/OrderCreate.graphql')
        );

        $client = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'options' => [
                    "inventoryBehaviour" => "BYPASS",
                    "sendFulfillmentReceipt" => true,
                    "sendReceipt" => true
                ],
                'order' => $this->c_order
            ]
        ]);

        $response = $client->json();

        if ($client->failed()) {
            throw new \Exception("Error Processing Shopify Create Order", 1);
        } else {
            logger()->info("Response: ", $response);
            
            if (array_key_exists('errors', $response)) {
                throw new \Exception($response['errors'][0]['message'], 1);
            } else {
                $resp_data = $response['data'];
                $orderCreate = $resp_data['orderCreate'];
                if (count($orderCreate['userErrors']) > 0) {
                    throw new \Exception($resp_data['userErrors'], 1);
                } else {
                    $order = $resp_data['orderCreate']['order'];
                    $orderModel->shopify_order_name = $order['name'];
                    $orderModel->order_url = $order['statusPageUrl'];
                    $orderModel->save();
                    // save as response order
                    $this->r_order = $order;
                    $orderModel->intellicareLog->receipt_number = str_replace("#", "", $order['name']);
                    $orderModel->intellicareLog->save();

                    JobDispatcher::dispatch(
                        new IntellicareCreateTransactionJob($orderModel->id)
                    );
                }
            } 
        }

        return $this;
    }

    public function clearCart(Order $orderModel)
    {
        $cartLines_response = $this->getCartLinesOnly($orderModel->shopify_cart_id);
        $lines = array_map(function ($v) {
            return $v['node']['id'];
        }, $cartLines_response['data']['cart']['lines']['edges']);
        $apiUrl = $this->apiUrl;
        $cartId = $orderModel->shopify_cart_id;
        $query = file_get_contents(
            app_path("Helper/GraphQL/Mutations/cartLinesRemove.graphql")
        );

        $accessToken = config('services.shopify.storefront_access_token');
        $client = Http::withHeaders([
            'Content-Type' => "application/json",
            'X-Shopify-Storefront-Access-Token' => $accessToken
        ])->post("$apiUrl/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'cartId' => $cartId,
                'lineIds' => $lines
            ]
        ]);

        if ($client->failed()) {
            $response = $client->json();
            $err_message = array_key_exists("errors", $response) ? $response['errors']:"";
            logger()->info($err_message);
            throw new \Exception($err_message, 422);
        } else {
            $response = $client->json();
            if (array_key_exists("errors", $response)) {
                $err_message = $response['errors'][0]['message'];
                throw new \Exception($err_message, 422);
            } else {
                logger()->info("ClearCart Response:", $response);
            }
        }

        return $this;
    }

    public function sendInvoice()
    {
        $apiUrl = $this->apiUrl;
        $query = file_get_contents(
            app_path("Helper/GraphQL/Mutations/orderInvoiceSend.graphql")
        );

        $client = Http::withHeaders([
            'Content-Type' => "application/json",
            'X-Shopify-Access-Token' => $this->x_access_token
        ])->post("$apiUrl/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'orderId' => $this->r_order['id']
            ]
        ]);

        if ($client->failed()) {
            $response = $client->json();
            $err_message = array_key_exists("errors", $response) ? $response['errors']:"";
            logger()->info($err_message);
            throw new \Exception($err_message, 422);
        } else {
            $response = $client->json();
            if (array_key_exists("errors", $response)) {
                $err_message = $response['errors'][0]['message'];
                throw new \Exception($err_message, 422);
            } else {
                logger()->info("Send Order Invoice Response:", $response);
            }
        }

        return $this;
    }
}
