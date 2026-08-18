<?php

namespace App\Services\Normalizers;

/**
 * Normalizes numbers found in Persian/Arabic/English SMS and receipts into a
 * single canonical English form.
 *
 * - Converts Persian digits (۰۱۲۳۴۵۶۷۸۹) and Arabic-Indic digits (٠١٢٣٤٥٦٧٨٩)
 *   to English digits.
 * - Removes thousand separators (`,` , `،` , `٬`).
 * - Treats `٫` (U+066B) and `.` as the decimal separator.
 * - Strips currency symbols and free whitespace.
 */
final class NumberNormalizer
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const EXTENDED_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** Map every supported digit character to its ASCII value. */
    public static function toAsciiDigits(string $input): string
    {
        $map = [];
        foreach (array_merge(self::PERSIAN_DIGITS, self::ARABIC_DIGITS, self::EXTENDED_DIGITS) as $i => $digit) {
            $map[$digit] = (string) ($i % 10);
        }
        return strtr($input, $map);
    }

    /**
     * Keep only digits, optional decimal point and sign. Removes grouping
     * separators (comma, Persian comma, Arabic thousand separator).
     */
    public static function compact(string $input): string
    {
        $ascii = self::toAsciiDigits($input);

        // Replace Persian/Arabic decimal separators with a dot.
        $ascii = str_replace(['٫', '٫', '٫'], '.', $ascii);

        // Remove grouping separators.
        $ascii = str_replace(['،', '٬', ','], '', $ascii);

        // Keep only digits, dots and a leading sign.
        $ascii = preg_replace('/[^0-9.+-]/', '', $ascii) ?? '';

        // Collapse multiple dots (keep the first).
        $ascii = preg_replace('/\.(?=.*\.)/', '', $ascii) ?? '';

        return $ascii;
    }

    /** Canonical integer (drops decimals & sign grouping). */
    public static function toInt(string $input): ?int
    {
        $compact = self::compact($input);
        if ($compact === '' || $compact === '-' || $compact === '+') {
            return null;
        }
        $value = filter_var($compact, FILTER_VALIDATE_INT);
        if ($value === false) {
            // fall back to intval of the cleaned digits
            $value = (int) round((float) $compact);
        }
        return $value;
    }

    /** Canonical float (used for money that may include decimals). */
    public static function toFloat(string $input): ?float
    {
        $compact = self::compact($input);
        if ($compact === '' || $compact === '-' || $compact === '+') {
            return null;
        }
        $value = (float) $compact;
        return is_nan($value) || is_infinite($value) ? null : $value;
    }

    /** Remove every digit-like character leaving only letters/spaces. */
    public static function stripDigits(string $input): string
    {
        return preg_replace('/[0-9۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩]/u', '', $input) ?? '';
    }

    /** Format an integer with Western thousands separators. */
    public static function formatThousands(int $value): string
    {
        return number_format($value, 0, '.', ',');
    }

    /** Format an integer with Persian thousands separators and Persian digits. */
    public static function formatPersian(int $value): string
    {
        $western = number_format($value, 0, '.', ',');
        $reversed = strrev($western);
        $reversed = str_replace(',', '٬', $reversed);
        $withPersian = self::toPersianDigits(strrev($reversed));

        return $withPersian;
    }

    /** Convert English digits to Persian digits (for display). */
    public static function toPersianDigits(int|string $input): string
    {
        return strtr((string) $input, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
