<?php

namespace App\Support\Converters;

use InvalidArgumentException;

/**
 * Jalali (Persian/Solar Hijri) calendar conversion.
 *
 * Based on the widely-used, public-domain "Jalali Date Function" (jdf) algorithm
 * by Roozbeh Pournader and others, re-implemented in modern typed PHP.
 *
 * Supports the standard Persian calendar used in Iran.
 */
final class Jalali
{
    /**
     * Convert a Gregorian (y, m, d) to a Jalali [jy, jm, jd].
     *
     * @return array{0:int,1:int,2:int}
     */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $gDm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        if ($gm < 1 || $gm > 12) {
            throw new InvalidArgumentException("Invalid Gregorian month: $gm");
        }
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400)
            + $gd + $gDm[$gm - 1];

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * Convert a Jalali (jy, jm, jd) to a Gregorian [gy, gm, gd].
     *
     * @return array{0:int,1:int,2:int}
     */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            throw new InvalidArgumentException("Invalid Jalali date: $jy/$jm/$jd");
        }

        $gy = $jy + 1595;
        $days = -355668 + (365 * $gy) + (intdiv($gy, 33) * 8)
            + intdiv((($gy % 33) + 3), 4)
            + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $salA = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28,
            31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        for ($gm = 0; $gm < 13 && $gd > $salA[$gm]; $gm++) {
            $gd -= $salA[$gm];
        }

        return [$gy, $gm, $gd];
    }
}
