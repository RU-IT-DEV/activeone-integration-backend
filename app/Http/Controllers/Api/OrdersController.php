<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use App\Jobs\IntellicareCreateTransactionJob;
use App\Jobs\ShopifyCreateOrderJob;
use App\Mail\PharmaRejectionMail;
use Illuminate\Support\Facades\Mail;
use App\Services\CustomCrypt;
use App\Services\OrderLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderDetails;

class OrdersController extends BaseController
{
    public function index(Request $request)
    {
        $perPage = $request->input('itemsPerPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', []);
        $orders = Order::with(['lineItems', 'shippingAddress', 'billingAddress', 'intellicareLog', 'prescriptions'])
            ->where('shopify_status', '!=', 'TRXN_ERROR');

        if ($search) {
            $orders->where(function ($query) use ($search) {
                $query->where('shopify_order_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('totalAmount', 'like', "%{$search}%")
                    ->orWhere('intellicare_status', 'like', "%{$search}%")
                    ->orWhere('activeone_status', 'like', "%{$search}%");
            });
        }

        if ($sortBy) {
            foreach ($sortBy as $value) {
                $orders = $orders->orderBy($value['key'], $value['order']);
            }
        }

        $orders = $orders->paginate($perPage);

        return $this->sendResponse($orders, "Orders retrieved successfully.");
    }

    public function show(Request $request, Order $order) 
    {
        $order->load([
            'lineItems', 'shippingAddress', 'billingAddress', 'intellicareLog', 'prescriptions', 
            'rejected' => function ($query) {
                return $query->select(
                    'auditable_id', 
                    'value', 
                    'created_at',
                    DB::raw("JSON_EXTRACT(value, '$.order_reason') as reason")
                );
            }
        ]);
        
        return $this->sendResponse($order, "Order {$order->id} is retrieved");
    }
    public function store(Request $request, OrderLogService $orderLogService)
    {
        $reqData = $request->all();

        $this->validate($request, [
            'id' => 'required|string',
            'totalAmount' => 'required|numeric',
            'prccode' => 'required|string|alpha_num|between:4,7',
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
            'address.barangay' => 'required|string',
            'address.city' => 'required|string',
            'address.country' => 'required|string',
            'address.region' => 'required|string',
            'address.postalCode' => 'required|string',
            // Add more validation rules as needed
        ]);

        try {
            $order = DB::transaction(function () use ($reqData) {
                $customer = (object) $reqData['customer'];
    
                $order = Order::create([
                    'customer_id' => $customer->id,
                    'customer_email' => $customer->email,
                    'customer_name' => "{$customer->firstName} {$customer->lastName}",
                    'shopify_cart_id' => $reqData['id'],
                    'financialStatus' => 'PENDING', 
                    'totalAmount' => $reqData['totalAmount'],
                    'test' => config('app.env') !== 'production', 
                    'intellicare_status' => 'TRXN_CREATE', 
                    'shopify_status' => 'FOR_VERIFICATION',
                    'activeone_status' => 'TRXN_CREATED'
                ]);
                $address = (object) $reqData['address'];
                $lineItems = $reqData['edges'];
                $orderDetails = [];
                foreach ($lineItems as $key => $item) {
                    $obj_item = (object) $item;
                    $image = null; 
                    $category = null;
                    $taxable = filter_var(
                        $obj_item->merchandise['taxable'],
                        FILTER_VALIDATE_BOOLEAN
                    );
    
                    if (isset($obj_item->merchandise['image'])) {
                        if (!is_null($obj_item->merchandise['image'])) {
                            $image = $obj_item->merchandise['image']['url'];
                        }
                    }
    
                    if (array_key_exists('category', $obj_item->merchandise['product'])) {
                        $category = $obj_item->merchandise['product']['category']['name'];
                    }
    
                    if ($obj_item->quantity > 0) {
                        $orderDetails[] = [
                            'order_id' => $order->id,
                            'shopify_productId' => $obj_item->merchandise['product']['id'], 
                            'shopify_product_price' => $obj_item->merchandise['price']['amount'],
                            'image_url' => $image,
                            'quantity' => $obj_item->quantity, 
                            'sku' => $obj_item->merchandise['sku'],
                            'code' => $obj_item->merchandise['sku'], 
                            'title' => $obj_item->merchandise['product']['title'], 
                            'type' => $category, 
                            'variantTitle' => $obj_item->merchandise['title'],
                            'unit' => $obj_item->merchandise['selectedOptions'][0]['name'],
                            'amount' => $obj_item->cost['totalAmount']['amount'], 
                            'vat_amount' => $obj_item->cost['tax']['amount'], 
                            'no_vat_amount' => $obj_item->cost['deductableToEmployee']['amount'], 
                            'taxable' => $taxable,
                            'is_prescribed' => true
                        ];
                    }
    
                }
                $order->lineItems()->createMany($orderDetails);
    
                $addressData = [
                    'address1' => $address->address,
                    'address2' => "{$address->address2} {$address->barangay}",
                    'city' => $address->city,
                    'countryCode' => $address->country,
                    'provinceCode' => $address->region,
                    'zip' => $address->postalCode,
                    'firstName' => $customer->firstName,
                    'lastName' => $customer->lastName,
                    'phone' => $address->phone ?? null,
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
                
                return $order;
            });
    
            $orderLogService->store($order->id, $order);
    
            $response = [
                'id' => $order->id,
                'customer_id' => $order->customer_id,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'shopify_cart_id' => $order->shopify_cart_id,
                'financialStatus' => $order->financialStatus,
                'totalAmount' => $order->totalAmount,
                'test' => $order->test,
                'intellicare_status' => $order->intellicare_status,
                'shopify_status' => $order->shopify_status,
                'activeone_status' => $order->activeone_status,
                'isp' => $order->intellicareLog
            ];
    
            return $this->sendResponse($response, "Order has been added.");
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
            DB::rollback();
            return $this->sendError($e->getMessage(), [], 500);
        }

        // $order = Order::where('intellicare_status', 'TRXN_SENT')->first();
        // $order->load([
        //     'lineItems', 'shippingAddress','billingAddress','intellicareLog'
        // ])->toArray();
    }

    public function update(Request $request, Order $order, OrderLogService $orderLogService) {
        $this->validate($request, [
            'activeone_status' => 'required|in:APPROVED,REJECTED'
        ], [
            'activeone_status.required' => "ActiveOne status is required.",
            'activeone_status.in' => "ActiveOne status must be Approved or Rejected.",
        ]);

        try {
            DB::beginTransaction();
            if (in_array($order->activeone_status, ['APPROVED','REJECTED'])) {
                throw new \Exception("You cannot change status of an order twice. This order has already been {$order->activeone_status}.", 400);
            }
            $order->shopify_status = "PENDING";
            $order->activeone_status = $request->input('activeone_status');
            $order->save();

            if (is_null($order->shopify_order_name) && $order->activeone_status == "APPROVED") {
                JobDispatcher::dispatch(
                    new ShopifyCreateOrderJob($order->id)
                );
            }

            $order->refresh();
            $order->load([
                'lineItems', 'shippingAddress', 'billingAddress', 'intellicareLog', 'prescriptions'
            ]);

            if ($request->input('activeone_status') == 'APPROVED') {
                $orderLogService->approve($order->id, $order);
            } else if ($request->input('activeone_status') == 'REJECTED') {
                $order->order_reason = $request->input('reason');
                $orderLogService->reject($order->id, $order);
                
                $mail_msg = $order->order_reason;

                if (isset($orderLogService->arr_reject_reason_email_msg[$order->order_reason])) {
                    $mail_msg = $orderLogService->arr_reject_reason_email_msg[$order->order_reason];
                }
                Mail::to($order->customer_email)
                    ->send(new PharmaRejectionMail($order, $mail_msg));
            }

            DB::commit();
            return $this->sendResponse($order, "Order {$order->shopify_order_name} is {$order->activeone_status}.");
        } catch (\Exception $e) {
            DB::rollback();
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
    

    public function showProductMetaobject (Request $request)
    {
        $data = $request->all();
        $this->validate($request, [
            'value' => 'required|string',
            'process' => 'required|string'
        ]);

        $cc = new CustomCrypt;
        if ($data['process'] === 'encrypt') {
            $metaobject = $cc->encrypt($data['value']);
        } else if ($data['process'] === 'decrypt') {
            $metaobject = $cc->decrypt($data['value']);
        } else {
            return $this->sendError("Invalid process type. Use 'encrypt' or 'decrypt'.", [], 400);
        }

        return $this->sendResponse($metaobject, "Product metaobject retrieved successfully.");
    }
    
    public function updateOrderIcDs(
        Request $request,
        OrderLogService $orderLogService,
        ShopifyHelper $shopifyHelper
    )
    {
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $productCache = [];

        OrderDetails::query()
            ->whereNull('icd')
            ->whereNull('icdcode')
            ->orderBy('id')
            ->chunkById(100, function ($orderDetails) use (
                &$updated,
                &$skipped,
                &$errors,
                &$productCache,
                $shopifyHelper,
                $orderLogService
            ) {
                foreach ($orderDetails as $orderDetail) {
                    $sku = trim((string) $orderDetail->sku);

                    if ($sku === '') {
                        $skipped++;
                        $errors[] = [
                            'order_detail_id' => $orderDetail->id,
                            'sku' => null,
                            'reason' => 'SKU is empty.',
                        ];
                        continue;
                    }

                    try {
                        if (!array_key_exists($sku, $productCache)) {
                            $productCache[$sku] = $shopifyHelper->getProductBySku($sku);
                        }

                        $product = $productCache[$sku];

                        if (!$product) {
                            $skipped++;
                            $errors[] = [
                                'order_detail_id' => $orderDetail->id,
                                'sku' => $sku,
                                'reason' => 'Shopify product not found.',
                            ];
                            continue;
                        }

                        $find_productPrice = collect(data_get($product, 'variants.nodes', []))
                            ->first()['price'];

                        $productPrice = floatval($find_productPrice);

                        $medicineCode = collect(data_get($product, 'metafields.nodes', []))
                            ->firstWhere('key', 'medicine_code')['value'] ?? null;

                        $medicineCode = strtoupper(trim((string) $medicineCode));

                        if ($medicineCode === '') {
                            $skipped++;
                            $errors[] = [
                                'order_detail_id' => $orderDetail->id,
                                'sku' => $sku,
                                'reason' => 'Shopify product has no medicine_code.',
                            ];
                            continue;
                        }

                        $diagnosisIndex = FALSE;
                        foreach ($orderLogService->diagnosis as $key => $icd) {
                            if (strtoupper($icd) === $medicineCode) {
                                $diagnosisIndex = $key;
                            }
                        }

                        if ($diagnosisIndex === false || !isset($orderLogService->diagnosis_codes[$diagnosisIndex])) {
                            $skipped++;
                            $errors[] = [
                                'order_detail_id' => $orderDetail->id,
                                'sku' => $sku,
                                'medicine_code' => $medicineCode,
                                'reason' => 'Medicine code is not in the ICD diagnosis list.',
                            ];
                            continue;
                        }

                        $orderDetail->update([
                            'icd' => $orderLogService->diagnosis[$diagnosisIndex],
                            'icdcode' => $orderLogService->diagnosis_codes[$diagnosisIndex],
                            'amount' => $productPrice,
                            'vat_amount' => $productPrice * 0.2,
                            'no_vat_amount' => $productPrice - ($productPrice * 0.2)
                        ]);

                        $orderDetail->refresh();

                        $orderLogService->orderDetails->systemUpdate(
                            $orderDetail->id,
                            $orderDetail,
                            "ICD updated from Shopify medicine code {$medicineCode}."
                        );

                        $updated++;
                    } catch (\Throwable $e) {
                        $skipped++;
                        $errors[] = [
                            'order_detail_id' => $orderDetail->id,
                            'sku' => $sku,
                            'reason' => $e->getMessage(),
                        ];

                        logger()->error('Failed to update order detail ICD.', [
                            'order_detail_id' => $orderDetail->id,
                            'sku' => $sku,
                            'exception' => $e,
                        ]);
                    }
                }
            });

        return $this->sendResponse([
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ], 'Order detail ICD update completed.');
    }

    public function createIntellicareTransaction(Request $request, Order $order)
    {
        JobDispatcher::dispatch(
            new IntellicareCreateTransactionJob($order->id)
        );

        return $this->sendResponse([], "Intellicare transaction created successfully.");
    }
}
