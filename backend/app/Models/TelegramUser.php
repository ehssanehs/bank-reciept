<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Telegram user identified by the stable numeric chat_id (never a username).
 */
class TelegramUser extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'chat_id';

    protected $keyType = 'int';

    protected $fillable = [
        'chat_id',
        'first_name',
        'last_name',
        'username',
        'language',
        'state',
        'state_payload',
        'linked_customer_id',
        'link_code',
        'link_code_expires_at',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'link_code_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'state_payload' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'linked_customer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'chat_id', 'chat_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'telegram_user_id', 'chat_id');
    }

    public function isLinked(): bool
    {
        return $this->linked_customer_id !== null;
    }
}
