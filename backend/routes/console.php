<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
*/

// Mark payments whose verification window has passed as EXPIRED.
Schedule::call(function () {
    \App\Models\Payment::query()
        ->whereIn('status', ['CREATED', 'AWAITING_RECEIPT', 'RECEIPT_RECEIVED', 'OCR_PROCESSING', 'MATCHING', 'PENDING_REVIEW'])
        ->where('expires_at', '<', now())
        ->each(function (\App\Models\Payment $payment) {
            app(\App\Services\Payments\PaymentStateMachine::class)
                ->transition($payment, \App\Enums\PaymentStatus::EXPIRED, 'Verification window expired');
        });
})->everyFiveMinutes()->name('payments-expire');

// Remove expired Telegram link codes.
Schedule::call(function () {
    \App\Models\TelegramUser::query()
        ->whereNotNull('link_code')
        ->where('link_code_expires_at', '<', now())
        ->update(['link_code' => null, 'link_code_expires_at' => null]);
})->hourly()->name('telegram-link-codes-cleanup');

// Purge old receipt files per retention policy.
Schedule::call(function () {
    app(\App\Services\Receipt\ReceiptRetentionService::class)->prune();
})->daily()->name('receipts-retention');
