<?php

namespace App\Services\Receipt;

use App\Enums\AuditEvent;
use App\Enums\PaymentStatus;
use App\Models\OcrResult;
use App\Models\PaymentReceipt;
use App\Services\Audit\AuditLogger;
use App\Services\Ocr\ImagePreprocessor;
use App\Services\Ocr\OCRProvider;
use App\Services\Payments\PaymentStateMachine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Orchestrates OCR on a stored receipt: preprocess → provider → persist result.
 */
class ReceiptProcessor
{
    public function __construct(
        private readonly OCRProvider $ocr,
        private readonly AuditLogger $audit,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    public function process(PaymentReceipt $receipt): OcrResult
    {
        $payment = $receipt->payment;
        $attempt = $this->nextAttempt($receipt);

        $this->stateMachine->transition($payment, PaymentStatus::OCR_PROCESSING, 'Starting OCR');
        $this->audit->log(AuditEvent::OCR_STARTED, paymentId: $payment->id, metadata: [
            'receipt_id' => $receipt->id, 'attempt' => $attempt,
        ]);

        try {
            $storage = Storage::disk($receipt->disk);
            if (!$storage->exists($receipt->path)) {
                throw new RuntimeException('Receipt file missing on disk.');
            }
            $tmpSource = tempnam(sys_get_temp_dir(), 'rcpt_');
            file_put_contents($tmpSource, $storage->get($receipt->path));

            $workPath = $this->prepareForOcr($tmpSource, $receipt->mime_type);

            $result = $this->ocr->extract($workPath);

            $ocr = OcrResult::query()->create([
                'payment_id' => $payment->id,
                'receipt_id' => $receipt->id,
                'provider' => $result->provider,
                'status' => 'completed',
                'raw_text' => $result->rawText,
                'extracted_fields' => $result->fields,
                'normalized_fields' => $this->normalizeFields($result->fields),
                'confidence' => $result->overallConfidence,
                'attempt' => $attempt,
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            $payment->forceFill(['ocr_confidence' => $result->overallConfidence])->save();

            $this->audit->log(AuditEvent::OCR_COMPLETED, paymentId: $payment->id, metadata: [
                'receipt_id' => $receipt->id, 'confidence' => $result->overallConfidence,
            ]);

            return $ocr;
        } catch (\Throwable $e) {
            Log::warning('OCR failed', ['receipt_id' => $receipt->id, 'error' => $e->getMessage()]);

            $ocr = OcrResult::query()->create([
                'payment_id' => $payment->id,
                'receipt_id' => $receipt->id,
                'provider' => config('services.ocr.provider', 'tesseract'),
                'status' => 'failed',
                'error' => $e->getMessage(),
                'attempt' => $attempt,
                'started_at' => now(),
            ]);

            $this->audit->log(AuditEvent::OCR_FAILED, paymentId: $payment->id, metadata: [
                'receipt_id' => $receipt->id, 'attempt' => $attempt, 'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function prepareForOcr(string $sourcePath, string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            $prep = (new ImagePreprocessor())->process($sourcePath);

            return $prep['out_path'];
        }

        if ($mime === 'application/pdf') {
            return $this->rasterizePdf($sourcePath);
        }

        throw new RuntimeException('Unsupported media for OCR.');
    }

    private function rasterizePdf(string $pdfPath): string
    {
        $outBase = tempnam(sys_get_temp_dir(), 'pdf_');

        $process = new Process(['pdftoppm', '-f', '1', '-l', '1', '-png', '-r', '200', $pdfPath, $outBase]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('PDF rasterization failed: '.$process->getErrorOutput());
        }

        $files = glob($outBase.'-*.png') ?: [];
        if ($files === []) {
            throw new RuntimeException('PDF produced no pages.');
        }

        return $files[0];
    }

    private function nextAttempt(PaymentReceipt $receipt): int
    {
        return (int) OcrResult::query()->where('receipt_id', $receipt->id)->count() + 1;
    }

    private function normalizeFields(array $fields): array
    {
        return $fields;
    }
}
