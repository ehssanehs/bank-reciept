<?php

use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminBankController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeviceController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\ReceiptDownloadController;
use App\Http\Controllers\Web\WebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public customer web
|--------------------------------------------------------------------------
*/
Route::get('/', [WebController::class, 'home'])->name('web.home');
Route::get('/pay', [WebController::class, 'showPayForm'])->name('web.pay');
Route::post('/pay', [WebController::class, 'createPay'])->name('web.pay.create');
Route::get('/pay/{token}', [WebController::class, 'showPayment'])->name('web.payment');
Route::post('/pay/{token}/receipt', [WebController::class, 'uploadReceipt'])->name('web.payment.receipt');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('web.locale');

Route::middleware('auth')->group(function () {
    Route::get('/link', [WebController::class, 'link'])->name('web.link');
    Route::post('/link', [WebController::class, 'generateLink'])->name('web.link.generate');
});

/*
|--------------------------------------------------------------------------
| Auth (admin panel session login)
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:super_admin,admin,payment_reviewer,support,read_only'])
    ->group(function () {

        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])
            ->middleware('role:super_admin,admin,payment_reviewer')->name('payments.approve');
        Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])
            ->middleware('role:super_admin,admin,payment_reviewer')->name('payments.reject');

        Route::get('/banks', [AdminBankController::class, 'index'])->name('banks.index');
        Route::get('/banks/create', [AdminBankController::class, 'create'])->name('banks.create');
        Route::post('/banks', [AdminBankController::class, 'store'])->name('banks.store');
        Route::get('/banks/{bank}', [AdminBankController::class, 'show'])->name('banks.show');
        Route::get('/banks/{bank}/edit', [AdminBankController::class, 'edit'])->name('banks.edit');
        Route::put('/banks/{bank}', [AdminBankController::class, 'update'])->name('banks.update');

        Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices');

        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');

        Route::get('/audit', [AdminAuditController::class, 'index'])->name('audit');

        Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers');
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');

        Route::get('/reports/payments', [AdminReportController::class, 'payments'])->name('reports.payments');
    });

// Authorized receipt download (outside web root, policy-gated)
Route::get('/receipts/{receipt}/download', [ReceiptDownloadController::class, 'download'])
    ->name('receipts.download')
    ->middleware(['auth', 'role:super_admin,admin,payment_reviewer,support']);
