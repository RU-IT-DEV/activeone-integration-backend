<?php

namespace App\Jobs;

use App\Dispatchers\JobDispatcher;
use App\Helper\ShopifyHelper;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;

class ShopifyCreateOrderJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $order, $orderModel, $shopifyHelper;

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

        $this->shopifyHelper = new ShopifyHelper;
        $this->orderModel = $order;
        $this->order = $this->shopifyHelper->transformOrderData($order);

        logger()->info("ShopifyCreateOrderJob is running...");
        $apiUrl = $this->shopifyHelper->apiUrl;
        $query = file_get_contents(
            app_path('Helper/GraphQL/Mutations/OrderCreate.graphql')
        );

        $client = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->shopifyHelper->x_access_token
        ])->post("$apiUrl/admin/api/2026-07/graphql.json", [
            'query' => $query,
            'variables' => [
                'options' => [
                    "inventoryBehaviour" => "BYPASS",
                    "sendFulfillmentReceipt" => true,
                    "sendReceipt" => true
                ],
                'order' => $this->order
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
                    $this->orderModel->shopify_order_name = $order['name'];
                    $this->orderModel->save();
                    $this->orderModel->intellicareLog->receipt_number = str_replace("#", "", $order['name']);
                    $this->orderModel->intellicareLog->save();

                    // $this->clearCart();

                    JobDispatcher::dispatch(
                        new IntellicareCreateTransactionJob($this->orderModel->id)
                    );
                }
            } 
        }
    }

    private function clearCart()
    {
        $cartLines_response = $this->shopifyHelper->getCartLinesOnly($this->orderModel->shopify_cart_id);
        $lines = array_map(function ($v) {
            return $v['node']['id'];
        }, $cartLines_response['data']['cart']['lines']['edges']);
        $apiUrl = $this->shopifyHelper->apiUrl;
        $cartId = $this->orderModel->shopify_cart_id;
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
    }
}
