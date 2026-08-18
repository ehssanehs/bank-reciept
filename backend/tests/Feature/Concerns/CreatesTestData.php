<?php

namespace Tests\Feature\Concerns;

use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\DeviceSignature;

trait CreatesTestData
{
    protected function seedRolesAndBanks(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\BankSeeder::class);
    }

    protected function makeUser(string $role = Role::SUPER_ADMIN, string $email = 'a@example.com'): User
    {
        $user = User::factory()->create(['email' => $email, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Register a device via the API and return its credentials.
     *
     * @return array{device:Device,api_key:string,secret:string,device_id:string}
     */
    protected function registerDevice(User $admin, string $deviceId = 'android-phone-1'): array
    {
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/auth/register-device', [
                'device_id' => $deviceId,
                'name' => 'Test Phone',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $device = Device::query()->where('device_id', $deviceId)->firstOrFail();

        return [
            'device' => $device,
            'api_key' => $response->json('api_key'),
            'secret' => $response->json('secret'),
            'device_id' => $deviceId,
        ];
    }

    /**
     * Build server headers signing an HTTP request for device auth.
     *
     * @return array<string,string>
     */
    protected function deviceHeaders(string $deviceId, string $apiKey, string $secret, string $method, string $path, string $body, ?string $nonce = null): array
    {
        $timestamp = (string) time();
        $signature = DeviceSignature::sign($secret, $method, $path, $body, $timestamp);

        return [
            'HTTP_X_DEVICE_ID' => $deviceId,
            'HTTP_X_API_KEY' => $apiKey,
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_X_NONCE' => $nonce ?? 'nonce-'.random_int(1, 999999),
        ];
    }

    /** @return array{device_id:string,api_key:string,secret:string} */
    protected function createDeviceDirectly(string $deviceId = 'direct-device'): array
    {
        $credentials = Device::generateApiKey();

        Device::query()->create([
            'device_id' => $deviceId,
            'name' => 'Direct',
            'api_key_hash' => $credentials['api_key_hash'],
            'secret_encrypted' => $credentials['secret_encrypted'],
            'status' => 'active',
        ]);

        return ['device_id' => $deviceId, 'api_key' => $credentials['api_key'], 'secret' => $credentials['secret']];
    }
}
