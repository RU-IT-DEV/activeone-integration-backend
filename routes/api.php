<?php


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\DoctorsController;
use App\Http\Controllers\Api\FileSystemController;
use App\Http\Controllers\Api\OrderPrescriptionController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\ProcessJobsController;
use App\Http\Controllers\Api\Shopify\CustomerController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

 
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group(['namespace' => 'Api', 'middleware' => ['cors']], function () {
    // ... other routes ...
    Route::prefix('verify')->group(function () {
        Route::post('account-number', [AuthController::class, 'verifyAccountNumber']);
        Route::post('email', [AuthController::class, 'verifyEmail']);
    });
    Route::post('register', [AuthController::class, 'register']);

    Route::get('customer-default-address', [CustomerController::class, 'show']);
    Route::get('cart', [CartController::class, 'show']);
    Route::get('doctors', [DoctorsController::class, 'index']);
    
    Route::post('order', [OrdersController::class, 'store']);
    Route::post('order/{order}/prescriptions', [OrderPrescriptionController::class, 'store']);
    Route::get('orders', [OrdersController::class, 'index'])->middleware('auth:sanctum');
    Route::post('product-metaobject', [OrdersController::class, 'showProductMetaobject']);
    Route::post('tasks/process-queue-job', [ProcessJobsController::class, 'queue_work']);
    
    Route::get('a1-shopify-integration/object', [FileSystemController::class, 'getSignedUrl']);

    Route::prefix('admin')->group(function () {
        Route::post('login-with-msal', [AuthController::class, 'verifyAzureToken']);
        Route::get('refresh-token', [AuthController::class, 'refreshToken'])->middleware('auth:sanctum');
    });

    // Route::post('cart-session', []);
    Route::delete('/clear-cache', function () {
        // Your logic to clear the cache goes here
        // Example:
        \Cache::flush();
        return response()->json(['message' => 'Cache cleared successfully']);
    })->middleware('purge.auth');
});

