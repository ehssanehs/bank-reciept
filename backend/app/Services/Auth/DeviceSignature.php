<?php

namespace App\Services\Auth;

/**
 * HMAC-SHA256 request signing for Android devices.
 *
 * Canonical string signed by the device:
 *     METHOD\nPATH\nRAW_BODY\nX-Timestamp
 * keyed with the per-device secret.
 */
final class DeviceSignature
{
    public static function sign(string $secret, string $method, string $path, string $body, string $timestamp): string
    {
        $canonical = implode("\n", [strtoupper($method), $path, $body, $timestamp]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function verify(string $secret, string $signature, string $method, string $path, string $body, string $timestamp): bool
    {
        $expected = self::sign($secret, $method, $path, $body, $timestamp);

        return hash_equals($expected, $signature);
    }
}
