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
}
