<?php


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
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
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('register', [AuthController::class, 'register']);

    Route::post('cart', [CartController::class, 'store']);
    
    // Route::post('cart-session', []);
    Route::delete('/clear-cache', function () {
        // Your logic to clear the cache goes here
        // Example:
        \Cache::flush();
        return response()->json(['message' => 'Cache cleared successfully']);
    })->middleware('purge.auth');
});

