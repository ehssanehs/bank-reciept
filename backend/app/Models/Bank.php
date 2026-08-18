<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'currency',
        'sender_patterns',
        'parser_class',
        'amount_pattern',
        'tracking_pattern',
        'date_pattern',
        'time_pattern',
        'transaction_type_rules',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sender_patterns' => 'array',
            'transaction_type_rules' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /** @return array<int,string> */
    public function senderPatterns(): array
    {
        return array_map('mb_strtolower', (array) $this->sender_patterns);
    }
}
