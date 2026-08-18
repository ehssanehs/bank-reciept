<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentReceipt extends Model
{
    use HasUuids;

    protected $fillable = [
        'payment_id',
        'upload_token',
        'original_name',
        'stored_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'sha256',
        'perceptual_hash',
        'status',
        'uploaded_via',
        'telegram_user_id',
        'uploaded_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function ocrResults(): HasMany
    {
        return $this->hasMany(OcrResult::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
