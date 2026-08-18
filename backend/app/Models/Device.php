<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_id',
        'name',
        'api_key_hash',
        'secret_encrypted',
        'status',
        'last_synced_at',
        'last_seen_at',
        'last_ip',
        'app_version',
        'metadata',
    ];

    protected $hidden = ['api_key_hash', 'secret_encrypted'];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function bankSms(): HasMany
    {
        return $this->hasMany(BankSms::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function generateApiKey(): array
    {
        $apiKey = 'bpk_'.Str::random(32);
        $secret = Str::random(48);

        return [
            'api_key' => $apiKey,
            'secret' => $secret,
            'api_key_hash' => hash('sha256', $apiKey),
            // Encrypted so the server can verify HMACs without storing the
            // secret in plaintext.
            'secret_encrypted' => encrypt($secret),
        ];
    }
}
