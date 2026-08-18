<?php

namespace Tests\Unit;

use App\Services\Normalizers\DateTimeNormalizer;
use PHPUnit\Framework\TestCase;

class DateTimeNormalizerTest extends TestCase
{
    public function test_normalizes_jalali_date_and_time(): void
    {
        $result = (new DateTimeNormalizer())->normalize('1405/05/27', '14:32');

        $this->assertNotNull($result);
        $this->assertSame('1405/05/27', $result['original_date']);
        $this->assertSame('14:32', $result['original_time']);
        $this->assertSame('Asia/Tehran', $result['timezone']);
        $this->assertNotNull($result['normalized_timestamp']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $result['normalized_timestamp']);
    }

    public function test_normalizes_gregorian_date(): void
    {
        $result = (new DateTimeNormalizer())->normalize('2024/05/27', '10:05');
        $this->assertNotNull($result);
        $this->assertSame('2024-05-27', $result['gregorian_date']);
    }

    public function test_persian_digits_in_date(): void
    {
        $result = (new DateTimeNormalizer())->normalize('۱۴۰۵/۰۵/۲۷', '۱۴:۳۲');
        $this->assertNotNull($result);
        $this->assertSame('1405/05/27', $result['original_date']);
    }

    public function test_returns_null_for_garbage(): void
    {
        $this->assertNull((new DateTimeNormalizer())->normalize('hello world'));
        $this->assertNull((new DateTimeNormalizer())->normalize(''));
    }
}
