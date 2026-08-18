<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Central configuration service. Business rules are stored in the
 * `system_settings` table (admin-editable, cached) and fall back to
 * environment-driven defaults from config/services.php.
 */
class SettingsService
{
    private ?Collection $cache = null;

    /** @var array<int,string> keys that may never be exposed to the UI. */
    private const SENSITIVE_KEYS = [
        'telegram.bot_token',
        'telegram.webhook_secret',
        'android.api_key',
        'ocr.api_key',
        'mail.password',
    ];

    public function all(): Collection
    {
        return $this->cache ??= Cache::remember('system_settings.all', 300, function () {
            return SystemSetting::query()->orderBy('key')->get();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->all()->firstWhere('key', $key);
        if ($setting === null) {
            return $default;
        }
        if ($setting->is_encrypted) {
            return decrypt($setting->value);
        }

        return $this->decode($setting->value);
    }

    public function set(string $key, mixed $value, ?string $group = null, bool $encrypt = false): void
    {
        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypt ? encrypt($this->encode($value)) : $this->encode($value),
                'group' => $group ?? $this->groupOf($key),
                'is_encrypted' => $encrypt || in_array($key, self::SENSITIVE_KEYS, true),
            ]
        );

        $this->cache = null;
        Cache::forget('system_settings.all');
    }

    public function delete(string $key): void
    {
        SystemSetting::query()->where('key', $key)->delete();
        $this->cache = null;
        Cache::forget('system_settings.all');
    }

    // ---- Typed business-rule getters (DB first, config fallback) ----

    public function timeToleranceMinutes(): int
    {
        return (int) $this->get('payment.match_time_tolerance_minutes', config('services.matching.time_tolerance_minutes', 15));
    }

    public function ocrConfidenceThreshold(): float
    {
        return (float) $this->get('ocr.confidence_threshold', config('services.ocr.confidence_threshold', 0.75));
    }

    public function autoApprovalEnabled(): bool
    {
        return (bool) $this->get('payment.auto_approval_enabled', config('services.matching.auto_approval_enabled', true));
    }

    public function autoApproveConfidenceThreshold(): float
    {
        return (float) $this->get('payment.auto_approve_confidence_threshold', config('services.matching.auto_approve_confidence_threshold', 0.75));
    }

    public function maxReceiptSizeMb(): int
    {
        return (int) $this->get('receipt.max_size_mb', config('services.receipts.max_size_mb', 10));
    }

    public function riskReviewThreshold(): int
    {
        return (int) $this->get('fraud.risk_auto_review_threshold', config('services.fraud.risk_auto_review_threshold', 50));
    }

    public function verificationWindowHours(): int
    {
        return (int) $this->get('fraud.verification_window_hours', config('services.fraud.verification_window_hours', 72));
    }

    public function isSensitive(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS, true);
    }

    private function groupOf(string $key): string
    {
        return explode('.', $key)[0] ?? 'general';
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function decode(string $value): mixed
    {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
