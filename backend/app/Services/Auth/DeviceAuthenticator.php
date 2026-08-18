<?php

namespace App\Services\Auth;

use App\Enums\AuditEvent;
use App\Models\Device;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DeviceAuthenticator
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @var string Attribute key under which the resolved device is stored. */
    public const ATTR_DEVICE = 'device';

    /**
     * Authenticate a device from request headers.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function authenticate(Request $request): Device
    {
        $deviceId = (string) $request->header('X-Device-Id', '');
        $apiKey = (string) $request->header('X-API-Key', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = (string) $request->header('X-Signature', '');
        $nonce = (string) $request->header('X-Nonce', '');

        if ($deviceId === '' || $apiKey === '' || $timestamp === '' || $signature === '') {
            $this->audit->log(AuditEvent::DEVICE_AUTH_FAILED, deviceId: $deviceId ?: null, metadata: ['reason' => 'missing_headers']);
            throw new \Illuminate\Auth\Access\AuthorizationException('Missing authentication headers.');
        }

        $device = Device::query()->where('device_id', $deviceId)->first();

        if ($device === null || !$device->isActive()) {
            $this->audit->log(AuditEvent::DEVICE_AUTH_FAILED, deviceId: $deviceId, metadata: ['reason' => 'unknown_or_inactive']);
            throw new \Illuminate\Auth\Access\AuthorizationException('Device not authorized.');
        }

        // Verify API key
        if (!hash_equals($device->api_key_hash, hash('sha256', $apiKey))) {
            $this->audit->log(AuditEvent::DEVICE_AUTH_FAILED, deviceId: $device->id, metadata: ['reason' => 'bad_api_key']);
            throw new \Illuminate\Auth\Access\AuthorizationException('Invalid API key.');
        }

        // Verify timestamp freshness (replay window)
        $now = time();
        $ts = (int) $timestamp;
        if ($ts === 0 || abs($now - $ts) > config('services.devices.replay_ttl', 300)) {
            $this->audit->log(AuditEvent::DEVICE_AUTH_FAILED, deviceId: $device->id, metadata: ['reason' => 'stale_timestamp', 'diff' => $now - $ts]);
            throw new \Illuminate\Auth\Access\AuthorizationException('Request timestamp is outside the allowed window.');
        }

        // Verify HMAC signature
        $secret = decrypt($device->secret_encrypted);
        $ok = DeviceSignature::verify(
            $secret,
            $signature,
            $request->method(),
            $request->getPathInfo(),
            (string) $request->getContent(),
            $timestamp
        );

        if (!$ok) {
            $this->audit->log(AuditEvent::DEVICE_AUTH_FAILED, deviceId: $device->id, metadata: ['reason' => 'bad_signature']);
            throw new \Illuminate\Auth\Access\AuthorizationException('Invalid request signature.');
        }

        // Replay protection via nonce
        if ($nonce === '') {
            $this->audit->log(AuditEvent::DEVICE_AUTH_FAILED, deviceId: $device->id, metadata: ['reason' => 'missing_nonce']);
            throw new \Illuminate\Auth\Access\AuthorizationException('Missing nonce.');
        }
        $nonceKey = 'device_nonce:'.$deviceId.':'.$nonce;
        if (Cache::has($nonceKey)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Replayed request.');
        }
        Cache::put($nonceKey, true, now()->addSeconds((int) config('services.devices.replay_ttl', 300)));

        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ])->save();

        return $device;
    }
}
