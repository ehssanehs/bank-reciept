<?php

namespace Tests\Unit;

use App\Services\Ocr\OCRFieldExtractor;
use PHPUnit\Framework\TestCase;

class OCRFieldExtractorTest extends TestCase
{
    public function test_extracts_fields_from_receipt_text(): void
    {
        $text = "پرداخت موفق\nمبلغ: ۵٬۰۰۰٬۰۰۰ ریال\nشماره پیگیری: 845621\nتاریخ: 1405/05/27\nساعت: 14:32";

        $fields = (new OCRFieldExtractor())->extract($text);

        $this->assertSame(5000000, $fields['amount']['value']);
        $this->assertSame(0.9, $fields['amount']['confidence']);
        $this->assertSame('ocr', $fields['amount']['source']);
        $this->assertSame('845621', $fields['tracking_number']['value']);
        $this->assertSame('IRR', $fields['currency']['value']);
        $this->assertNotNull($fields['normalized_timestamp']['value']);
    }

    public function test_returns_empty_for_blank(): void
    {
        $fields = (new OCRFieldExtractor())->extract('');
        $this->assertSame([], $fields);
    }

    public function test_fields_always_have_source_ocr(): void
    {
        $fields = (new OCRFieldExtractor())->extract('مبلغ ۱۰۰۰ ریال');
        foreach ($fields as $field) {
            $this->assertSame('ocr', $field['source']);
        }
    }
}
