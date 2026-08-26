<?php

namespace App\Http\Controllers\Api;

use App\Helper\ShopifyHelper;
use App\Helper\IntellicareHelper;
use App\Http\Controllers\Api\BaseController;
use App\Models\CustomerEmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'address.phone_no' => [
                'required',
                'string',
                'max:13',
                'min:11',
                'regex:/^(09\d{9}|\+639\d{9})$/'
            ],
            'termsOfService' => 'required|accepted',
            'privacyPolicy' => 'required|accepted',
        ], [
            'address.address1.required' => "Address line 1 is required.",
            'address.city.required' => "City is required.",
            'address.province.required' => "Province is required.",
            'address.phone_no.required' => "The phone number field is required.",
            'address.phone_no.min' => "The phone number field should be 11 digits.",
            'address.phone_no.max' => "The phone number field should be 11 digits.",
            'address.phone_no.regex' => "The phone number field is invalid.",
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

    public function verifyAzureToken(Request $request)
    {
        $idToken = $request->header('Authorization');
        $idToken = str_replace('Bearer ', '', $idToken); // Remove 'Bearer' prefix if needed
        if (!$idToken) {
            return $this->sendError('ID token is required', [], 400);
        }

        $ad_url = config('services.msal_ad.cloud_url');
        $tenant = config('services.msal_ad.tenant_id');
        $jwksUri = "{$ad_url}{$tenant}/discovery/v2.0/keys";

        try {
            // Fetch the JWK (public keys)
            $client = new Client();
            $response = $client->get($jwksUri);
            $jwks = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
                throw new \Exception('Failed to retrieve valid public keys from Azure');
            }
    
            // Decode the token header to get the key ID (kid)
            $tokenParts = explode('.', $idToken);
    
            if (count($tokenParts) !== 3) {
                throw new \Exception('Invalid token format');
            }
    
            $header = json_decode(base64_decode($tokenParts[0]), true);
    
            if (!$header || !isset($header['kid'])) {
                throw new \Exception('Invalid token header or Key ID (kid) missing');
            }
    
            $kid = $header['kid'];
    
            // Find the corresponding public key in the JWK
            $key = null;
            foreach ($jwks['keys'] as $jwk) {
                if ($jwk['kid'] === $kid) {
                    if (!isset($jwk['alg'])) {
                        $jwk['alg'] = 'RS256';
                    }
                    $key = JWK::parseKey($jwk);
                    break;
                }
            }
    
            if (!$key) {
                throw new \Exception('Unable to find the key.');
            }
    
            $alg = ['RS256'];
            // Verify the token
            $decoded = JWT::decode($idToken, $key, $headers);
            logger()->info("decoded array");
            $decodedArray = (array) $decoded;
            // Validate claims
            $this->validateToken($decodedArray);
            
            if($decodedArray){
                if($decodedArray['oid']){
                    // $member = Members::where('email', $decodedArray['preferred_username'])->first();
                    // if (!$member) {
                    //     throw new Exception('User not registered');
                    // }

                    // $member->last_signin = $member->signin;
                    // $member->signin = now();
                    // $member->update();
                    // $token = $member->createToken('appToken')->accessToken;

                    $user = User::where('email', $decodedArray['preferred_username'])->first();
                    if (!$user) {
                        $arr_email = explode("@", $decodedArray['preferred_username']);
                        $name = str_replace(".", " ", $arr_email[0]);
                        $user = User::create([
                            'name' => $name,
                            'email' => $decodedArray['preferred_username'],
                            'password' => Hash::make("@ct1ve0ne_#2026"),
                            'email_verified_at' => Carbon::now(),
                        ]);
                    }
                    logger()->info("should create token");
        
                    $user_token = $user->createToken('appToken')->plainTextToken;
                    $user->remember_token = $user_token;
                    $user->save();
        
                    return $this->sendResponse([
                        'token' => $user_token,
                        'user'=>$user, 'role'=>'pharma', 'user_type' => 'admin',
                    ], "Logging in...");

                    // Return the JWT token as a response
                    // return $this->successLog('signin', "AdminSignInWithMicrosoft")->sendResponse([
                    //     'token' => $user_token,
                    //     'user' => $user,
                    //     'user_type' => 'admin'
                    // ], "Login successful");

                }else{
                    throw new \Exception('Invalid Token.');
                }
            }
            
            return $this->sendResponse([
                'token' => $decodedArray
            ], 'Token is valid!');
    
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    private function validateToken($token)
    {
        $clientId = config('services.msal_ad.client_id'); // Replace with your Azure AD Client ID
        $tenantId = config('services.msal_ad.tenant_id'); // Replace with your Azure AD Tenant ID
        $currentTime = time();

        if ($token['aud'] !== $clientId) {
            throw new \Exception('Invalid audience.');
        }

        if ($token['iss'] !== "https://login.microsoftonline.com/{$tenantId}/v2.0") {
            throw new \Exception('Invalid issuer.');
        }

        if ($token['exp'] < $currentTime) {
            throw new \Exception('Token has expired.');
        }

        if (isset($token['nbf']) && $token['nbf'] > $currentTime) {
            throw new \Exception('Token is not yet valid.');
        }

        // Add any additional claim validations here if needed
    }
}
