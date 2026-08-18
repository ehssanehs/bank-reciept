<?php

namespace App\Services\Payments;

use App\Enums\AuditEvent;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;

/**
 * Enforces the payment lifecycle. Arbitrary status changes are not allowed;
 * every transition is validated, persisted and audited.
 */
class PaymentStateMachine
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function transition(
        Payment $payment,
        PaymentStatus $target,
        string $note = '',
        ?User $actor = null,
        array $metadata = []
    ): Payment {
        $current = $payment->status ?? PaymentStatus::CREATED;

        if ($current === $target) {
            return $payment;
        }

        if (!$current->canTransitionTo($target)) {
            throw new DomainException(
                "Illegal payment state transition: {$current->value} → {$target->value}"
            );
        }

        $oldValue = $current->value;

        $payment->forceFill(['status' => $target])->save();

        $this->audit->log(
            event: AuditEvent::PAYMENT_STATUS_CHANGED,
            userId: $actor?->id,
            paymentId: $payment->id,
            oldStatus: $oldValue,
            newStatus: $target->value,
            metadata: array_merge(['note' => $note], $metadata)
        );

        return $payment;
    }
}
