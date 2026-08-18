<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'chat_id',
        'message_id',
        'text',
        'media_type',
        'media_file_id',
        'media_path',
        'caption',
        'command',
        'payload',
        'status',
        'reply_after',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'chat_id', 'chat_id');
    }
}
