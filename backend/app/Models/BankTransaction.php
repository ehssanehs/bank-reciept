<?php

namespace App\Models;

use App\Enums\BankTransactionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'bank_sms_id',
        'bank_id',
        'device_id',
        'transaction_type',
        'amount',
        'currency',
        'tracking_number',
        'reference_number',
        'card_number',
        'account_number',
        'original_date',
        'original_time',
        'transaction_date',
        'transaction_time',
        'normalized_timestamp',
        'timezone',
        'raw_message',
        'parsed',
        'hash',
        'status',
        'used_by_payment_id',
        'first_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'normalized_timestamp' => 'datetime',
            'first_seen_at' => 'datetime',
            'parsed' => 'array',
            'status' => BankTransactionStatus::class,
        ];
    }

    public function bankSms(): BelongsTo
    {
        return $this->belongsTo(BankSms::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function usedByPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'used_by_payment_id');
    }

    public function paymentMatch(): HasOne
    {
        return $this->hasOne(PaymentMatch::class);
    }

    public function isUsable(): bool
    {
        return $this->status === BankTransactionStatus::AVAILABLE
            && $this->used_by_payment_id === null;
    }

    public static function computeHash(array $fields): string
    {
        return hash('sha256', json_encode($fields));
    }
}
