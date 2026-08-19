<?php

namespace App\Http\Controllers\Api;

use App\Helper\ShopifyHelper;
use App\Helper\IntellicareHelper;
use App\Http\Controllers\Api\BaseController;
use App\Mail\CustomerRegistrationMail;
use App\Models\CustomerEmailVerification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AuthController extends BaseController
{
    public function verifyAccountNumber(Request $request, IntellicareHelper $intellicare_helper) {
        $this->validate($request, [
            'hmoNumber' => 'required|string|max:20|min:20'
        ], [
            'hmoNumber.required' => 'Member HMO Account Number is required',
            'hmoNumber.max' => 'Member HMO Account Number must be 16 digits',
            'hmoNumber.min' => 'Member HMO Account Number must be 16 digits',
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

    public function verifyEmail(Request $request)
    {
        $this->validate($request, [
            'e' => 'required'
        ]);

        $encrypted_email = substr($request->input('e'), 0, -26);
        $str_token = substr($request->input('e'), -25);

        $decrypt = Crypt::decrypt($encrypted_email);
        $value = CustomerEmailVerification::where('email', $decrypt)->where('token', $str_token)->first();

        if ($value) {
            if (Carbon::now() > $value->expires_at) {
                return $this->sendError("Token expired", [], 422);
            } else {
                return $this->sendResponse([], "Success");
            }
        } else {
            return $this->sendError("Not Found.", [], 404);
        }
    }

    public function register(Request $request, ShopifyHelper $shopify_helper) {
        $this->validate($request, [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email_address' => 'required|string',
            'account_no' => 'required|string|max:20',
            'birth_date' => 'required|date_format:Y-m-d\TH:i:s',
            'company' => 'required|string',
            'email_personal' => "nullable|email",
            'address.country' => 'required|string',
            'address.province' => 'required|string',
            'address.city' => 'required|string',
            'address.address2' => 'nullable|string',
            'address.address1' => 'required|string',
            'address.phone_no' => 'required|string',
            'termsOfService' => 'required|accepted',
            'privacyPolicy' => 'required|accepted',
        ], [
            'address.address1.required' => "Address line 1 is required.",
            'address.city.required' => "City is required.",
            'address.province.required' => "Province is required.",
            'address.phone_no.required' => "The phone number field is required."
        ]);

        try {
            $data = $request->all();
            $shopify_helper
                ->processCreateCustomerInput($data)
                ->createUser()
                ->createUserAddress()
                ->sendAccountInvite();

            return $this->sendResponse([], "Successfully created a user account.");
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
