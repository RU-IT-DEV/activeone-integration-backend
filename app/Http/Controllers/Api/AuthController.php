<?php

namespace App\Http\Controllers\Api;

use App\Helper\ShopifyHelper;
use App\Helper\IntellicareHelper;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function verify(Request $request, IntellicareHelper $intellicare_helper) {
        $this->validate($request, [
            'hmoNumber' => 'required|string|max:20'
        ]);

        try {
            $arr_intellicare_response = $intellicare_helper->validateMember([
                'acct_no' => $request->hmoNumber
            ]);

            return $this->sendResponse($arr_intellicare_response, "Success.");
        } catch (\Exception $e) {
            $e_status = $e->getCode();
            if ($e_status === 400) {
                return $this->sendError("Something went wrong. Call your administrator", [
                    'errors' => [
                        'hmoNumber' => $e->getMessage()
                    ]
                ], 200);
            } else if ($e_status === 500) {
                return $this->sendError("Something went wrong. Call your administrator", [$e->getMessage()], 500);
            }
        }
    }

    public function register(Request $request, ShopifyHelper $shopify_helper) {
        $this->validate($request, [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email_address' => 'required|string',
            'account_no' => 'required|string|max:20',
            'company' => 'required|string',
            'email_personal' => "nullable|email",
            'address.country' => 'required|string',
            'address.province' => 'required|string',
            'address.city' => 'required|string',
            'address.address2' => 'nullable|string',
            'address.address1' => 'required|string',
        ], [
            'address.address1.required' => "Address line 1 is required.",
            'address.city.required' => "City is required.",
            'address.province.required' => "Province is required."
        ]);

        try {
            $data = $request->all();
            $arr_shopify_response = $shopify_helper
                ->processCreateCustomerInput($data)
                ->createUser()
                ->createUserAddress();

            return $this->sendResponse($arr_shopify_response, "Success.");
        } catch (\Exception $e) {
            if ($e->getCode() == 400) {
                return $this->sendError("Something went wrong. Call your administrator.", [
                    'errors' => [$e->getMessage()]
                ], 420);
            } else {
                return $this->sendError("Something went wrong. Call your administrator.", $e->getMessage(), 400);
            }
        }
    }
}
