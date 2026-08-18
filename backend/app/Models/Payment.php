<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'user_id',
        'telegram_user_id',
        'code',
        'order_id',
        'amount',
        'currency',
        'tracking_number',
        'status',
        'risk_score',
        'ocr_confidence',
        'matched_transaction_id',
        'verification_notes',
        'recommendation',
        'upload_token',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'risk_score' => 'integer',
            'ocr_confidence' => 'float',
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id', 'chat_id');
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'matched_transaction_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(PaymentMatch::class);
    }

    public function ocrResults(): HasMany
    {
        return $this->hasMany(OcrResult::class, 'payment_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'payment_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status->value, [
            PaymentStatus::VERIFIED->value,
            PaymentStatus::REJECTED->value,
            PaymentStatus::EXPIRED->value,
            PaymentStatus::CANCELLED->value,
        ], true);
    }

    public function isVerified(): bool
    {
        return $this->status === PaymentStatus::VERIFIED;
    }

    public static function generateCode(): string
    {
        return 'PMT'.strtoupper(Str::random(6));
    }

    public static function generateUploadToken(): string
    {
        return Str::random(48);
    }
}
