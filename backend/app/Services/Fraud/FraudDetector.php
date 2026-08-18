<?php

namespace App\Services\Fraud;

use App\Enums\AuditEvent;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\Audit\AuditLogger;
use App\Services\Ocr\ImageHasher;
use Carbon\Carbon;

/**
 * Computes a configurable risk score (0–100) and flags suspicious activity.
 *
 * Risk score is advisory: it feeds the recommendation shown to reviewers and
 * can force PENDING_REVIEW when it exceeds the configured threshold, but the
 * final authority is always the trusted bank transaction + matching rules.
 */
class FraudDetector
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{score:int,flags:array<int,string>,reasons:array<int,string>}
     */
    public function assess(
        Payment $payment,
        array $ocrFields,
        ?PaymentReceipt $receipt,
        ?BankTransaction $candidate = null,
        float $ocrConfidence = 0.0
    ): array {
        $score = 0;
        $flags = [];
        $reasons = [];

        $tracking = $this->val($ocrFields, 'tracking_number');
        $amount = $this->val($ocrFields, 'amount');
        $timestamp = $this->val($ocrFields, 'normalized_timestamp');

        // 1) Tracking number already consumed by another verified payment
        if ($tracking !== null) {
            $used = BankTransaction::query()
                ->where('tracking_number', $tracking)
                ->whereNotNull('used_by_payment_id')
                ->where('used_by_payment_id', '!=', $payment->id)
                ->exists();
            if ($used) {
                $score += 45;
                $flags[] = 'duplicate_tracking_number';
                $reasons[] = 'Tracking number already used by another payment.';
            }
        }

        // 2) Same receipt file (sha256) uploaded before
        if ($receipt !== null) {
            $sameSha = PaymentReceipt::query()
                ->where('sha256', $receipt->sha256)
                ->where('id', '!=', $receipt->id)
                ->exists();
            if ($sameSha) {
                $score += 30;
                $flags[] = 'duplicate_receipt_hash';
                $reasons[] = 'Identical receipt file already submitted.';
            }

            // 3) Perceptually similar receipt
            if ($receipt->perceptual_hash !== null && $receipt->perceptual_hash !== '') {
                $similar = PaymentReceipt::query()
                    ->whereNotNull('perceptual_hash')
                    ->where('id', '!=', $receipt->id)
                    ->get()
                    ->contains(fn (PaymentReceipt $r) => $r->perceptual_hash !== ''
                        && ImageHasher::hammingDistance($receipt->perceptual_hash, $r->perceptual_hash) <= 8);
                if ($similar) {
                    $score += 20;
                    $flags[] = 'similar_receipt';
                    $reasons[] = 'Perceptually similar receipt found in history.';
                }
            }
        }

        // 4) Amount mismatch vs trusted transaction
        if ($candidate !== null && $amount !== null && (int) $candidate->amount !== (int) $amount) {
            $score += 30;
            $flags[] = 'amount_mismatch';
            $reasons[] = 'Receipt amount differs from bank transaction amount.';
        }

        // 5) Tracking mismatch
        if ($candidate !== null && $tracking !== null
            && $candidate->tracking_number !== null
            && strtoupper((string) $candidate->tracking_number) !== strtoupper((string) $tracking)) {
            $score += 25;
            $flags[] = 'tracking_mismatch';
            $reasons[] = 'Tracking number does not match the bank transaction.';
        }

        // 6) Bank mismatch
        $bank = $this->val($ocrFields, 'bank');
        if ($candidate !== null && $bank !== null && $candidate->bank_id
            && $candidate->bank?->code && strtolower($bank) !== strtolower($candidate->bank->code)) {
            $score += 20;
            $flags[] = 'bank_mismatch';
            $reasons[] = 'Bank indicated on receipt differs from SMS bank.';
        }

        // 7) Future transaction date
        $futureTolerance = (int) config('services.fraud.future_tolerance_minutes', 30);
        if ($timestamp !== null) {
            $ts = Carbon::parse($timestamp);
            if ($ts->isFuture() && $ts->gt(now()->addMinutes($futureTolerance))) {
                $score += 25;
                $flags[] = 'future_transaction_date';
                $reasons[] = 'Transaction timestamp is in the future.';
            }
        }

        // 8) Transaction older than verification window
        $windowHours = (int) config('services.fraud.verification_window_hours', 72);
        if ($candidate?->normalized_timestamp && $candidate->normalized_timestamp->lt(now()->subHours($windowHours))) {
            $score += 20;
            $flags[] = 'old_transaction';
            $reasons[] = 'Transaction is older than the configured verification window.';
        }

        // 9) Excessive receipts for one payment
        $maxPerPayment = (int) config('services.fraud.max_receipts_per_payment', 5);
        $receiptCount = (int) PaymentReceipt::query()->where('payment_id', $payment->id)->count();
        if ($receiptCount > $maxPerPayment) {
            $score += 15;
            $flags[] = 'excessive_receipts';
            $reasons[] = 'More than the allowed number of receipts submitted.';
        }

        // 10) OCR confidence too low
        $threshold = config('services.ocr.confidence_threshold', 0.75);
        if ($ocrConfidence > 0 && $ocrConfidence < $threshold) {
            $score += 20;
            $flags[] = 'low_ocr_confidence';
            $reasons[] = 'OCR confidence is below the configured threshold.';
        }

        $score = min(100, $score);

        if ($score > 0) {
            $this->audit->log(AuditEvent::FRAUD_FLAG, paymentId: $payment->id, metadata: [
                'score' => $score, 'flags' => $flags,
            ]);
        }

        return ['score' => $score, 'flags' => $flags, 'reasons' => $reasons];
    }

    private function val(array $fields, string $key): mixed
    {
        return $fields[$key]['value'] ?? null;
    }
}
