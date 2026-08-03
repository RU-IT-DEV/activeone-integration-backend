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
                $taxable = filter_var(
                    $obj_item->merchandise['taxable'],
                    FILTER_VALIDATE_BOOLEAN
                );

                $isPrescribed = filter_var(
                    $obj_item->merchandise['product']['is_prescribed'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );

                if (!is_null($obj_item->merchandise['image'])) {
                    $image = $obj_item->merchandise['image']['url'];
                }

                $orderDetails[] = [
                    'order_id' => $order->id,
                    'shopify_productId' => $obj_item->merchandise['product']['id'], 
                    'shopify_product_price' => $obj_item->merchandise['price']['amount'],
                    'image_url' => $image,
                    'quantity' => $obj_item->quantity, 
                    'sku' => $obj_item->merchandise['sku'],
                    'code' => $obj_item->merchandise['sku'], 
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
                'receipt_number' => "018324172983389",
                'prccode' => $reqData['prccode'],
                'diagnosis' => explode(",", $reqData['diagnosis']),
                'prescription_location' => ''
            ]);

            $order->load([
                'lineItems', 'shippingAddress','billingAddress','intellicareLog'
            ])->toArray();
            
            // Runs ONLY if the outer transaction succeeds completely
            DB::afterCommit(function () use ($order) {
                Bus::chain([
                    JobDispatcher::dispatch(new IntellicareCreateTransactionJob($order)),
                    JobDispatcher::dispatch(new ShopifyCreateOrderJob($order)),
                ])->dispatch();
            });

            return $order;
        });

        return $this->sendResponse($order, "Order has been added.");

        // $order = Order::where('intellicare_status', 'TRXN_SENT')->first();
        // $order->load([
        //     'lineItems', 'shippingAddress','billingAddress','intellicareLog'
        // ])->toArray();
        // $arr_resp = $intellicareHelper->transformTransactionData($order->intellicareLog);
        // $cc = new CustomCrypt;
        // print_r(json_encode($arr_resp));

        // echo $cc->encrypt(json_encode($arr_resp));

        // echo $cc->decrypt('U2FsdGVkX1+a2xAxSV6CBIrmaZzBujQnRTK8ERsSdsXPBGXabs65Z+2C7nSIIpZrTdSpSBBt1Ic83ZQy9Nv0nVeG69eXGzOwyHTp60TzR1JEb7myXkGbWaIl7yfLfiSzLCT6sxdOjzfz8tbuJqV3HGm3LQfmTTbT6sLMsPAkX94JDQBL2lBL87QBgepjzNsCvuc+sxWDiRprO+p3HmIJev55BYqT6WxQHMAXftZ/C6DIxk+ksx26A3K4SJRZN6leXuD0HQ8KCqX4FigH6OdrKFEDyUvesaXCYEsL9uiyKz3OuWDC5I+8DBZKG/PwGFzTb2EP26KA6V9A7kppEwpOvPoQYzSUk2XSBqtvAtjGXu+esDURviSEIoBUZwvnfrXMGb/Uyq0NBOiS+iCwIrVCJh+6dAGZQeZm9SLGP8KIgnHY2zsT94Zy5rj0iEEDPe9+0M6RrSoZ1uOGh9MP9CDhVB9Hv3r0OmxBoTCAVX1pHj6buSQ1/W1Y28Va05uOm9h64Qi9+OVngB/5NF4/E08TYuLiN8V8Dt4+X4VmooNkuNWI5VKI8IU8DM9uWocJi2fVUFTSyCJqVJkcLV0cN3gy04P6D4j5JETW8RUxNfrpIDXws9IHSNnwKxtfHKXyuCgZFaCAjTh+d6JHlVJsZPb/IDOQ5D0N1YYLxsXVcP44dKsxC9b5zyQaG6lktA2cTBEXCuAQeeyKYb9RY4WDuR24qSRdQGSBBDGf/48ciDjaZJg1nsE6bSy/e8g3YvIhCsd0yd+hdj3tvd2N4Y1rPUPfxAaaCXM9ZchQIwmiqrEHJCCBeCykV3ic0mXFfjC5zrer7ySsfOKqcbOn1z4HuOg+ka3QmI7VPbwUH/697BNSgFZ2KTi8GAjSFbXznkDIQwrr7LyTkAXWGv9G9Y+GK15FMa2GNydy+J22QpcPjSwcXl9BtAiuF/FNOt4RPYI/PipFlWq3rsgDlgU29bifXIp7RGuClLbjxz8vLiuXGLcFfQX1GlURbh2nNI9Zbi7H+w5kdu6BmkChVZLoSAyCzhVjkMW8qiB2xMZOkxBkyYPTr9c/zEaFCboO+6KJ59rVNt4owjL8D3932j91vVClA8YsJTwjtAE7c4jiK43LFwLi++yme1FDOnJHbjOChdJrGuw60mmX/8vvJEb0SpssARE3Ba4bdLq1W0s5smAJuAjxjCUmN/0bj3z2HKXN1X6oJHxrrpqmdzBdChMrMjG89ENCTCh5OYH9//dLnrViD/ayegOhB7SpSxDhBq1gmG6CVcvM/xuRcBwEIG+88exHoU6DUyt2n1ncs9jh4Hvb0OkOWePRa3cI3BIrwQa+KQ/eLw3OqTS+fTkV8NA5BXm50CIFLfXPNRUZKO6zbE+Av3DBeps=');

        // echo $cc->decrypt('U2FsdGVkX19Hd21O0VGB/rz5vseyEWO6oReyfvIUPpZQhLP+pymK6P1yrhKkjhbNHGvCedNi+VZMmyoru/LcSHGITNsbjtbWB8cf4OceBCCcLvWAXlbr5Ms4PW2cpTeZWGfPMICM7a/yK26cplC72w==');

        // JobDispatcher::dispatch(
        //     new IntellicareCreateTransactionJob($order)
        // );
    }

    public function showProductMetaobject (Request $request)
    {
        $fieldId = $request->input('fieldId');
    }
}
