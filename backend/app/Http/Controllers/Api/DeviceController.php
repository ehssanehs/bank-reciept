<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Register a new Android device and return its credentials.
     * The API key + secret are shown exactly once (to the registering admin).
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:128', Rule::unique('devices', 'device_id')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $credentials = Device::generateApiKey();

        $device = Device::query()->create([
            'device_id' => $data['device_id'],
            'name' => $data['name'],
            'api_key_hash' => $credentials['api_key_hash'],
            'secret_encrypted' => $credentials['secret_encrypted'],
            'status' => 'active',
            'metadata' => ['registered_by' => $request->user()?->id],
        ]);

        $this->audit->log(AuditEvent::DEVICE_REGISTERED, userId: $request->user()?->id, deviceId: $device->id, metadata: ['device_id' => $data['device_id']]);

        return response()->json([
            'success' => true,
            'device' => $device->only(['id', 'device_id', 'name']),
            // Credentials shown once; store securely on the phone.
            'api_key' => $credentials['api_key'],
            'secret' => $credentials['secret'],
            'warning' => 'Store these credentials securely. The secret is shown only once.',
        ]);
    }
}
