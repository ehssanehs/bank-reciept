<?php

namespace Tests\Feature;

use App\Models\BankSms;
use App\Models\Device;
use App\Services\Auth\DeviceSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class AndroidSmsUploadTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndBanks();
    }

    private function signedRequest(string $deviceId, string $apiKey, string $secret, array $messages, ?int $timestampOffset = 0, ?string $secretOverride = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['messages' => $messages]);
        $ts = (string) (time() + $timestampOffset);
        $secretKey = $secretOverride ?? $secret;
        $signature = DeviceSignature::sign($secretKey, 'POST', '/api/v1/android/sms', $body, $ts);

        $headers = [
            'HTTP_X_DEVICE_ID' => $deviceId,
            'HTTP_X_API_KEY' => $apiKey,
            'HTTP_X_TIMESTAMP' => $ts,
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_NONCE' => 'nonce-'.random_int(1, 999999),
        ];

        return $this->call('POST', '/api/v1/android/sms', [], [], [], $headers, $body);
    }

    public function test_duplicate_sms_is_rejected(): void
    {
        $admin = $this->makeUser();
        $device = $this->registerDevice($admin);

        $messages = [['sender' => 'BANK', 'message' => 'واریز مبلغ ۵٬۰۰۰٬۰۰۰ ریال به شماره پیگیری 845621', 'received_at' => now()->toDateTimeString()]];

        $this->signedRequest($device['device_id'], $device['api_key'], $device['secret'], $messages)->assertOk()->assertJsonPath('data.accepted', 1);
        // Same message → duplicate
        $this->signedRequest($device['device_id'], $device['api_key'], $device['secret'], $messages)->assertOk()->assertJsonPath('data.duplicates', 1);

        $this->assertSame(1, BankSms::query()->count());
    }

    public function test_invalid_signature_rejected(): void
    {
        $admin = $this->makeUser();
        $device = $this->registerDevice($admin);

        $messages = [['sender' => 'BANK', 'message' => 'test', 'received_at' => now()->toDateTimeString()]];

        $this->signedRequest($device['device_id'], $device['api_key'], $device['secret'], $messages, secretOverride: 'wrong-secret')
            ->assertStatus(403);
    }

    public function test_stale_timestamp_rejected(): void
    {
        $admin = $this->makeUser();
        $device = $this->registerDevice($admin);

        $messages = [['sender' => 'BANK', 'message' => 'test', 'received_at' => now()->toDateTimeString()]];

        $this->signedRequest($device['device_id'], $device['api_key'], $device['secret'], $messages, timestampOffset: -100000)
            ->assertStatus(403);
    }

    public function test_unrecognized_sender_is_ignored_not_fatal(): void
    {
        $admin = $this->makeUser();
        $device = $this->registerDevice($admin);

        $messages = [['sender' => 'UNKNOWN-SENDER', 'message' => 'واریز ۱۰۰۰ ریال', 'received_at' => now()->toDateTimeString()]];

        $this->signedRequest($device['device_id'], $device['api_key'], $device['secret'], $messages)
            ->assertOk()
            ->assertJsonPath('data.ignored', 1);

        $this->assertSame(0, BankSms::query()->count());
    }

    public function test_inactive_device_rejected(): void
    {
        $credentials = $this->createDeviceDirectly('inactive-device');
        Device::query()->where('device_id', 'inactive-device')->update(['status' => 'suspended']);

        $messages = [['sender' => 'BANK', 'message' => 'test', 'received_at' => now()->toDateTimeString()]];

        $this->signedRequest('inactive-device', $credentials['api_key'], $credentials['secret'], $messages)
            ->assertStatus(403);
    }
}
