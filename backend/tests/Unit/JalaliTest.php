<?php

namespace Tests\Unit;

use App\Support\Converters\Jalali;
use PHPUnit\Framework\TestCase;

class JalaliTest extends TestCase
{
    public function test_known_conversion(): void
    {
        // 1400/01/01 (Farvardin 1, 1400) == 2021-03-21
        $this->assertSame([2021, 3, 21], Jalali::toGregorian(1400, 1, 1));
        $this->assertSame([1400, 1, 1], Jalali::toJalali(2021, 3, 21));
    }

    public function test_round_trip(): void
    {
        foreach ([[1405, 5, 27], [1398, 12, 29], [1370, 6, 1]] as $jalali) {
            [$jy, $jm, $jd] = $jalali;
            $gregorian = Jalali::toGregorian($jy, $jm, $jd);
            $this->assertSame([$jy, $jm, $jd], Jalali::toJalali($gregorian[0], $gregorian[1], $gregorian[2]));
        }
    }

    public function test_invalid_month_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Jalali::toGregorian(1400, 13, 1);
    }
}
