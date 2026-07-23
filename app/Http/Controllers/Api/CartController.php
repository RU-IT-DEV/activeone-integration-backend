<?php

namespace App\Http\Controllers\Api;

use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function show(Request $request, ShopifyHelper $shopifyHelper)
    {
        $data = $request->all();

        try {
            $cart = $shopifyHelper->getCart($request->cartToken);
            $col_cart = collect($cart);
    
            return $this->sendResponse($cart, "Success");
        } catch (\Exception $e) {
            if ($e->getCode() === 422) {
                return $this->sendError("Something went wrong.", $e->getMessage(), 422);
            } else {
                return $this->sendError("Something went wrong.", $e->getMessage());
            }
        }
    }
}
