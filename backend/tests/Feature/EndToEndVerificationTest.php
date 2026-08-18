<?php

namespace Tests\Feature;

use App\Models\BankSms;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Services\Auth\DeviceSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

/**
 * Full end-to-end scenario:
 *   SMS arrives → Android uploads → parsed → customer pays → receipt uploaded
 *   → OCR → matching → VERIFIED → audit trail exists.
 */
class EndToEndVerificationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndBanks();
        config(['services.ocr.fake_file' => base_path('tests/Fixtures/ocr_fixture.json')]);
    }

    public function test_full_pipeline_auto_verifies_payment(): void
    {
        $admin = $this->makeUser();
        $device = $this->registerDevice($admin);

        // 1) Android uploads a bank SMS (HMAC-signed)
        $messages = [
            ['local_id' => 'loc-1', 'sender' => 'BANK', 'message' => 'واریز مبلغ ۵٬۰۰۰٬۰۰۰ ریال به شماره پیگیری 845621 در تاریخ 1405/05/27 ساعت 14:32', 'received_at' => now()->toDateTimeString()],
        ];
        $body = json_encode(['messages' => $messages]);
        $headers = $this->deviceHeaders($device['device_id'], $device['api_key'], $device['secret'], 'POST', '/api/v1/android/sms', $body);

        $this->call('POST', '/api/v1/android/sms', [], [], [], $headers, $body)
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        $this->assertDatabaseHas('bank_sms', ['sender' => 'BANK']);
        $sms = BankSms::query()->first();
        $this->assertDatabaseHas('bank_transactions', ['bank_sms_id' => $sms->id]);
        $transaction = BankTransaction::query()->first();
        $this->assertSame('845621', $transaction->tracking_number);
        $this->assertSame(5000000, (int) $transaction->amount);

        // 2) Customer creates a payment
        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/payments', [
            'amount' => 5000000,
            'currency' => 'IRR',
            'order_id' => 'ORD-1',
        ]);
        $create->assertCreated();
        $token = $create->json('data.upload_token');
        $paymentCode = $create->json('data.id');

        // 3) Customer uploads a receipt (public one-time token)
        $this->post('/api/v1/payments/'.$token.'/receipt', [
            'receipt' => UploadedFile::fake()->image('receipt.png'),
        ])->assertOk();

        // 4) Sync queue ran OCR + matching; payment should be VERIFIED
        $payment = Payment::query()->where('code', $paymentCode)->firstOrFail();
        $this->assertSame('VERIFIED', $payment->status->value);
        $this->assertNotNull($payment->matched_transaction_id);
        $this->assertSame($transaction->id, $payment->matched_transaction_id);

        // 5) Transaction is consumed (single-use)
        $transaction->refresh();
        $this->assertSame('USED', $transaction->status->value);
        $this->assertSame($payment->id, $transaction->used_by_payment_id);

        // 6) Audit trail exists
        $this->assertDatabaseHas('audit_logs', ['event' => 'SMS_RECEIVED']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'SMS_PARSED']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'RECEIPT_UPLOADED']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'OCR_COMPLETED']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'MATCH_COMPLETED']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'PAYMENT_VERIFIED']);
    }
}
