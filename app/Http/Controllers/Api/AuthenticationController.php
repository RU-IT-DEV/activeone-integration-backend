<?php

namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController as Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use App\Models\Roles;
use App\Models\Members;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use Exception;

class AuthenticationController extends Controller
{
    // create and store
    public function store()
    {
        if (Auth::attempt(['email' => request('email'), 'password' => request('secret_key')])) {
            // successfull authentication
            $user = User::find(Auth::user()->id);
            $role = Roles::find($user->role_id);

            $user_token= $user->createToken('appToken')->accessToken;

            return response()->json([
                'success' => true,
                'token' => $user_token,
                'data' => ['user'=>$user, 'role'=>$role, 'user_type' => 'admin'],
            ], 200);
        } else {
            // failure to authenticate
            return response()->json([
                'success' => false,
                'message' => 'Failed to authenticate.',
            ], 401);
        }
    }

    // revoke token
    public function destroy(Request $request)
    {
        if (Auth::user()) {
            $request->user()->token()->revoke();
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
        }
    }

    public function getUserDetails () {
        if (Auth::user()) {
            $user = User::find(Auth::user()->id);
            $role = Roles::find($user->role_id);

            return response()->json([
                'success' => true,
                'data' => ['user'=>$user, 'role'=>$role],
            ], 200);
        }
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
                    // $member = Members::where('email', $decodedArray['preferred_username'])->first();
                    // if (!$member) {
                    //     throw new Exception('User not registered');
                    // }

                    // $member->last_signin = $member->signin;
                    // $member->signin = now();
                    // $member->update();
                    // $token = $member->createToken('appToken')->accessToken;

                    $user = User::where('email', $decodedArray['preferred_username'])->first();
                    $role = Roles::find($user->role_id);
        
                    $user_token= $user->createToken('appToken')->accessToken;
        
                    return response()->json([
                        'success' => true,
                        'token' => $user_token,
                        'data' => ['user'=>$user, 'role'=>$role, 'user_type' => 'admin'],
                    ], 200);

                    // Return the JWT token as a response
                    // return $this->successLog('signin', "AdminSignInWithMicrosoft")->sendResponse([
                    //     'token' => $user_token,
                    //     'user' => $user,
                    //     'user_type' => 'admin'
                    // ], "Login successful");

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
