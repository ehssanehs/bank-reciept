<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case CREATED = 'CREATED';
    case AWAITING_RECEIPT = 'AWAITING_RECEIPT';
    case RECEIPT_RECEIVED = 'RECEIPT_RECEIVED';
    case OCR_PROCESSING = 'OCR_PROCESSING';
    case MATCHING = 'MATCHING';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';

    /** Which statuses may transition directly into $target. */
    public function canTransitionTo(self $target): bool
    {
        // Terminal states
        if (in_array($this->value, [self::VERIFIED->value, self::REJECTED->value], true)) {
            // Allow super-admins to reopen (handled in service), but never automatic.
            return false;
        }

        return match ($target) {
            self::AWAITING_RECEIPT => $this === self::CREATED,
            self::RECEIPT_RECEIVED => $this === self::AWAITING_RECEIPT || $this === self::CREATED,
            self::OCR_PROCESSING => $this === self::RECEIPT_RECEIVED,
            self::MATCHING => $this === self::OCR_PROCESSING || $this === self::RECEIPT_RECEIVED,
            self::VERIFIED => $this === self::MATCHING || $this === self::PENDING_REVIEW,
            self::PENDING_REVIEW => in_array($this->value, [
                self::MATCHING->value, self::RECEIPT_RECEIVED->value, self::AWAITING_RECEIPT->value,
            ], true),
            self::REJECTED => in_array($this->value, [
                self::PENDING_REVIEW->value, self::MATCHING->value, self::AWAITING_RECEIPT->value,
            ], true),
            self::EXPIRED => in_array($this->value, [
                self::CREATED->value, self::AWAITING_RECEIPT->value, self::RECEIPT_RECEIVED->value,
                self::OCR_PROCESSING->value, self::MATCHING->value, self::PENDING_REVIEW->value,
            ], true),
            self::CANCELLED => in_array($this->value, [
                self::CREATED->value, self::AWAITING_RECEIPT->value,
            ], true),
            default => false,
        };
    }
}
