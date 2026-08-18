<?php

namespace App\Services\Parsers;

/**
 * Immutable structured output of a bank-SMS parser.
 */
final class SmsParsedData
{
    public function __construct(
        public readonly ?string $bankCode = null,
        public readonly ?string $transactionType = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $cardNumber = null,
        public readonly ?string $accountNumber = null,
        public readonly ?string $originalDate = null,
        public readonly ?string $originalTime = null,
        public readonly ?string $normalizedTimestamp = null,
        public readonly ?string $timezone = null,
        public readonly ?int $balance = null,
        public readonly array $raw = [],
        public readonly array $confidence = [],
    ) {}

    public function toArray(): array
    {
        return [
            'bank_code' => $this->bankCode,
            'transaction_type' => $this->transactionType,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tracking_number' => $this->trackingNumber,
            'reference_number' => $this->referenceNumber,
            'card_number' => $this->cardNumber,
            'account_number' => $this->accountNumber,
            'original_date' => $this->originalDate,
            'original_time' => $this->originalTime,
            'normalized_timestamp' => $this->normalizedTimestamp,
            'timezone' => $this->timezone,
            'balance' => $this->balance,
            'raw' => $this->raw,
            'confidence' => $this->confidence,
        ];
    }
}
