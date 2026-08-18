<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Audit\AuditLogger;
use App\Services\Sms\BankSmsIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AndroidSmsController extends Controller
{
    public function __construct(
        private readonly BankSmsIngestionService $ingestion,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The Android device uploads one or more bank SMS messages.
     * Requests are device-authenticated and HMAC-signed; duplicates are rejected.
     */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:200'],
            'messages.*.local_id' => ['sometimes', 'string', 'max:128'],
            'messages.*.sender' => ['required', 'string', 'max:128'],
            'messages.*.message' => ['required', 'string', 'max:20000'],
            'messages.*.received_at' => ['sometimes', 'date'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get(\App\Services\Auth\DeviceAuthenticator::ATTR_DEVICE);

        $counts = $this->ingestion->ingest($device, $data['messages']);

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get(\App\Services\Auth\DeviceAuthenticator::ATTR_DEVICE);

        return response()->json([
            'success' => true,
            'data' => [
                'server_time' => now()->toIso8601String(),
                'device' => [
                    'id' => $device->device_id,
                    'status' => $device->status,
                    'last_synced_at' => $device->last_synced_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
