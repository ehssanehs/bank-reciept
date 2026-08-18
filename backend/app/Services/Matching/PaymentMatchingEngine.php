<?php

namespace App\Services\Matching;

use App\Enums\AuditEvent;
use App\Enums\BankTransactionStatus;
use App\Enums\MatchType;
use App\Enums\PaymentStatus;
use App\Models\BankTransaction;
use App\Models\OcrResult;
use App\Models\Payment;
use App\Models\PaymentMatch;
use App\Models\PaymentReceipt;
use App\Services\Audit\AuditLogger;
use App\Services\Fraud\FraudDetector;
use App\Services\Payments\PaymentStateMachine;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The payment matching engine.
 *
 * Compares OCR evidence against trusted bank transactions. Auto-approval only
 * happens when ALL configured conditions pass and the bank transaction is
 * atomically reserved (row lock + unique constraint) so that one transaction
 * can never be assigned to two payments concurrently.
 */
class PaymentMatchingEngine
{
    public function __construct(
        private readonly PaymentStateMachine $stateMachine,
        private readonly FraudDetector $fraud,
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     match_type: string,
     *     score: float,
     *     risk_score: int,
     *     recommendation: string,
     *     matched_transaction_id: ?string,
     *     flags: array<int,string>
     * }
     */
    public function verify(Payment $payment, OcrResult $ocr, ?PaymentReceipt $receipt = null): array
    {
        $this->stateMachine->transition($payment, PaymentStatus::MATCHING, 'Matching against bank transactions');
        $this->audit->log(AuditEvent::MATCH_STARTED, paymentId: $payment->id, metadata: ['receipt_id' => $receipt?->id]);

        $fields = $ocr->normalized_fields ?: $ocr->extracted_fields;
        $toleranceMin = $this->settings->timeToleranceMinutes();

        $candidates = $this->findCandidates($fields, $toleranceMin);

        /** @var BankTransaction|null $best */
        $best = null;
        $bestType = MatchType::NONE;
        $score = 0.0;
        $matchedFields = [];

        foreach ($candidates as $candidate) {
            [$type, $fieldsMatched, $matchedScore] = $this->evaluate($fields, $candidate, $toleranceMin);
            if ($type->value === MatchType::STRONG->value || $type->value === MatchType::ALTERNATIVE_STRONG->value) {
                $best = $candidate;
                $bestType = $type;
                $score = $matchedScore;
                $matchedFields = $fieldsMatched;
                break;
            }
            // remember the best weak match
            if ($type === MatchType::WEAK && $best === null) {
                $best = $candidate;
                $bestType = $type;
                $score = $matchedScore;
                $matchedFields = $fieldsMatched;
            }
        }

        $risk = $this->fraud->assess($payment, $fields, $receipt, $best, $ocr->confidence);

        $this->persistMatch($payment, $ocr, $receipt, $best, $bestType, $score, $matchedFields, $risk, $fields);

        $status = $this->decide($payment, $best, $bestType, $risk, $ocr);

        $recommendation = $bestType->value === MatchType::WEAK->value
            ? 'MANUAL REVIEW'
            : ($status === PaymentStatus::VERIFIED->value ? 'AUTO APPROVE' : 'MANUAL REVIEW');

        $this->audit->log(AuditEvent::MATCH_COMPLETED, paymentId: $payment->id, metadata: [
            'match_type' => $bestType->value,
            'candidate' => $best?->id,
            'status' => $status->value,
            'risk_score' => $risk['score'],
        ]);

        return [
            'status' => $status->value,
            'match_type' => $bestType->value,
            'score' => round($score, 2),
            'risk_score' => $risk['score'],
            'recommendation' => $recommendation,
            'matched_transaction_id' => $best?->id,
            'flags' => $risk['flags'],
        ];
    }

