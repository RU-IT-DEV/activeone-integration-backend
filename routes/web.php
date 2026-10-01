<?php

use App\Models\MemberClaims;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\Api\Shopify\CustomerController;
use App\Http\Middleware\VerifyShopifyProxy;
use App\Http\Controllers\DatabaseController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('googleAuth');
});

# Web routes for SSO
   # Route::get('auth/google', [GoogleAuthController::class,'redirectToGoogle'])->name('google-auth');
   # Route::get('auth/google/call-back', [GoogleAuthController::class,'handleGoogleCallback']);

#Test Database connection
Route::get('/shopify/proxy/{path?}', function (Request $request, $path = null) {
    return response()->json([
        'success' => true,
        'path' => $path,
        'shop' => $request->query('shop'),
        'customer_id' => $request->query('logged_in_customer_id'),
        'cart_token' => $request->query('cart_token'),
        'timestamp' => $request->query('timestamp'),
        'signature' => $request->query('signature'),
    ]);
})->where('path', '.*')->middleware(VerifyShopifyProxy::class);
// Route::get('/shopify/proxy/checkout', [
//     CustomerController::class,
//     'checkout',
// ])->middleware(VerifyShopifyProxy::class);
Route::get('/check-database', [DatabaseController::class, 'checkDatabaseConnection']);
Route::get('/run-artisan/{command}', function ($command) {
    Artisan::call($command, ['--force' => true]);
    return "Command '{$command}' executed: " . Artisan::output();
});
Route::get('/run-schedule', function () {
    Artisan::call('schedule:run');
    return response()->json(['message' => 'Scheduler executed'], 200);
});
Route::get('/run-member_claims-factory/{userId}/{planLinkId}/{count}', function ($userId, $planLinkId, $count) {
    abort_unless(app()->environment('local'), 403);

    MemberClaims::factory()
        ->userId($userId)
        ->planLinkId($planLinkId)
        ->withSubClaims()
        ->count($count)
        ->create();
    return response()->json(['message' => 'Member claims factory executed'], 200);
});