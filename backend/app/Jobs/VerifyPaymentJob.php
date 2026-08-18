<?php

namespace App\Jobs;

use App\Models\OcrResult;
use App\Models\Payment;
use App\Services\Matching\PaymentMatchingEngine;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyPaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoffMinutes = [2, 10, 30];

    public function __construct(
        public readonly string $paymentId,
        public readonly string $ocrResultId
    ) {}

    public function handle(
        PaymentMatchingEngine $engine,
        NotificationService $notifications
    ): void {
        $payment = Payment::query()->find($this->paymentId);
        $ocr = OcrResult::query()->find($this->ocrResultId);

        if ($payment === null || $ocr === null) {
            return;
        }

        $receipt = $ocr->receipt;

        $engine->verify($payment, $ocr, $receipt);

        $payment->refresh();
        $notifications->sendVerificationResult($payment);
    }
}
