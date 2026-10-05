<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
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

class OrdersController extends BaseController
{
    public function index(Request $request)
    {
        $perPage = $request->input('itemsPerPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', []);
        $orders = Order::with([
            'lineItems', 'shippingAddress', 'billingAddress', 'intellicareLog', 'prescriptions',
            'statusUpdatedBy' => function ($query) {
                return $query->select(
                    'auditable_id', 
                    'auditable_by',
                    'created_at',
                    DB::raw("JSON_EXTRACT(value, '$.order_reason') as reason")
                );
            }, 'statusUpdatedBy.user'
        ])->where('shopify_status', '!=', 'TRXN_ERROR');

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

    public function export(Request $request)\n    {\n        $filename = 'activeone-orders-' . now()->format('Ymd-His') . '.csv';\n\n        return response()->streamDownload(function () {\n            $handle = fopen('php://output', 'w');\n\n            fputcsv($handle, [\n                'Order Number',\n                'Order Date Creation',\n                'Name of Customer',\n                'Status',\n                'Approved (Partial)',\n                'Changes Applied',\n                'Comments',\n                'Validation Date',\n                'Validation Time',\n                'Total Amount',\n                'List of Medicine Ordered',\n                'SKU Code',\n                'QTY',\n                'Email',\n                'Contact Number (Phone Number)',\n                'Attached Prescription'\n            ]);\n\n            $orders = DB::table('orders as o')\n                ->leftJoin('order_details as od', 'od.order_id', '=', 'o.id')\n                ->leftJoin('order_shippings as os', 'os.order_id', '=', 'o.id')\n                ->where('o.shopify_status', '!=', 'TRXN_ERROR')\n                ->select([\n                    'o.shopify_order_name',\n                    'o.created_at',\n                    'o.customer_name',\n                    'o.activeone_status',\n                    'o.totalAmount',\n                    'o.customer_email',\n                    'os.phone',\n                    'od.id as order_detail_id',\n                    'od.title',\n                    'od.sku',\n                    'od.quantity',\n                    'od.reason as item_reason',\n                    DB::raw("(SELECT MAX(ol.created_at) FROM order_logs ol WHERE ol.table = 'orders' AND ol.auditable_id = o.id AND ol.action IN ('approve', 'reject')) as validation_at"),\n                    DB::raw("(SELECT JSON_UNQUOTE(JSON_EXTRACT(ol.value, '$.order_reason')) FROM order_logs ol WHERE ol.table = 'orders' AND ol.auditable_id = o.id AND ol.action = 'reject' ORDER BY ol.id DESC LIMIT 1) as rejection_reason"),\n                    DB::raw("(SELECT GROUP_CONCAT(ol.summary ORDER BY ol.id ASC SEPARATOR ' | ') FROM order_logs ol WHERE ol.table = 'order_details' AND ol.auditable_id = od.id AND ol.action IN ('create', 'update', 'delete')) as item_changes"),\n                    DB::raw("(SELECT GROUP_CONCAT(DISTINCT op.file_path ORDER BY op.id ASC SEPARATOR '|') FROM order_prescriptions op WHERE op.order_id = o.id) as prescription_paths"),\n                    DB::raw("EXISTS(SELECT 1 FROM order_logs ol WHERE ol.table = 'order_details' AND ol.auditable_id IN (SELECT od2.id FROM order_details od2 WHERE od2.order_id = o.id) AND ol.action IN ('update', 'delete')) as has_item_changes")\n                ])\n                ->orderBy('o.created_at')\n                ->orderBy('o.id')\n                ->orderBy('od.id')\n                ->cursor();\n\n            foreach ($orders as $row) {\n                $validationAt = $row->validation_at ? Carbon::parse($row->validation_at) : null;\n                $status = strtoupper((string) $row->activeone_status);\n                $changesApplied = $row->item_changes ?: '';\n                $comments = $row->item_reason ?: ($row->rejection_reason ?: '');\n\n                // Partial approval means the order was approved after at least one\n                // existing medicine was changed or removed during pharmacy validation.\n                $approvedPartial = $status === 'APPROVED' && (bool) $row->has_item_changes\n                    ? 'Yes'\n                    : 'No';\n\n                $prescriptionLinks = [];\n                foreach (array_filter(explode('|', (string) $row->prescription_paths)) as $path) {\n                    $prescriptionLinks[] = url('/api/a1-shopify-integration/object?fileName=' . urlencode($path));\n                }\n\n                fputcsv($handle, [\n                    $row->shopify_order_name,\n                    $row->created_at ? Carbon::parse($row->created_at)->format('m/d/Y h:i:s A') : '',\n                    $row->customer_name,\n                    $status,\n                    $approvedPartial,\n                    $changesApplied,\n                    $comments,\n                    $validationAt ? $validationAt->format('m/d/Y') : '',\n                    $validationAt ? $validationAt->format('h:i:s A') : '',\n                    $row->totalAmount,\n                    $row->title,\n                    $row->sku,\n                    $row->quantity,\n                    $row->customer_email,\n                    $row->phone,\n                    implode(' | ', $prescriptionLinks)\n                ]);\n            }\n\n            fclose($handle);\n        }, $filename, [\n            'Content-Type' => 'text/csv; charset=UTF-8',\n            'Content-Disposition' => 'attachment; filename="' . $filename . '"',\n        ]);\n    }\n\n    public function show(Request $request, Order $order) 
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

    public function createIntellicareTransaction(Request $request, Order $order)
    {
        JobDispatcher::dispatch(
            new IntellicareCreateTransactionJob($order->id)
        );

        return $this->sendResponse([], "Intellicare transaction created successfully.");
    }
}
