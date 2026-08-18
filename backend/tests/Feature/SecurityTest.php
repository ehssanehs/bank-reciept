<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Role;
use App\Models\Payment;
use App\Services\Payments\PaymentStateMachine;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndBanks();
        config(['services.ocr.fake_file' => base_path('tests/Fixtures/ocr_fixture.json')]);
    }

    public function test_admin_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/payments')->assertStatus(401);
    }

    public function test_non_admin_cannot_register_device(): void
    {
        $user = $this->makeUser(Role::SUPPORT);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/register-device', ['device_id' => 'x', 'name' => 'x'])
            ->assertStatus(403);
    }

    public function test_read_only_cannot_approve_payment(): void
    {
        $user = $this->makeUser(Role::READ_ONLY);
        $payment = $this->makePendingPayment();

        $this->actingAs($user) // web guard
            ->post(route('admin.payments.approve', $payment), ['reason' => 'trying to escalate'])
            ->assertStatus(403);

        $payment->refresh();
        $this->assertNotSame(PaymentStatus::VERIFIED->value, $payment->status->value);
    }

    public function test_admin_approval_requires_reason(): void
    {
        $admin = $this->makeUser();
        $payment = $this->makePendingPayment();

        $this->actingAs($admin)
            ->from(route('admin.payments.show', $payment))
            ->post(route('admin.payments.approve', $payment), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $payment->refresh();
        $this->assertNotSame(PaymentStatus::VERIFIED->value, $payment->status->value);
    }

    public function test_invalid_receipt_file_rejected(): void
    {
        $admin = $this->makeUser();
        $payment = $this->makePendingPayment();

        // File named .png but containing random (non-image) bytes
        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/payments/'.$payment->upload_token.'/receipt', [
                'receipt' => UploadedFile::fake()->create('receipt.png', 20),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('payment_receipts', 0);
    }

    public function test_oversized_receipt_rejected(): void
    {
        $admin = $this->makeUser();
        $payment = $this->makePendingPayment();

        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/payments/'.$payment->upload_token.'/receipt', [
                'receipt' => UploadedFile::fake()->create('receipt.png', 20 * 1024 * 1024), // 20MB > 10MB
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('payment_receipts', 0);
    }

    public function test_unknown_payment_token_rejected(): void
    {
        $this->post('/api/v1/payments/unknown-token/receipt', [
            'receipt' => UploadedFile::fake()->image('r.png'),
        ])->assertStatus(404);
    }

    private function makePendingPayment(): Payment
    {
        $payment = app(PaymentService::class)->createPayment(['amount' => 5000000]);
        app(PaymentStateMachine::class)->transition($payment, PaymentStatus::PENDING_REVIEW, 'test');

        return $payment;
    }
}
