<?php

namespace App\Services\Normalizers;

/**
 * Extracts a canonical integer amount and currency code from free-text money
 * expressions such as "۵٬۰۰۰٬۰۰۰ ریال" or "5,000,000 IRR".
 */
final class MoneyNormalizer
{
    /** Currency code → list of Persian/English keywords and symbols. */
    private const CURRENCY_KEYWORDS = [
        'IRR' => ['ریال', 'ر.ت', 'ر﷼', 'ریال ایران', 'rial', 'irr', 'rails', 'tooman', 'toman', 'تومان', 'تومن'],
        'USD' => ['دلار', 'dollar', 'usd', '$'],
        'EUR' => ['یورو', 'euro', 'eur', '€'],
        'GBP' => ['پوند', 'pound', 'gbp', '£'],
        'TRY' => ['لیر', 'lira', 'try', '₺'],
        'AED' => ['درهم', 'dirham', 'aed', 'د.إ'],
        'IQD' => ['دینار عراق', 'dinar', 'iqd'],
    ];

    /** @return int|null canonical integer amount or null if not a number. */
    public function parseAmount(string $text): ?int
    {
        $ascii = NumberNormalizer::toAsciiDigits($text);

        // Handle "5/000/000" style grouping and "5،000،000"
        $ascii = str_replace(['،', '٬', '/'], ',', $ascii);
        $ascii = preg_replace('/,\s+/', ',', $ascii) ?? $ascii;

        // Remove everything that is not a digit, dot, comma or sign.
        $clean = preg_replace('/[^0-9.,+-]/', '', $ascii) ?? '';
        // Trim surrounding separators
        $clean = trim($clean, ',.');

        $compact = NumberNormalizer::compact($clean);

        if ($compact === '' || $compact === '-' || $compact === '+') {
            return null;
        }

        // Amounts are typically integers (rials); discard decimals.
        $float = (float) $compact;
        if (!is_finite($float)) {
            return null;
        }
        // If there is a fractional part, round to nearest integer (money).
        return (int) round($float);
    }

    /** @return string|null canonical ISO currency code, or null. */
    public function detectCurrency(string $text): ?string
    {
        $haystack = mb_strtolower(trim($text), 'UTF-8');

        foreach (self::CURRENCY_KEYWORDS as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($haystack, mb_strtolower($keyword, 'UTF-8')) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    /** Convenience: parse "amount currency" out of a full line. */
    public function parse(string $text): ?array
    {
        $amount = $this->parseAmount($text);
        if ($amount === null) {
            return null;
        }

        return [
            'amount' => $amount,
            'currency' => $this->detectCurrency($text) ?? 'IRR',
            'raw' => $text,
        ];
    }
}
