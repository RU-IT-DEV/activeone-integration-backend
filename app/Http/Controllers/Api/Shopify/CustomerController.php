<?php

namespace App\Http\Controllers\Api\Shopify;

use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;

class CustomerController extends BaseController
{
    public function show(Request $request, ShopifyHelper $shopifyHelper)
    {
        $customer_id = $request->input('customerId');
        logger()->info($customer_id);
        return $this->sendResponse($shopifyHelper->getCustomer($customer_id), "Success.");
    }

    public function checkout(Request $request)
    {
        return response()->json([
            'shop' => $request->query('shop'),
            'customer_id' => $request->query('logged_in_customer_id'),
            'cart_token' => $request->query('cart_token'),
            'timestamp' => $request->query('timestamp'),
        ]);
    }
}
