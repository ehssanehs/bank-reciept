<?php

namespace App\Services\Normalizers;

use App\Support\Converters\Jalali;
use Carbon\Carbon;

/**
 * Normalizes dates/times found in bank SMS and receipts.
 *
 * Supports Persian (Jalali) and Gregorian calendars, Persian/Arabic/English
 * digits and multiple separator styles. Output is a single canonical UTC
 * timestamp while preserving the original local date/time and timezone so that
 * no information is lost and Persian vs. Gregorian strings are never compared
 * directly as plain text.
 */
final class DateTimeNormalizer
{
    public const DEFAULT_TIMEZONE = 'Asia/Tehran';

    /**
     * @return array{
     *     original_date: ?string,
     *     original_time: ?string,
     *     timezone: string,
     *     gregorian_date: ?string,
     *     gregorian_time: ?string,
     *     normalized_timestamp: ?string,
     * }|null
     */
    public function normalize(?string $date, ?string $time = null, ?string $timezone = null): ?array
    {
        $date = trim((string) NumberNormalizer::toAsciiDigits((string) $date));
        if ($date === '') {
            return null;
        }

        $time = trim((string) NumberNormalizer::toAsciiDigits((string) $time));
        $tz = $timezone ?: self::DEFAULT_TIMEZONE;

        $parts = $this->extractDateParts($date);
        if ($parts === null) {
            return null;
        }
        [$year, $month, $day, $calendar] = $parts;

        [$h, $min, $sec] = $this->parseTime($time);

        if ($calendar === 'jalali') {
            try {
                [$gy, $gm, $gd] = Jalali::toGregorian($year, $month, $day);
            } catch (\InvalidArgumentException) {
                return null;
            }
        } else {
            [$gy, $gm, $gd] = [$year, $month, $day];
        }

        try {
            $dt = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $h, $min, $sec),
                $tz
            );
        } catch (\Throwable) {
            return null;
        }

        return [
            'original_date' => $date,
            'original_time' => $time === '' ? null : $time,
            'timezone' => $tz,
            'gregorian_date' => $dt->format('Y-m-d'),
            'gregorian_time' => $dt->format('H:i:s'),
            'normalized_timestamp' => $dt->copy()->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{0:int,1:int,2:int}|null  [y, m, d]
     */
    private function extractDateParts(string $date): ?array
    {
        // Compact 8-digit: YYYYMMDD
        if (preg_match('/^\d{8}$/', $date) === 1) {
            return $this->withCalendar(
                (int) substr($date, 0, 4),
                (int) substr($date, 4, 2),
                (int) substr($date, 6, 2)
            );
        }

        $clean = preg_replace('/[-_.\/\\\\]/', '/', $date) ?? $date;
        $clean = preg_replace('/\s+/', '/', $clean) ?? $clean;
        $parts = array_values(array_filter(explode('/', $clean), fn ($p) => $p !== ''));

        if (count($parts) === 3) {
            $a = (int) $parts[0];
            $b = (int) $parts[1];
            $c = (int) $parts[2];

            // Year-first (1405/05/27 or 2024/05/27)
            if (($a >= 1000 && $a < 1600) || $a >= 1900) {
                return $this->withCalendar($a, $b, $c);
            }
            // Day-first Gregorian (27/05/2024)
            if ($c >= 1900) {
                return $this->withCalendar($c, $a, $b);
            }
        }

        return null;
    }

    /**
     * @return array{0:int,1:int,2:int,3:string}|null
     */
    private function withCalendar(int $y, int $m, int $d): ?array
    {
        if ($m < 1 || $m > 12 || $d < 1 || $d > 31) {
            return null;
        }
        $calendar = ($y < 1600) ? 'jalali' : 'gregorian';

        return [$y, $m, $d, $calendar];
    }

    /**
     * @return array{0:int,1:int,2:int} [h, min, sec]
     */
    private function parseTime(string $time): array
    {
        if ($time === '') {
            return [0, 0, 0];
        }
        $t = preg_split('/[:.\s]/', $time) ?: [];
        $h = isset($t[0]) ? (int) $t[0] : 0;
        $min = isset($t[1]) ? (int) $t[1] : 0;
        $sec = isset($t[2]) ? (int) $t[2] : 0;

        if ($h < 0 || $h > 23) {
            $h = 0;
        }
        if ($min < 0 || $min > 59) {
            $min = 0;
        }
        if ($sec < 0 || $sec > 59) {
            $sec = 0;
        }

        return [$h, $min, $sec];
    }
}
