<?php

namespace App\Http\Controllers\Api\Members;

use App\Http\Controllers\Api\BaseController as Controller;
use Illuminate\Http\Request;

use App\Models\Members;
use App\Models\MemberEmailVerification;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use App\Mail\MemberLoginMail;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use Exception;

class AuthController extends Controller
{
    public function sendVerificationMail (Request $request) {
        $this->validate($request, [
            'email' => 'required|email|exists:members,email'
        ], [
            'email.exists' => 'The email has not been registered.'
        ]);

        try {
            $member = Members::where('email', $request->email)->first();
            $token = Str::random(25);
            $signInLink = config('app.frontend_url') . "email-verify/$token";
            
            DB::beginTransaction();
            // send mail to the email  
            $name = $member->first_name." ".$member->last_name;  
            Mail::to($request->email)->send(new MemberLoginMail($signInLink, $request->email,$name));
            $member->email_verifications()->create([
                'email' => $request->email,
                'token' => $token
            ]);
            DB::commit();

            return $this->successLog('signin', 'MemberEmailVerification')->sendResponse([
                "success" => true,
            ], "Verification email sent successfully.");
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("sendVerificationMail:" . $e->getMessage());

            return $this->errorLog('signin', 'MemberEmailVerification')->sendError("Failed to send verification email.", [
                'email' => $request->email
            ], 500);
        }
    }

    public function memberLogin (Request $request) {
        $this->validate($request, [
            'token' => [
                'required',
                'exists:member_email_verification,token',
                function ($attribute, $value, $fail) use ($request) {
                    $verification = MemberEmailVerification::where('token', $value)->first();
                    if ($verification && $verification->sent_date && $verification->sent_date->diffInMinutes(now()) > 20) {
                        $verification->delete();
                        $fail('The verification link has expired.');
                    }
                }
            ],
        ], [
            'token.exists' => "Your token has expired or is invalid."
        ]);

        try {
            $member_email_verification = MemberEmailVerification::where('token', $request->token)->first();
            $member = $member_email_verification->member;
            $member_email_verification->delete();
            
            $member->last_signin = $member->signin;
            $member->signin = now();
            $member->update();
            
            $token = $member->createToken('appToken')->accessToken;
            $member = $member->load('company:code,name,logo_path');

            return $this->successLog('signin', 'MemberSignIn')->sendResponse([
                'token' => $token,
                'user' => $member,
                'user_type' => 'member'
            ], "Login successful.");
        } catch (\Exception $e) {
            \Log::error("Member login error " . $e->getMessage());

            $this->errorLog('signin', 'MemberSignIn')->sendError("An error occurred while logging in. Please try again later.", [
                'success' => false
            ], 500);
        }
    }

    public function memberLogout (Request $request) {
        $email = $request->user()->email;
        Auth::guard('member_api')->user()->token()->revoke();

        return $this->successLog('signout', 'MemberSignOut')
            ->sendResponse(['email' => $email], "Successfully logged out.");
    }

    public function verifyAzureToken(Request $request)
    {
        $idToken = $request->header('Authorization');
        $idToken = str_replace('Bearer ', '', $idToken); // Remove 'Bearer' prefix if needed
        if (!$idToken) {
            return $this->errorLog('signin', 'MemberSignInWithMicrosoft')->sendError('ID token is required', [], 400);
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
                throw new Exception('Failed to retrieve valid public keys from Azure');
            }
    
            // Decode the token header to get the key ID (kid)
            $tokenParts = explode('.', $idToken);
    
            if (count($tokenParts) !== 3) {
                throw new Exception('Invalid token format');
            }
    
            $header = json_decode(base64_decode($tokenParts[0]), true);
    
            if (!$header || !isset($header['kid'])) {
                throw new Exception('Invalid token header or Key ID (kid) missing');
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
                throw new Exception('Unable to find the key.');
            }
    
            $alg = ['RS256'];
            // Verify the token
            $decoded = JWT::decode($idToken, $key, $headers);
            $decodedArray = (array) $decoded;
            \Log::info($decodedArray);
            // Validate claims
            $this->validateToken($decodedArray);
            
            if($decodedArray){
                if($decodedArray['oid']){
                    $member = Members::where('email', $decodedArray['preferred_username'])->first();
                    if (!$member) {
                        throw new Exception('User not registered');
                    }

                    $member->last_signin = $member->signin;
                    $member->signin = now();
                    $member->update();
                    $token = $member->createToken('appToken')->accessToken;
                    $member = $member->load('company:id,name,logo_path');
                    // Return the JWT token as a response
                    return $this->successLog('signin', "MemberSignInWithMicrosoft")->sendResponse([
                        'token' => $token,
                        'user' => $member,
                        'user_type' => 'member'
                    ], "Login successful");

                }else{
                    throw new Exception('Invalid Token.');
                }
            }
            
            return $this->sendResponse([
                'token' => $decodedArray
            ], 'Token is valid!');
    
        } catch (\Exception $e) {
            return $this->errorLog('signin', "MemberSignInWithMicrosoft")->sendError($e->getMessage(), [], 400);
        }
    }

    private function validateToken($token)
    {
        $clientId = config('services.msal_ad.client_id'); // Replace with your Azure AD Client ID
        $tenantId = config('services.msal_ad.tenant_id'); // Replace with your Azure AD Tenant ID
        $currentTime = time();

        if ($token['aud'] !== $clientId) {
            throw new Exception('Invalid audience.');
        }

        if ($token['iss'] !== "https://login.microsoftonline.com/{$tenantId}/v2.0") {
            throw new Exception('Invalid issuer.');
        }

        if ($token['exp'] < $currentTime) {
            throw new Exception('Token has expired.');
        }

        if (isset($token['nbf']) && $token['nbf'] > $currentTime) {
            throw new Exception('Token is not yet valid.');
        }

        // Add any additional claim validations here if needed
    }
}
