<?php

use App\Http\Controllers\Api\AndroidSmsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TelegramController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // Public health endpoints
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/health/database', [HealthController::class, 'database']);
    Route::get('/health/queue', [HealthController::class, 'queue']);
    Route::get('/health/cache', [HealthController::class, 'cache']);

    // Auth
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Device registration (admin only)
    Route::post('/auth/register-device', [DeviceController::class, 'register'])
        ->middleware(['auth:sanctum', 'role:super_admin,admin']);

    // Android SMS endpoints (HMAC device auth + replay protection)
    Route::post('/android/sms', [AndroidSmsController::class, 'upload'])
        ->middleware(['device.auth', 'throttle:60,1']);
    Route::get('/android/status', [AndroidSmsController::class, 'status'])
        ->middleware('device.auth');

    // Customer payment + public receipt upload (one-time token)
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/payments/{token}', [PaymentController::class, 'show']);
    Route::post('/payments/{token}/receipt', [PaymentController::class, 'uploadReceipt'])->middleware('throttle:20,1');

    // Telegram webhook (guarded by shared secret; responds immediately)
    Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])
        ->middleware(['telegram.secret', 'throttle:30,1']);

    /*
    |--------------------------------------------------------------------------
    | Admin API (sanctum token + RBAC)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:super_admin,admin,payment_reviewer,support,read_only'])
        ->group(function () {

            Route::get('/payments', [\App\Http\Controllers\Admin\AdminPaymentController::class, 'indexJson'])
                ->name('api.admin.payments');
        });
});
