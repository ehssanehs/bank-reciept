<?php

namespace App\Services\Ocr;

use App\Services\Normalizers\DateTimeNormalizer;
use App\Services\Normalizers\NumberNormalizer;

/**
 * Converts raw OCR text into structured fields with per-field confidence.
 *
 * Output is evidence only — the matching engine independently compares these
 * values against trusted bank transaction data.
 */
final class OCRFieldExtractor
{
    private const AMOUNT_LABELS = ['مبلغ', 'amount', 'value', 'total', 'مابه‌التفاوت', 'قابل پرداخت', 'پرداخت'];

    private const TRACKING_LABELS = [
        'شماره پیگیری', 'کد پیگیری', 'کد رهگیری', 'شماره رهگیری', 'tracking', 'reference',
        'ref', 'کد پیام', 'کد پذیرنده', 'شماره سند', 'سند',
    ];

    private const RECEIVER_LABELS = ['گیرنده', 'receiver', 'به نام', 'دریافت', 'مقصد'];

    private const SENDER_LABELS = ['فرستنده', 'sender', 'از', 'حساب مبدا'];

    private const MERCHANT_LABELS = ['پذیرنده', 'merchant', 'فروشگاه', 'terminal', 'پایانه'];

    /**
     * @return array<string,array{value:mixed,confidence:float,source:string}>
     */
    public function extract(string $rawText): array
    {
        $norm = NumberNormalizer::toAsciiDigits($rawText);
        $lower = mb_strtolower($norm, 'UTF-8');

        $fields = [];

        $this->put($fields, 'amount', $this->extractAmount($norm, $lower));
        $this->put($fields, 'currency', $this->extractCurrency($lower));
        $this->put($fields, 'tracking_number', $this->extractAfterLabel($norm, $lower, self::TRACKING_LABELS, 4, 16, 0.85));
        $this->put($fields, 'reference_number', $this->extractAfterLabel($norm, $lower, ['شماره مرجع', 'مرجع', 'reference no', 'ref no'], 4, 20, 0.7));
        $this->put($fields, 'card_number', $this->extractCard($norm));
        $this->put($fields, 'account_number', $this->extractAfterLabel($norm, $lower, ['شماره حساب', 'account', 'شبا', 'iban', 'شماره شبا'], 6, 26, 0.7));
        $this->put($fields, 'receiver', $this->extractTextAfterLabel($norm, $lower, self::RECEIVER_LABELS, 0.6));
        $this->put($fields, 'sender', $this->extractTextAfterLabel($norm, $lower, self::SENDER_LABELS, 0.55));
        $this->put($fields, 'merchant', $this->extractTextAfterLabel($norm, $lower, self::MERCHANT_LABELS, 0.6));

        [$date, $time] = $this->extractDateTime($norm);
        $dt = (new DateTimeNormalizer())->normalize($date, $time);
        if ($date !== null) {
            $this->put($fields, 'transaction_date', $date, 0.8);
        }
        if ($time !== null) {
            $this->put($fields, 'transaction_time', $time, 0.8);
        }
        if ($dt !== null) {
            $this->put($fields, 'normalized_timestamp', $dt['normalized_timestamp'], 0.75);
        }

        return $fields;
    }

    private function extractAmount(string $norm, string $lower): array|null
    {
        // nearest number to currency keyword
        $pos = mb_strpos($lower, 'ریال') !== false ? mb_strpos($norm, 'ریال')
            : (mb_stripos($lower, 'rial') !== false ? mb_stripos($norm, 'rial') : false);
        if ($pos !== false) {
            $start = max(0, $pos - 40);
            if (preg_match('/(\d[\d\s,٬]*)\s*$/', mb_substr($norm, $start, $pos - $start), $mm) === 1) {
                $v = NumberNormalizer::toInt($mm[1]);
                if ($v !== null) {
                    return ['value' => $v, 'confidence' => 0.9];
                }
            }
        }

        foreach (self::AMOUNT_LABELS as $label) {
            $pos = mb_stripos($norm, $label);
            if ($pos !== false) {
                $segment = mb_substr($norm, $pos, 60);
                if (preg_match('/\d[\d\s,٬]*/', $segment, $mm) === 1) {
                    $v = NumberNormalizer::toInt($mm[0]);
                    if ($v !== null) {
                        return ['value' => $v, 'confidence' => 0.85];
                    }
                }
            }
        }

        return null;
    }

    private function extractCurrency(string $lower): array|null
    {
        foreach ([
            'IRR' => ['ریال', 'rial', 'toman', 'تومان'],
            'USD' => ['دلار', 'dollar', 'usd'],
            'EUR' => ['یورو', 'euro'],
            'AED' => ['درهم', 'dirham'],
        ] as $code => $keywords) {
            foreach ($keywords as $k) {
                if (str_contains($lower, $k)) {
                    return ['value' => $code, 'confidence' => 0.9];
                }
            }
        }

        return null;
    }

    private function extractAfterLabel(string $norm, string $lower, array $labels, int $minLen, int $maxLen, float $conf): array|null
    {
        foreach ($labels as $label) {
            $pos = mb_stripos($lower, $label);
            if ($pos === false) {
                continue;
            }
            $segment = mb_substr($norm, $pos + mb_strlen($label), 60);
            if (preg_match('/\s*[:：-]?\s*([A-Za-z0-9]{'.$minLen.','.$maxLen.'})/', $segment, $mm) === 1) {
                return ['value' => $mm[1], 'confidence' => $conf];
            }
        }

        return null;
    }

    private function extractTextAfterLabel(string $norm, string $lower, array $labels, float $conf): array|null
    {
        foreach ($labels as $label) {
            $pos = mb_stripos($lower, $label);
            if ($pos === false) {
                continue;
            }
            $segment = mb_substr($norm, $pos + mb_strlen($label), 40);
            if (preg_match('/\s*[:：-]?\s*([\p{L}\p{N}][\p{L}\p{N}\s۰-۹٠-٩]{2,30})/u', $segment, $mm) === 1) {
                return ['value' => trim($mm[1]), 'confidence' => $conf];
            }
        }

        return null;
    }

    private function extractCard(string $norm): array|null
    {
        if (preg_match('/\b(\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4})\b/', $norm, $mm) === 1) {
            return ['value' => preg_replace('/[\s-]/', '', $mm[1]), 'confidence' => 0.85];
        }

        return null;
    }

    /** @return array{0:?string,1:?string} */
    private function extractDateTime(string $norm): array
    {
        $date = null;
        if (preg_match('/\b(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})\b/', $norm, $mm) === 1) {
            $date = $mm[1];
        } elseif (preg_match('/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})\b/', $norm, $mm) === 1) {
            $date = $mm[1];
        }

        $time = null;
        if (preg_match('/\b(\d{1,2}:\d{2}(:\d{2})?)\b/', $norm, $mm) === 1) {
            $time = $mm[1];
        }

        return [$date, $time];
    }

    private function put(array &$fields, string $key, ?array $value): void
    {
        if ($value === null) {
            return;
        }
        $fields[$key] = [
            'value' => $value['value'],
            'confidence' => $value['confidence'],
            'source' => 'ocr',
        ];
    }
}
