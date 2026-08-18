<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'payment_id',
        'receipt_id',
        'provider',
        'status',
        'raw_text',
        'extracted_fields',
        'confidence',
        'normalized_fields',
        'attempt',
        'started_at',
        'completed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'extracted_fields' => 'array',
            'normalized_fields' => 'array',
            'confidence' => 'float',
            'attempt' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'completed';
    }

    /** Get a normalized field's value if present. */
    public function fieldValue(string $key): mixed
    {
        return $this->normalized_fields[$key]['value'] ?? $this->extracted_fields[$key]['value'] ?? null;
    }
}
