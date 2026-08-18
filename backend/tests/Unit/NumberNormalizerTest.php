<?php

namespace Tests\Unit;

use App\Services\Normalizers\NumberNormalizer;
use PHPUnit\Framework\TestCase;

class NumberNormalizerTest extends TestCase
{
    public function test_persian_digits_to_ascii(): void
    {
        $this->assertSame('0123456789', NumberNormalizer::toAsciiDigits('۰۱۲۳۴۵۶۷۸۹'));
    }

    public function test_arabic_indic_digits_to_ascii(): void
    {
        $this->assertSame('0123456789', NumberNormalizer::toAsciiDigits('٠١٢٣٤٥٦٧٨٩'));
    }

    public function test_extended_persian_digits(): void
    {
        $this->assertSame('42', NumberNormalizer::toAsciiDigits('۴۲'));
    }

    public function test_compact_removes_thousand_separators(): void
    {
        $this->assertSame('5000000', NumberNormalizer::compact('۵٬۰۰۰٬۰۰۰'));
        $this->assertSame('5000000', NumberNormalizer::compact('5,000,000'));
        $this->assertSame('5000000', NumberNormalizer::compact('5،000،000'));
    }

    public function test_to_int(): void
    {
        $this->assertSame(42, NumberNormalizer::toInt('۴۲'));
        $this->assertSame(5000000, NumberNormalizer::toInt('5,000,000'));
        $this->assertNull(NumberNormalizer::toInt(''));
        $this->assertNull(NumberNormalizer::toInt('abc'));
    }

    public function test_formatting(): void
    {
        $this->assertSame('5,000,000', NumberNormalizer::formatThousands(5000000));
        $this->assertSame('۵۰۰۰۰۰۰', NumberNormalizer::toPersianDigits('5000000'));
    }

    public function test_strip_digits(): void
    {
        $this->assertSame('abc', NumberNormalizer::stripDigits('abc123۴۵'));
    }
}
