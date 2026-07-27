<?php

use App\Models\MemberClaims;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleAuthController;
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
Route::get('/check-database', [DatabaseController::class, 'checkDatabaseConnection']);
Route::get('/run-artisan/{command}', function ($command) {
    Artisan::call($command, ['--force' => true]);
    return "Command '{$command}' executed: " . Artisan::output();
});
Route::get('/run-schedule', function () {
    Artisan::call('schedule:run');
    return response()->json(['message' => 'Scheduler executed'], 200);
});
Route::get('/debug-db', function () {
    return [
        'env_password' => env('DB_PASSWORD') === null ? 'NULL' : 'SET',
        'config_password' => config('database.connections.mysql.password') === null ? 'NULL' : 'SET',
        'password_length' => strlen(config('database.connections.mysql.password') ?? ''),
        'host' => config('database.connections.mysql.host'),
        'user' => config('database.connections.mysql.username'),
        'database' => config('database.connections.mysql.database'),
    ];
});

Route::get('/pdo-test', function () {

    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                env('DB_HOST'),
                env('DB_PORT'),
                env('DB_DATABASE')
            ),
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );

        return [
            'success' => true,
            'server' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        ];

    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ];
    }
});
Route::get('/pdo-info', function () {
    return [
        'client_version' => PDO::getAvailableDrivers(),
        'mysql_client' => PDO::ATTR_CLIENT_VERSION,
    ];
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