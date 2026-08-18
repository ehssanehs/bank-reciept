<?php

namespace App\Services\Telegram;

use App\Enums\AuditEvent;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\TelegramMessage;
use App\Models\TelegramUser;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentService;
use App\Services\Receipt\ReceiptUploadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Handles Telegram updates (commands, link codes, receipt media) in both
 * Persian and English. Uses stable numeric chat_id for identity (never the
 * mutable username).
 */
class TelegramBotService
{
    private const PAYMENT_STATES = ['awaiting_payment_code'];

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly AuditLogger $audit,
        private readonly PaymentService $payments,
        private readonly ReceiptUploadService $receipts,
    ) {}

    /**
     * Process a single Telegram update (decoded JSON). Throws nothing on
     * user-level errors; logs internally and replies to the user.
     */
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if ($message === null) {
            // callback queries & other update types are ignored for now
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $messageId = $message['message_id'] ?? null;
        $text = (string) ($message['text'] ?? $message['caption'] ?? '');
        $from = $message['from'] ?? [];

        $user = $this->getOrCreateUser($chatId, $from);

        // Persist the incoming message (idempotent on message_id)
        $this->recordMessage($chatId, $messageId, $text, $message);

        $this->audit->log(AuditEvent::TELEGRAM_MESSAGE_RECEIVED, metadata: [
            'chat_id' => $chatId, 'message_id' => $messageId,
        ]);

        $lang = $user->language === 'fa' ? 'fa' : 'en';

        // Link code detection
        if (preg_match('/^\s*[0-9]{6,10}\s*$/', $text) === 1 && !$user->isLinked()) {
            $this->handleLinkCode($user, trim($text), $lang);
            return;
        }

        $photo = $message['photo'] ?? null;
        $document = $message['document'] ?? null;
        if ($photo !== null || $document !== null) {
            $this->handleReceiptMedia($user, $message, $lang);
            return;
        }

        // In-progress flows
        if ($user->state === 'awaiting_payment_code' && trim($text) !== '') {
            $this->handlePaymentCode($user, trim($text), $lang);
            return;
        }

        $this->handleCommand($user, $text, $lang);
    }

    private function handleCommand(TelegramUser $user, string $text, string $lang): void
    {
        $chatId = $user->getKey();
        $text = trim($text);
        $cmd = strtolower(explode(' ', $text)[0] ?? '');

        $reply = match ($cmd) {
            '/start' => $this->startReply($user, $lang),
            '/help' => $this->helpReply($lang),
            '/pay' => $this->beginPayFlow($user, $lang),
            '/status' => $this->statusReply($user, $lang),
            '/link' => $this->linkReply($user, $lang),
            default => $this->defaultReply($lang),
        };

        $this->telegram->sendMessage($chatId, $reply);
    }

    private function beginPayFlow(TelegramUser $user, string $lang): string
    {
        if (!$user->isLinked()) {
            return $lang === 'fa'
                ? 'ابتدا حساب خود را لینک کنید. با /link کد لینک را دریافت کنید.'
                : 'First link your account. Use /link to get a linking code.';
        }

        $user->forceFill(['state' => 'awaiting_payment_code', 'state_payload' => null])->save();

        return $lang === 'fa'
            ? 'لطفاً کد پرداخت (شماره پیگیری) خود را ارسال کنید:'
            : 'Please send your payment code (tracking number):';
    }

    private function handlePaymentCode(TelegramUser $user, string $code, string $lang): void
    {
        $chatId = $user->getKey();
        $payment = $user->customer?->payments()
            ->where('status', '!=', 'VERIFIED')
            ->where(fn ($q) => $q->where('code', $code)->orWhere('order_id', $code))
            ->latest()
            ->first();

        if ($payment === null) {
            $this->telegram->sendMessage($chatId, $lang === 'fa'
                ? 'پرداختی با این کد یافت نشد. لطفاً کد صحیح را ارسال کنید یا /pay را بزنید.'
                : 'No payment found with that code. Please send the correct code or use /pay.');
            return;
        }

        $user->forceFill([
            'state' => 'awaiting_receipt',
            'state_payload' => ['payment_id' => $payment->id],
        ])->save();

        $this->telegram->sendMessage($chatId, $lang === 'fa'
            ? 'حالا تصویر رسید پرداخت را ارسال کنید.'
            : 'Now send the payment receipt image.');
    }

    private function handleReceiptMedia(TelegramUser $user, array $message, string $lang): void
    {
        $chatId = $user->getKey();

        $state = $user->state_payload ?? [];
        $paymentId = $state['payment_id'] ?? null;

        if ($paymentId === null) {
            $this->telegram->sendMessage($chatId, $lang === 'fa'
                ? 'لطفاً ابتدا با /pay کد پرداخت را وارد کنید.'
                : 'Please use /pay to enter your payment code first.');
            return;
        }

        $payment = Payment::query()->find($paymentId);
        if ($payment === null) {
            $this->telegram->sendMessage($chatId, $lang === 'fa'
                ? 'پرداخت یافت نشد. دوباره /pay را بزنید.' : 'Payment not found. Use /pay again.');
            return;
        }

        // Download the media
        $file = null;
        if (isset($message['photo'])) {
            $file = $message['photo'][array_key_last($message['photo'])]['file_id'] ?? null;
        } elseif (isset($message['document'])) {
            $file = $message['document']['file_id'] ?? null;
        }

        if ($file === null) {
            $this->telegram->sendMessage($chatId, $lang === 'fa'
                ? 'رسید قابل خواندن نبود. لطفاً تصویر/مدارک را دوباره ارسال کنید.'
                : 'Could not read the receipt. Please resend the image/document.');
            return;
        }

        try {
            $tmp = $this->telegram->downloadFile($file);
            $originalName = $message['document']['file_name'] ?? 'receipt_'.Str::random(8).'.jpg';
            $receipt = $this->receipts->store($payment, $tmp, ['via' => 'telegram', 'chat_id' => $chatId]);
            @unlink($tmp);

            $this->payments->markReceiptReceived($payment);

            // Queue OCR + matching for this receipt.
            \App\Jobs\ProcessReceiptJob::dispatch($receipt->id);

            $this->telegram->sendMessage($chatId, $lang === 'fa'
                ? 'رسید دریافت شد و در حال بررسی است. ⏳' : 'Receipt received. Processing… ⏳');
        } catch (\Throwable $e) {
            $this->telegram->sendMessage($chatId, $lang === 'fa'
                ? 'خطا در دریافت رسید. دوباره تلاش کنید.' : 'Error receiving receipt. Try again.');
        }
    }

    private function startReply(TelegramUser $user, string $lang): string
    {
        return $lang === 'fa'
            ? "خوش آمدید! 🌟\nبرای شروع:\n/pay — ثبت پرداخت\n/status — وضعیت پرداخت\n/link — لینک حساب\n/help — راهنما"
            : "Welcome! 🌟\nCommands:\n/pay — submit a payment\n/status — payment status\n/link — link your account\n/help — help";
    }

    private function helpReply(string $lang): string
    {
        return $lang === 'fa'
            ? "راهنما:\n۱) با /pay کد پرداخت را وارد کنید\n۲) تصویر رسید را بفرستید\n۳) نتیجه بررسی ارسال می‌شود."
            : "Help:\n1) Use /pay and enter your payment code\n2) Send the receipt image\n3) You'll get the result.";
    }

    private function statusReply(TelegramUser $user, string $lang): string
    {
        $payment = $user->customer?->payments()->latest()->first();
        if ($payment === null) {
            return $lang === 'fa' ? 'پرداختی ثبت نشده است.' : 'No payment found.';
        }

        $status = __("payment.status.{$payment->status->value}", [], $lang === 'fa' ? 'fa' : 'en');

        return $lang === 'fa'
            ? "پرداخت: {$payment->code}\nوضعیت: {$status}"
            : "Payment: {$payment->code}\nStatus: {$status}";
    }

    private function linkReply(TelegramUser $user, string $lang): string
    {
        if ($user->isLinked()) {
            return $lang === 'fa' ? 'حساب شما قبلاً لینک شده است.' : 'Your account is already linked.';
        }

        $code = $this->generateLinkCode($user);
        $ttl = (int) config('services.telegram.link_code_ttl_minutes', 10);

        return $lang === 'fa'
            ? "برای لینک کردن، این کد را در وب‌سایت وارد کنید:\n<code>$code</code>\n(منقضی در $ttl دقیقه)"
            : "To link your account, enter this code on the website:\n<code>$code</code>\n(expires in $ttl minutes)";
    }

    private function defaultReply(string $lang): string
    {
        return $lang === 'fa'
            ? 'دستور نامعتبر است. /help را ببینید.' : 'Invalid command. See /help.';
    }

    private function handleLinkCode(TelegramUser $user, string $code, string $lang): void
    {
        $chatId = $user->getKey();

        if ($user->link_code !== null
            && hash_equals((string) $user->link_code, $code)
            && $user->link_code_expires_at !== null
            && $user->link_code_expires_at->isFuture()) {
            // find customer whose pending link code matches
            $customer = \App\Models\Customer::query()
                ->where('link_code', $code)
                ->first();

            if ($customer !== null) {
                DB::transaction(function () use ($user, $customer, $chatId, $lang) {
                    $user->forceFill([
                        'linked_customer_id' => $customer->id,
                        'link_code' => null,
                        'link_code_expires_at' => null,
                        'status' => 'active',
                    ])->save();
                    $customer->forceFill(['link_code' => null])->save();
                });

                $this->audit->log(AuditEvent::TELEGRAM_LINKED, metadata: ['chat_id' => $chatId, 'customer_id' => $customer->id]);

                $this->telegram->sendMessage($chatId, $lang === 'fa'
                    ? 'حساب شما با موفقیت لینک شد. ✅' : 'Your account has been linked. ✅');
                return;
            }
        }

        $this->telegram->sendMessage($chatId, $lang === 'fa'
            ? 'کد لینک نامعتبر است یا منقضی شده. از /link استفاده کنید.' : 'Invalid or expired link code. Use /link.');
    }

    private function generateLinkCode(TelegramUser $user): string
    {
        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('services.telegram.link_code_ttl_minutes', 10);

        $user->forceFill([
            'link_code' => $code,
            'link_code_expires_at' => now()->addMinutes($ttl),
        ])->save();

        // Store on the (to-be-created) customer record too via website flow.
        return $code;
    }

    private function getOrCreateUser(int $chatId, array $from): TelegramUser
    {
        $user = TelegramUser::query()->find($chatId);
        if ($user !== null) {
            $user->forceFill(['last_seen_at' => now()])->save();
            return $user;
        }

        $lang = 'en';
        $langCode = strtolower((string) ($from['language_code'] ?? ''));
        if (str_starts_with($langCode, 'fa') || str_starts_with($langCode, 'ar')) {
            $lang = 'fa';
        }

        return TelegramUser::query()->create([
            'chat_id' => $chatId,
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
            'username' => $from['username'] ?? null,
            'language' => $lang,
            'state' => null,
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
    }

    private function recordMessage(int $chatId, ?int $messageId, string $text, array $message): void
    {
        $existing = TelegramMessage::query()
            ->where('chat_id', $chatId)
            ->where('message_id', $messageId)
            ->exists();

        if ($existing) {
            return; // idempotent
        }

        TelegramMessage::query()->create([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'media_type' => isset($message['photo']) ? 'photo' : (isset($message['document']) ? 'document' : null),
            'media_file_id' => $message['photo'][array_key_last($message['photo'])]['file_id']
                ?? $message['document']['file_id'] ?? null,
            'caption' => $message['caption'] ?? null,
            'status' => 'received',
        ]);
    }
}
