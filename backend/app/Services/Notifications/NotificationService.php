<?php

namespace App\Services\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Notification;
use App\Models\Payment;
use App\Services\Normalizers\NumberNormalizer;
use App\Services\Telegram\TelegramService;

class NotificationService
{
    public function __construct(private readonly TelegramService $telegram) {}

    public function sendVerificationResult(Payment $payment): void
    {
        $status = $payment->status;

        if ($status === PaymentStatus::VERIFIED) {
            $this->websiteNotification($payment, 'verified');
            if ($payment->telegramUser) {
                $this->telegramVerification($payment, true);
            }
        } elseif ($status === PaymentStatus::PENDING_REVIEW) {
            $this->websiteNotification($payment, 'pending_review');
            if ($payment->telegramUser) {
                $this->telegramVerification($payment, false);
            }
        } elseif ($status === PaymentStatus::REJECTED) {
            $this->websiteNotification($payment, 'rejected');
            if ($payment->telegramUser) {
                $this->telegramVerification($payment, false, true);
            }
        }

        $this->notifyAdmin($payment);
    }

    private function telegramVerification(Payment $payment, bool $verified, bool $rejected = false): void
    {
        $user = $payment->telegramUser;
        $lang = $user->language === 'fa' ? 'fa' : 'en';

        $amount = NumberNormalizer::formatThousands((int) $payment->amount);
        $tracking = (string) ($payment->tracking_number ?: '');

        if ($verified) {
            $text = $lang === 'fa'
                ? "✅ پرداخت شما با موفقیت تأیید شد.\n\nمبلغ: {$amount} {$payment->currency}\nشماره پیگیری: {$tracking}"
                : "✅ Your payment has been verified successfully.\n\nAmount: {$amount} {$payment->currency}\nTracking number: {$tracking}";
        } elseif ($rejected) {
            $text = $lang === 'fa'
                ? "❌ پرداخت شما تایید نشد.\nمبلغ: {$amount} {$payment->currency}"
                : "❌ Your payment could not be verified.\nAmount: {$amount} {$payment->currency}";
        } else {
            $text = $lang === 'fa'
                ? "⏳ پرداخت شما در حال بررسی دستی است.\nمبلغ: {$amount} {$payment->currency}"
                : "⏳ Your payment is under manual review.\nAmount: {$amount} {$payment->currency}";
        }

        try {
            $this->telegram->sendMessage($user->getKey(), $text);
        } catch (\Throwable $e) {
            $this->websiteNotification($payment, $verified ? 'verified' : 'pending_review');
        }
    }

    private function websiteNotification(Payment $payment, string $kind): void
    {
        Notification::query()->create([
            'user_id' => $payment->user_id,
            'customer_id' => $payment->customer_id,
            'payment_id' => $payment->id,
            'type' => 'payment_status',
            'channel' => 'web',
            'title' => "Payment {$kind}",
            'body' => "Payment {$payment->code} is now {$kind}.",
            'status' => 'created',
        ]);
    }

    private function notifyAdmin(Payment $payment): void
    {
        $adminChatId = config('services.telegram.admin_chat_id');
        if (!$adminChatId) {
            return;
        }

        $line = "Payment {$payment->code} ({$payment->status->value})\nAmount: {$payment->amount} {$payment->currency}\nRisk: {$payment->risk_score}/100";
        try {
            $this->telegram->sendMessage($adminChatId, $line);
        } catch (\Throwable) {
            // admin notification is best-effort
        }
    }
}
