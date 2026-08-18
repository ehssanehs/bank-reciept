<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\BankSms;
use App\Models\BankTransaction;
use App\Models\Device;
use App\Models\OcrResult;
use App\Models\Payment;
use App\Services\Matching\PaymentMatchingEngine;
use App\Services\Payments\PaymentStateMachine;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

/**
 * Ensures one bank transaction can never be auto-assigned to two payments.
 */
class DuplicateTransactionTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndBanks();
    }

    public function test_two_payments_cannot_claim_same_transaction(): void
    {
        $this->makeTransaction();
        $engine = app(PaymentMatchingEngine::class);
        $state = app(PaymentStateMachine::class);
        $payments = app(PaymentService::class);

        $fields = [
            'amount' => ['value' => 5000000, 'confidence' => 0.99, 'source' => 'ocr'],
            'tracking_number' => ['value' => '845621', 'confidence' => 0.99, 'source' => 'ocr'],
        ];

        $p1 = $payments->createPayment(['amount' => 5000000]);
        $state->transition($p1, PaymentStatus::RECEIPT_RECEIVED, 't');
        $engine->verify($p1, $this->ocrFor($p1, $fields));

        $p2 = $payments->createPayment(['amount' => 5000000]);
        $state->transition($p2, PaymentStatus::RECEIPT_RECEIVED, 't');
        $engine->verify($p2, $this->ocrFor($p2, $fields));

        $p1->refresh();
        $p2->refresh();

        $this->assertSame(PaymentStatus::VERIFIED->value, $p1->status->value);
        $this->assertSame(PaymentStatus::PENDING_REVIEW->value, $p2->status->value);
        $this->assertNotSame($p1->matched_transaction_id, $p2->matched_transaction_id);
    }

    private function makeTransaction(): void
    {
        $bank = \App\Models\Bank::query()->where('code', 'EXAMPLE')->firstOrFail();
        $device = $this->createDeviceDirectly('dup-device');
        $deviceId = Device::query()->where('device_id', 'dup-device')->firstOrFail()->id;

        $sms = BankSms::query()->create([
            'device_id' => $deviceId,
            'bank_id' => $bank->id,
            'sender' => 'BANK',
            'message_body' => 'واریز ۵٬۰۰۰٬۰۰۰ ریال',
            'sms_hash' => BankSms::computeHash('dup-device', 'BANK', 'y', now()->toDateTimeString()),
            'status' => 'parsed',
        ]);

        BankTransaction::query()->create([
            'bank_sms_id' => $sms->id,
            'bank_id' => $bank->id,
            'device_id' => $deviceId,
            'transaction_type' => 'credit',
            'amount' => 5000000,
            'currency' => 'IRR',
            'tracking_number' => '845621',
            'normalized_timestamp' => now(),
            'status' => \App\Enums\BankTransactionStatus::AVAILABLE,
        ]);
    }

    private function ocrFor(Payment $payment, array $fields): OcrResult
    {
        return OcrResult::query()->create([
            'payment_id' => $payment->id,
            'provider' => 'fake',
            'status' => 'completed',
            'raw_text' => 'x',
            'extracted_fields' => $fields,
            'normalized_fields' => $fields,
            'confidence' => 0.99,
        ]);
    }
}
