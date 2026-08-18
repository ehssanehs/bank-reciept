<?php

namespace App\Models;

use App\Enums\MatchType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'payment_id',
        'bank_transaction_id',
        'receipt_id',
        'match_type',
        'score',
        'matched_fields',
        'result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'matched_fields' => 'array',
            'match_type' => MatchType::class,
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }
}
