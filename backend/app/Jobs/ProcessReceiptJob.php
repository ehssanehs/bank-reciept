<?php

namespace App\Jobs;

use App\Models\PaymentReceipt;
use App\Services\Receipt\ReceiptProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessReceiptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public array $backoffMinutes = [1, 5, 15];

    public function __construct(public readonly string $receiptId) {}

    public function handle(ReceiptProcessor $processor): void
    {
        $receipt = PaymentReceipt::query()->find($this->receiptId);
        if ($receipt === null) {
            return;
        }

        $ocr = $processor->process($receipt);

        if ($ocr->isSuccess()) {
            VerifyPaymentJob::dispatch($receipt->payment_id, $ocr->id);
        }
    }
}
