<?php

namespace Tests\Feature;

use App\Enums\BankTransactionStatus;
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

class PaymentFlowTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndBanks();
    }

    private function makeTransaction(array $overrides = []): BankTransaction
    {
        $bank = \App\Models\Bank::query()->where('code', 'EXAMPLE')->firstOrFail();
        $device = $this->createDeviceDirectly('tx-device');

        $sms = BankSms::query()->create([
            'device_id' => Device::query()->where('device_id', 'tx-device')->firstOrFail()->id,
            'bank_id' => $bank->id,
            'sender' => 'BANK',
            'message_body' => 'واریز مبلغ ۵٬۰۰۰٬۰۰۰ ریال به شماره پیگیری 845621',
            'sms_hash' => BankSms::computeHash('tx-device', 'BANK', 'x', now()->toDateTimeString()),
            'status' => 'parsed',
        ]);

        return BankTransaction::query()->create(array_merge([
            'bank_sms_id' => $sms->id,
            'bank_id' => $bank->id,
            'device_id' => Device::query()->where('device_id', 'tx-device')->firstOrFail()->id,
            'transaction_type' => 'credit',
            'amount' => 5000000,
            'currency' => 'IRR',
            'tracking_number' => '845621',
            'normalized_timestamp' => now(),
            'status' => BankTransactionStatus::AVAILABLE,
        ], $overrides));
    }

    private function makePaymentReady(): Payment
    {
        $state = app(PaymentStateMachine::class);
        $payment = app(PaymentService::class)->createPayment(['amount' => 5000000, 'currency' => 'IRR']);
        $state->transition($payment, PaymentStatus::RECEIPT_RECEIVED, 'test');

        return $payment;
    }

    private function ocrFor(Payment $payment, array $fields, float $confidence = 0.99): OcrResult
    {
        return OcrResult::query()->create([
            'payment_id' => $payment->id,
            'receipt_id' => null,
            'provider' => 'fake',
            'status' => 'completed',
            'raw_text' => 'fixture',
            'extracted_fields' => $fields,
            'normalized_fields' => $fields,
            'confidence' => $confidence,
        ]);
    }

    public function test_strong_match_auto_verifies(): void
    {
        $this->makeTransaction();
        $payment = $this->makePaymentReady();
        $ocr = $this->ocrFor($payment, [
            'amount' => ['value' => 5000000, 'confidence' => 0.99, 'source' => 'ocr'],
            'tracking_number' => ['value' => '845621', 'confidence' => 0.99, 'source' => 'ocr'],
        ]);

        app(PaymentMatchingEngine::class)->verify($payment, $ocr);

        $payment->refresh();
        $this->assertSame(PaymentStatus::VERIFIED->value, $payment->status->value);
        $this->assertNotNull($payment->matched_transaction_id);
    }

    public function test_weak_amount_only_match_goes_to_review(): void
    {
        $tx = $this->makeTransaction();
        $payment = $this->makePaymentReady();
        // Different tracking (no tracking match), only amount matches → weak
        $ocr = $this->ocrFor($payment, [
            'amount' => ['value' => 5000000, 'confidence' => 0.99, 'source' => 'ocr'],
            'tracking_number' => ['value' => '999999', 'confidence' => 0.99, 'source' => 'ocr'],
        ]);

        app(PaymentMatchingEngine::class)->verify($payment, $ocr);

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING_REVIEW->value, $payment->status->value);
        $this->assertNull($payment->matched_transaction_id);

        // Transaction not consumed
        $tx->refresh();
        $this->assertSame(BankTransactionStatus::AVAILABLE->value, $tx->status->value);
    }

    public function test_no_candidate_goes_to_review(): void
    {
        $payment = $this->makePaymentReady();
        $ocr = $this->ocrFor($payment, [
            'amount' => ['value' => 12345, 'confidence' => 0.99, 'source' => 'ocr'],
            'tracking_number' => ['value' => 'NOPE', 'confidence' => 0.99, 'source' => 'ocr'],
        ]);

        app(PaymentMatchingEngine::class)->verify($payment, $ocr);

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING_REVIEW->value, $payment->status->value);
    }

    public function test_low_ocr_confidence_blocks_auto_approval(): void
    {
        $this->makeTransaction();
        $payment = $this->makePaymentReady();
        $ocr = $this->ocrFor($payment, [
            'amount' => ['value' => 5000000, 'confidence' => 0.5, 'source' => 'ocr'],
            'tracking_number' => ['value' => '845621', 'confidence' => 0.5, 'source' => 'ocr'],
        ], 0.5);

        app(PaymentMatchingEngine::class)->verify($payment, $ocr);

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING_REVIEW->value, $payment->status->value);
    }
}
