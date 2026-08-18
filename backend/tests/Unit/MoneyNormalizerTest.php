<?php

namespace Tests\Unit;

use App\Services\Normalizers\MoneyNormalizer;
use PHPUnit\Framework\TestCase;

class MoneyNormalizerTest extends TestCase
{
    public function test_parses_persian_rial_amount(): void
    {
        $normalizer = new MoneyNormalizer();
        $this->assertSame(5000000, $normalizer->parseAmount('۵٬۰۰۰٬۰۰۰ ریال'));
    }

    public function test_parses_english_rial_amount(): void
    {
        $normalizer = new MoneyNormalizer();
        $this->assertSame(5000000, $normalizer->parseAmount('5,000,000 IRR'));
    }

    public function test_detects_currency(): void
    {
        $normalizer = new MoneyNormalizer();
        $this->assertSame('IRR', $normalizer->detectCurrency('۵٬۰۰۰٬۰۰۰ ریال'));
        $this->assertSame('USD', $normalizer->detectCurrency('1,000 dollars'));
        $this->assertSame('EUR', $normalizer->detectCurrency('۵۰۰ یورو'));
    }

    public function test_parse_full(): void
    {
        $normalizer = new MoneyNormalizer();
        $this->assertSame([
            'amount' => 5000000,
            'currency' => 'IRR',
            'raw' => '۵٬۰۰۰٬۰۰۰ ریال',
        ], $normalizer->parse('۵٬۰۰۰٬۰۰۰ ریال'));
    }

    public function test_returns_null_for_non_number(): void
    {
        $normalizer = new MoneyNormalizer();
        $this->assertNull($normalizer->parseAmount('هیچ مبلغی'));
    }
}
