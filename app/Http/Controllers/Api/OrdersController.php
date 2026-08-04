<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Helper\IntellicareHelper;
use App\Http\Controllers\Api\BaseController;
use App\Jobs\IntellicareCreateTransactionJob;
use App\Jobs\ShopifyCreateOrderJob;
use App\Services\CustomCrypt;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;

class OrdersController extends BaseController
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $orders = Order::with(['lineItems', 'shippingAddress', 'billingAddress', 'intellicareLog'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->sendResponse($orders, "Orders retrieved successfully.");
    }
    public function store(Request $request, IntellicareHelper $intellicareHelper)
    {
        $reqData = $request->all();

        $this->validate($request, [
            'id' => 'required|string',
            'totalAmount' => 'required|numeric',
            'prccode' => 'required|string',
            'diagnosis' => 'required|string',
            'customer' => 'required|array',
            'customer.id' => 'required|string',
            'customer.email' => 'required|email',
            'customer.firstName' => 'required|string',
            'customer.lastName' => 'required|string',
            'customer.account_no' => 'required|string',
            'customer.birth_date' => 'required|date_format:Y-m-d',
            'customer.contract' => 'required|string',
            'address' => 'required|array',
            'address.address' => 'required|string',
            'address.baranggay' => 'required|string',
            'address.city' => 'required|string',
            'address.country' => 'required|string',
            'address.region' => 'required|string',
            'address.postalCode' => 'required|string',
            // Add more validation rules as needed
        ]);

        $order = DB::transaction(function () use ($reqData) {
            $customer = (object) $reqData['customer'];

            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
                'customer_name' => "{$customer->firstName} {$customer->lastName}",
                'shopify_cart_id' => $reqData['id'],
                'financialStatus' => 'PENDING', 
                'totalAmount' => $reqData['totalAmount'],
                'test' => true, 
                'intellicare_status' => 'VERIFYING', 
                'shopify_status' => 'PENDING',
                'activeone_status' => 'VERIFYING'
            ]);
            $address = (object) $reqData['address'];
            $lineItems = $reqData['edges'];
            $orderDetails = [];
            foreach ($lineItems as $key => $item) {
                $obj_item = (object) $item;
                $image = null; 
                $productCode = empty($obj_item->merchandise['product']['code']) ? 
                    $obj_item->merchandise['sku'] : $obj_item->merchandise['product']['code'];
                $taxable = filter_var(
                    $obj_item->merchandise['taxable'],
                    FILTER_VALIDATE_BOOLEAN
                );

                $isPrescribed = filter_var(
                    $obj_item->merchandise['product']['is_prescribed'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );

                if (isset($obj_item->merchandise['image'])) {
                    if (!is_null($obj_item->merchandise['image'])) {
                        $image = $obj_item->merchandise['image']['url'];
                    }
                }

                $orderDetails[] = [
                    'order_id' => $order->id,
                    'shopify_productId' => $obj_item->merchandise['product']['id'], 
                    'shopify_product_price' => $obj_item->merchandise['price']['amount'],
                    'image_url' => $image,
                    'quantity' => $obj_item->quantity, 
                    'sku' => $obj_item->merchandise['sku'],
                    'code' => $productCode, 
                    'title' => $obj_item->merchandise['product']['title'], 
                    'type' => $obj_item->merchandise['product']['category']['name'], 
                    'variantTitle' => $obj_item->merchandise['title'],
                    'unit' => $obj_item->merchandise['selectedOptions'][0]['name'],
                    'amount' => $obj_item->cost['totalAmount']['amount'], 
                    'vat_amount' => $obj_item->cost['tax']['amount'], 
                    'no_vat_amount' => $obj_item->cost['deductableToEmployee']['amount'], 
                    'taxable' => $taxable,
                    'is_prescribed' => $isPrescribed
                ];

            }
            $order->lineItems()->createMany($orderDetails);

            $addressData = [
                'address1' => $address->address,
                'address2' => $address->address2,
                'city' => $address->city,
                'countryCode' => $address->country,
                'provinceCode' => $address->region,
                'zip' => $address->postalCode,
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'phone' => $address->phone,
            ];

            $order->shippingAddress()->create($addressData);
            $order->billingAddress()->create($addressData);

            $order->intellicareLog()->create([
                'order_id' => $order->id,
                'account_no' => $customer->account_no,
                'first_name' => $customer->firstName,
                'last_name' => $customer->lastName,
                'birth_date' => $customer->birth_date,
                'contract' => $customer->contract,
                'branch' => 'NCR-PS',
                'prccode' => $reqData['prccode'],
                'diagnosis' => explode(",", $reqData['diagnosis']),
                'prescription_location' => ''
            ]);

            $order->load([
                'lineItems', 'shippingAddress','billingAddress','intellicareLog'
            ])->toArray();
            
            // Runs ONLY if the outer transaction succeeds completely
            DB::afterCommit(function () use ($order) {
                JobDispatcher::dispatch(
                    new ShopifyCreateOrderJob($order->id)
                );
            });

            return $order;
        });

        return $this->sendResponse($order, "Order has been added.");

        // $order = Order::where('intellicare_status', 'TRXN_SENT')->first();
        // $order->load([
        //     'lineItems', 'shippingAddress','billingAddress','intellicareLog'
        // ])->toArray();

        // JobDispatcher::dispatch(
        //     new IntellicareCreateTransactionJob($order)
        // );
    }

    public function showProductMetaobject (Request $request)
    {
        $fieldId = $request->input('fieldId');
    }
}
