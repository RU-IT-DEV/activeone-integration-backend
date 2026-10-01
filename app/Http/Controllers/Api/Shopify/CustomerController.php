<?php

namespace App\Http\Controllers\Api\Shopify;

use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use App\Models\ShopifyCheckoutSession;
use Illuminate\Support\Str;
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
        $customerId = $request->query('logged_in_customer_id');
        $cartToken = $request->query('cart_token');

        if (!$customerId) {
            abort(401, 'Shopify customer is not logged in.');
        }

        if (!$cartToken) {
            abort(400, 'Cart token is required.');
        }

        $session = ShopifyCheckoutSession::create([
            'token' => Str::random(64),
            'shop' => $request->query('shop'),
            'shopify_customer_id' => $customerId,
            'cart_token' => $cartToken,
            'expires_at' => now()->addMinutes(5),
        ]);

        return redirect()->away(
            config('app.frontend_url') .
            '/checkout?token=' .
            urlencode($session->token)
        );
    }
}
