<?php

namespace Tests\Unit;

use App\Models\Bank;
use App\Services\Parsers\Banks\DefaultBankParser;
use PHPUnit\Framework\TestCase;

class BankParserTest extends TestCase
{
    private function bank(): Bank
    {
        $bank = new Bank();
        $bank->code = 'EXAMPLE';
        $bank->currency = 'IRR';
        $bank->timezone = 'Asia/Tehran';
        $bank->amount_pattern = null;
        $bank->tracking_pattern = null;

        return $bank;
    }

    public function test_parses_persian_sms(): void
    {
        $sms = 'واریز مبلغ ۵٬۰۰۰٬۰۰۰ ریال از کارت 6037991111111111 به شماره پیگیری 845621 در تاریخ 1405/05/27 ساعت 14:32';

        $parsed = (new DefaultBankParser())->parse($this->bank(), $sms);

        $this->assertSame('credit', $parsed->transactionType);
        $this->assertSame(5000000, $parsed->amount);
        $this->assertSame('IRR', $parsed->currency);
        $this->assertSame('845621', $parsed->trackingNumber);
        $this->assertSame('6037991111111111', $parsed->cardNumber);
        $this->assertSame('1405/05/27', $parsed->originalDate);
        $this->assertSame('14:32', $parsed->originalTime);
        $this->assertNotNull($parsed->normalizedTimestamp);
        $this->assertArrayHasKey('raw_message', $parsed->raw);
    }

    public function test_parses_english_sms(): void
    {
        $sms = 'Deposit 5,000,000 IRR to account 1234567890. Tracking: 998877 at 2024/05/27 10:05';

        $parsed = (new DefaultBankParser())->parse($this->bank(), $sms);

        $this->assertSame('credit', $parsed->transactionType);
        $this->assertSame(5000000, $parsed->amount);
        $this->assertSame('998877', $parsed->trackingNumber);
        $this->assertSame('2024/05/27', $parsed->originalDate);
    }

    public function test_uses_bank_patterns(): void
    {
        $bank = $this->bank();
        $bank->tracking_pattern = '/پیگیری\s*[:：-]?\s*(\d+)/u';

        $sms = 'واریز مبلغ ۱۰۰۰۰۰ ریال، پیگیری: 55555';
        $parsed = (new DefaultBankParser())->parse($bank, $sms);

        $this->assertSame('55555', $parsed->trackingNumber);
        $this->assertSame(100000, $parsed->amount);
    }

    public function test_raw_message_preserved(): void
    {
        $sms = 'پیامک اصلی با اعداد فارسی ۱۲۳';
        $parsed = (new DefaultBankParser())->parse($this->bank(), $sms);

        $this->assertSame($sms, $parsed->raw['raw_message']);
    }
}
