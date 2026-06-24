<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Roles;
use Laravel\Socialite\Facades\Socialite;
use Auth;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = User::where('email', $googleUser->email)->first();
           
            if ($user) { // successfull authentication
                $user_auth = User::find($user->id);
                $role = Roles::find($user->role_id);
                
                Auth::login($user_auth); //authenticate
                $user_token= $user->createToken('appToken')->accessToken;

                return response()->json([
                    'success' => true,
                    'token' => $user_token,
                    'data' => ['user'=>$user, 'role'=>$role],
                ], 200);
            }
            else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate.',
                ], 401);
            }
            return redirect()->indended('dashboard');
        } catch (\Throwable $th) {
            dd('somehting went wrong!' . $th->getMessage());
        }
    }
}