    private function decide(Payment $payment, ?BankTransaction $best, MatchType $bestType, array $risk, OcrResult $ocr): PaymentStatus
    {
        $confThreshold = $this->settings->autoApproveConfidenceThreshold();
        $riskThreshold = $this->settings->riskReviewThreshold();

        $strong = $bestType === MatchType::STRONG || $bestType === MatchType::ALTERNATIVE_STRONG;
        $confOk = $ocr->confidence >= $confThreshold;
        $riskOk = $risk['score'] < $riskThreshold;
        $auto = $this->settings->autoApprovalEnabled();

        if ($best !== null && $strong && $confOk && $riskOk && $auto) {
            $reserved = $this->reserveTransaction($payment, $best);
            if (!$reserved) {
                return $this->sendToReview($payment, 'Transaction was already reserved by another payment.');
            }

            $payment->forceFill([
                'matched_transaction_id' => $best->id,
                'risk_score' => $risk['score'],
                'recommendation' => 'AUTO APPROVE',
                'verification_notes' => 'Matched via '.$bestType->value.' rule and atomically reserved.',
            ])->save();

            $this->stateMachine->transition($payment, PaymentStatus::VERIFIED, 'Automatically verified via strong match');
            $this->audit->log(AuditEvent::PAYMENT_VERIFIED, paymentId: $payment->id,
                newStatus: PaymentStatus::VERIFIED->value, metadata: ['transaction_id' => $best->id]);

            return PaymentStatus::VERIFIED;
        }

        return $this->sendToReview($payment, $this->reviewReason($bestType, $confOk, $riskOk, $auto, $best !== null));
    }

    private function sendToReview(Payment $payment, string $reason): PaymentStatus
    {
        $payment->forceFill([
            'recommendation' => 'MANUAL REVIEW',
            'verification_notes' => $reason,
        ])->save();

        try {
            $this->stateMachine->transition($payment, PaymentStatus::PENDING_REVIEW, $reason);
        } catch (\DomainException $e) {
            // e.g. already terminal — leave as-is
        }

        $this->audit->log(AuditEvent::PAYMENT_SENT_TO_REVIEW, paymentId: $payment->id,
            newStatus: PaymentStatus::PENDING_REVIEW->value, metadata: ['reason' => $reason]);

        return PaymentStatus::PENDING_REVIEW;
    }

    private function reviewReason(MatchType $type, bool $confOk, bool $riskOk, bool $auto, bool $hasCandidate): string
    {
        if (!$auto) {
            return 'Automatic approval is disabled by configuration.';
        }
        if (!$hasCandidate) {
            return 'No matching trusted bank transaction found.';
        }
        if ($type === MatchType::WEAK) {
            return 'Only a weak (amount-only) match was found; requires manual review.';
        }
        if (!$confOk) {
            return 'OCR confidence is below the configured threshold.';
        }
        if (!$riskOk) {
            return 'Risk score exceeds the configured review threshold.';
        }

        return 'Pending manual review.';
    }

    /**
     * Atomically reserve a bank transaction for a single payment.
     *
     * Row-level lock + the unique index on used_by_payment_id guarantee that
     * two concurrent verifications cannot both claim the same transaction.
     */
    private function reserveTransaction(Payment $payment, BankTransaction $tx): bool
    {
        return DB::transaction(function () use ($payment, $tx) {
            $locked = BankTransaction::query()->lockForUpdate()->find($tx->id);
            if ($locked === null) {
                return false;
            }

            if ($locked->status !== BankTransactionStatus::AVAILABLE || $locked->used_by_payment_id !== null) {
                return false;
            }

            $locked->forceFill([
                'status' => BankTransactionStatus::USED,
                'used_by_payment_id' => $payment->id,
            ])->save();

            return true;
        });
    }

    /**
     * @return Collection<int,BankTransaction>
     */
    private function findCandidates(array $fields, int $toleranceMin): Collection
    {
        $tracking = $fields['tracking_number']['value'] ?? null;
        $amount = isset($fields['amount']['value']) ? (int) $fields['amount']['value'] : null;
        $ts = $fields['normalized_timestamp']['value'] ?? null;

        $base = fn () => BankTransaction::query()
            ->where('status', BankTransactionStatus::AVAILABLE)
            ->whereNull('used_by_payment_id');

        if ($tracking !== null && $tracking !== '') {
            $byTracking = $base()->where('tracking_number', $tracking)->orderBy('normalized_timestamp', 'desc')->limit(5)->get();
            if ($byTracking->isNotEmpty()) {
                return $byTracking;
            }
        }

        return $this->queryByAmountTime($amount, $ts, $toleranceMin, $base);
    }

