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
use App\Services\OrderSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use Carbon\Carbon;

class OrdersController extends BaseController
{
    public function index(Request $request, OrderSearchService $orderSearch)
    {
        $perPage = max(1, min((int) $request->input('itemsPerPage', 10), 100));
        $sortBy = $request->input('sortBy', []);

        $orders = Order::with([
            'lineItems',
            'shippingAddress',
            'billingAddress',
            'intellicareLog',
            'prescriptions',
            'statusUpdatedBy' => function ($query) {
                return $query->select(
                    'auditable_id',
                    'auditable_by',
                    'created_at',
                    DB::raw("JSON_EXTRACT(value, '$.order_reason') as reason")
                );
            },
            'statusUpdatedBy.user'
        ])->where('orders.shopify_status', '!=', 'TRXN_ERROR');

        $orderSearch->apply($orders, $request);

        // Whitelist sortable fields so client-provided column names cannot cause SQL errors.
        $sortable = [
            'id' => 'orders.id',
            'customer_name' => 'orders.customer_name',
            'customer_email' => 'orders.customer_email',
            'shopify_order_name' => 'orders.shopify_order_name',
            'activeone_status' => 'orders.activeone_status',
            'intellicare_status' => 'orders.intellicare_status',
            'created_at' => 'orders.created_at',
            'totalAmount' => 'orders.totalAmount',
        ];

        if (is_array($sortBy)) {
            foreach ($sortBy as $sort) {
                $key = $sort['key'] ?? null;
                $direction = strtolower($sort['order'] ?? 'asc');

                if (isset($sortable[$key]) && in_array($direction, ['asc', 'desc'], true)) {
                    $orders->orderBy($sortable[$key], $direction);
                }
            }
        }

        $orders = $orders->paginate($perPage)->withQueryString();

        return $this->sendResponse($orders, "Orders retrieved successfully.");
    }

    public function export(Request $request, OrderSearchService $orderSearch)
    {
        $filename = 'activeone-orders-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () {

            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order Number',
                'Order Date Creation',
                'Name of Customer',
                'Status',
                'Approved (Partial)',
                'Changes Applied',
                'Comments',
                'Validation Date',
                'Validation Time',
                'Total Amount',
                'List of Medicine Ordered',
                'SKU Code',
                'QTY',
                'Email',
                'Contact Number (Phone Number)',
                'Attached Prescription'
            ]);

            $filteredOrders = Order::query()
                ->select('orders.id')
                ->where('orders.shopify_status', '!=', 'TRXN_ERROR');

            $orderSearch->apply($filteredOrders, $request);

            $orders = DB::table('orders as o')
                ->whereIn('o.id', $filteredOrders)

                ->leftJoin('order_details as od', 'od.order_id', '=', 'o.id')
                ->leftJoin('order_shippings as os', 'os.order_id', '=', 'o.id')
                ->where('o.shopify_status', '!=', 'TRXN_ERROR')
                ->select([
                    'o.shopify_order_name',
                    'o.created_at',
                    'o.customer_name',
                    'o.activeone_status',
                    'o.totalAmount',
                    'o.customer_email',
                    'os.phone',
                    'od.id as order_detail_id',
                    'od.title',
                    'od.sku',
                    'od.quantity',
                    'od.reason as item_reason',

                    DB::raw("
                    (
                    SELECT MAX(ol.created_at)
                    FROM order_logs ol
                    WHERE ol.table = 'orders'
                    AND ol.auditable_id = o.id
                    AND ol.action IN ('approve', 'reject')
                    ) as validation_at
                    "),

                    DB::raw("
                    (
                    SELECT JSON_UNQUOTE(JSON_EXTRACT(ol.value, '$.order_reason'))
                    FROM order_logs ol
                    WHERE ol.table = 'orders'
                    AND ol.auditable_id = o.id
                    AND ol.action = 'reject'
                    ORDER BY ol.id DESC
                    LIMIT 1
                    ) as rejection_reason
                    "),

                    DB::raw("
                    (
                    SELECT GROUP_CONCAT(
                    ol.summary
                    ORDER BY ol.id ASC
                    SEPARATOR ' | '
                    )
                    FROM order_logs ol
                    WHERE ol.table = 'order_details'
                    AND ol.auditable_id = od.id
                    AND ol.action IN ('create', 'update', 'delete')
                    ) as item_changes
                    "),

                    DB::raw("
                    (
                    SELECT GROUP_CONCAT(
                    DISTINCT op.file_path
                    ORDER BY op.id ASC
                    SEPARATOR '|'
                    )
                    FROM order_prescriptions op
                    WHERE op.order_id = o.id
                    ) as prescription_paths
                    "),

                    DB::raw("
                    EXISTS (
                    SELECT 1
                    FROM order_logs ol
                    WHERE ol.table = 'order_details'
                    AND ol.auditable_id IN (
                    SELECT od2.id
                    FROM order_details od2
                    WHERE od2.order_id = o.id
                    )
                    AND ol.action IN ('update', 'delete')
                    ) as has_item_changes
                    ")
                ])
                ->orderBy('o.created_at')
                ->orderBy('o.id')
                ->orderBy('od.id')
                ->cursor();

            foreach ($orders as $row) {

                $validationAt = $row->validation_at
                    ? Carbon::parse($row->validation_at)
                    : null;

                $status = strtoupper((string) $row->activeone_status);

                $changesApplied = $row->item_changes ?: '';

                $comments = $row->item_reason
                    ?: ($row->rejection_reason ?: '');

                $approvedPartial =
                    $status === 'APPROVED' && (bool) $row->has_item_changes
                    ? 'Yes'
                    : 'No';

                $prescriptionLinks = [];

                foreach (
                    array_filter(
                        explode('|', (string) $row->prescription_paths)
                    ) as $path
                ) {
                    $prescriptionLinks[] = url(
                        '/api/a1-shopify-integration/object?fileName=' .
                        urlencode($path)
                    );
                }

                fputcsv($handle, [
                    $row->shopify_order_name,
                    $row->created_at
                    ? Carbon::parse($row->created_at)->format('m/d/Y h:i:s A')
                    : '',
                    $row->customer_name,
                    $status,
                    $approvedPartial,
                    $changesApplied,
                    $comments,
                    $validationAt ? $validationAt->format('m/d/Y') : '',
                    $validationAt ? $validationAt->format('h:i:s A') : '',
                    $row->totalAmount,
                    $row->title,
                    $row->sku,
                    $row->quantity,
                    $row->customer_email,
                    $row->phone,
                    implode(' | ', $prescriptionLinks),
                ]);
            }

            fclose($handle);

        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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
            'prctype' => 'required|in:1,2,3,4,5',
            'prccode' => [
                'requiredIf:prctype,1',
                'alpha_num',
                'string',
                'between:4,7'
            ],
            'prcfirstname' => [
                'requiredIf:prctype,1',
            ],
            'prclastname' => [
                'requiredIf:prctype,1',
            ],
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
            $order = DB::transaction(function () use ($reqData, $orderLogService) {
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
                    $icd = $obj_item->merchandise['product']['code'] ?? "Not Available";
                    $diagnosisArrKey = array_search($icd, $orderLogService->diagnosis);
                    $icd_code = $orderLogService->diagnosis_codes[$diagnosisArrKey];
    
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
                            'icdcode' => $icd_code === FALSE ? $icd:$icd_code,
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
                    'prctype' => $reqData['prctype'],
                    'prccode' => $reqData['prccode'],
                    'prcfirstname' => $reqData['prcfirstname'],
                    'prclastname' => $reqData['prclastname'],
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
