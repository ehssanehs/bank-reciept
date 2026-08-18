<?php

namespace App\Services\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Records every important event in the `audit_logs` table and a structured
 * JSON line to the dedicated `audit` log channel.
 *
 * Audit log rows are append-only by design: they are not exposed for editing
 * through the admin UI (see Admin controllers & policies).
 */
class AuditLogger
{
    public function log(
        AuditEvent $event,
        ?string $userId = null,
        ?string $paymentId = null,
        ?string $deviceId = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        array $metadata = [],
        ?string $ip = null
    ): AuditLog {
        $log = AuditLog::query()->create([
            'event' => $event->value,
            'user_id' => $userId,
            'payment_id' => $paymentId,
            'device_id' => $deviceId,
            'ip' => $ip ?? request()->ip(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        Log::channel('audit')->info('audit', [
            'event' => $event->value,
            'user_id' => $userId,
            'payment_id' => $paymentId,
            'device_id' => $deviceId,
            'ip' => $ip ?? request()->ip(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'metadata' => $metadata,
            'at' => now()->toIso8601String(),
        ]);

        return $log;
    }
}
