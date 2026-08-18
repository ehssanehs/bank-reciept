<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\TelegramUser;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly PaymentStateMachine $stateMachine,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create a new payment and set it AWAITING_RECEIPT.
     */
    public function createPayment(array $data, ?Customer $customer = null, ?User $user = null, ?TelegramUser $telegramUser = null): Payment
    {
        $amount = $data['amount'] ?? null;
        if ($amount === null || (float) $amount <= 0) {
            throw new InvalidArgumentException('A positive amount is required.');
        }

        $payment = Payment::query()->create([
            'customer_id' => $customer?->id,
            'user_id' => $user?->id ?? $customer?->user_id,
            'telegram_user_id' => $telegramUser?->getKey(),
            'code' => Payment::generateCode(),
            'order_id' => $data['order_id'] ?? null,
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'IRR',
            'tracking_number' => $data['tracking_number'] ?? null,
            'status' => PaymentStatus::CREATED,
            'risk_score' => 0,
            'ocr_confidence' => 0.0,
            'upload_token' => Payment::generateUploadToken(),
            'expires_at' => now()->addHours((int) config('services.fraud.verification_window_hours', 72)),
            'metadata' => [
                'customer_provided_tracking' => $data['tracking_number'] ?? null,
                'source' => $data['source'] ?? 'web',
            ],
        ]);

        $this->stateMachine->transition($payment, PaymentStatus::AWAITING_RECEIPT, 'Payment created, awaiting receipt');

        return $payment;
    }

    /**
     * Associate a stored receipt with a payment and advance its lifecycle.
     */
    public function markReceiptReceived(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::AWAITING_RECEIPT || $payment->status === PaymentStatus::CREATED) {
            $this->stateMachine->transition($payment, PaymentStatus::RECEIPT_RECEIVED, 'Receipt uploaded');
        }

        return $payment;
    }

    /** Find a payment by its public one-time upload token. */
    public function findByUploadToken(string $token): ?Payment
    {
        return Payment::query()->where('upload_token', $token)->first();
    }
}
