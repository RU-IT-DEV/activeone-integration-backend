<?php

namespace App\Http\Controllers\Api;

use App\Helper\ShopifyHelper;
use App\Helper\IntellicareHelper;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function register(Request $request, IntellicareHelper $intellicare_helper, ShopifyHelper $shopify_helper) {
        $this->validate($request, [
            'hmoNumber' => 'required|string|max:20'
        ]);

        try {
            $arr_intellicare_response = $intellicare_helper->validateMember([
                'acct_no' => $request->hmoNumber
            ]);
            $arr_shopify_response = $shopify_helper->processCreateCustomerInput($arr_intellicare_response)->createUser();

            logger()->info($arr_shopify_response);

            return $this->sendResponse([], "Success.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), "Something went wrong. Call your administrator", 400);
        }
    }
}