    private function queryByAmountTime(?int $amount, mixed $ts, int $toleranceMin, \Closure $base): Collection
    {
        $q = $base();

        if ($amount !== null) {
            $q->where('amount', $amount);
        }

        if ($ts !== null && $toleranceMin > 0) {
            $d = \Carbon\Carbon::parse($ts);
            $q->whereBetween('normalized_timestamp', [
                $d->copy()->subMinutes($toleranceMin),
                $d->copy()->addMinutes($toleranceMin),
            ]);
        }

        return $q->orderBy('normalized_timestamp', 'desc')->limit(10)->get();
    }

    /**
     * @return array{0:MatchType,1:array<int,string>,2:float}
     */
    private function evaluate(array $fields, BankTransaction $candidate, int $toleranceMin): array
    {
        $matched = [];

        $ocrTracking = $fields['tracking_number']['value'] ?? null;
        $ocrAmount = isset($fields['amount']['value']) ? (int) $fields['amount']['value'] : null;
        $ocrTs = $fields['normalized_timestamp']['value'] ?? null;

        $trackingEq = $ocrTracking !== null
            && $candidate->tracking_number !== null
            && strtoupper((string) $ocrTracking) === strtoupper((string) $candidate->tracking_number);

        $amountEq = $ocrAmount !== null
            && $candidate->amount !== null
            && (int) $candidate->amount === $ocrAmount;

        // Bank consistency: if the receipt names a bank, it must match.
        $ocrBank = $fields['bank']['value'] ?? $fields['currency']['value'] ?? null;
        $bankOk = true;
        if ($ocrBank !== null && $candidate->bank_id && $candidate->bank) {
            $bankOk = strtolower((string) $ocrBank) === strtolower((string) $candidate->bank->code);
        }

        $timeOk = true;
        if ($ocrTs !== null && $candidate->normalized_timestamp !== null && $toleranceMin > 0) {
            $diff = abs(\Carbon\Carbon::parse($ocrTs)->getTimestamp() - $candidate->normalized_timestamp->getTimestamp());
            $timeOk = $diff <= ($toleranceMin * 60);
        }

        $typeOk = true;
        if ($candidate->transaction_type !== null && in_array(strtolower($candidate->transaction_type), ['credit', 'deposit'], true)) {
            // credit deposits are the receivable case
            $typeOk = true;
        }

        $score = 0.0;
        if ($trackingEq) {
            $matched[] = 'tracking_number';
            $score += 0.6;
        }
        if ($amountEq) {
            $matched[] = 'amount';
            $score += 0.4;
        }

        if ($trackingEq && $amountEq && $bankOk && $timeOk && $typeOk) {
            return [MatchType::STRONG, $matched, $score];
        }

        // Alternative strong: tracking missing/absent, amount + time + identifiers
        if ($ocrTracking === null && $amountEq && $timeOk && $bankOk) {
            return [MatchType::ALTERNATIVE_STRONG, $matched, $score];
        }

        if ($amountEq && !$trackingEq) {
            return [MatchType::WEAK, $matched, $score];
        }

        return [MatchType::NONE, $matched, 0.0];
    }

    private function persistMatch(
        Payment $payment,
        OcrResult $ocr,
        ?PaymentReceipt $receipt,
        ?BankTransaction $best,
        MatchType $type,
        float $score,
        array $matchedFields,
        array $risk,
        array $fields
    ): void {
        PaymentMatch::query()->create([
            'payment_id' => $payment->id,
            'bank_transaction_id' => $best?->id,
            'receipt_id' => $receipt?->id,
            'match_type' => $type->value,
            'score' => $score,
            'matched_fields' => $matchedFields,
            'result' => $type === MatchType::NONE ? 'no_match' : 'candidate',
            'notes' => json_encode([
                'risk_score' => $risk['score'],
                'ocr_confidence' => $ocr->confidence,
                'extracted' => array_map(fn ($f) => $f['value'] ?? null, $fields),
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
