<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankSms extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_id',
        'bank_id',
        'local_id',
        'sender',
        'message_body',
        'received_at',
        'sms_hash',
        'status',
        'attempts',
        'last_error',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankTransaction(): HasOne
    {
        return $this->hasOne(BankTransaction::class);
    }

    public static function computeHash(string $deviceId, string $sender, string $body, ?string $receivedAt): string
    {
        return hash('sha256', $deviceId.'|'.$sender.'|'.$body.'|'.$receivedAt);
    }
}
