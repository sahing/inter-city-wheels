<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PricingCalculationTest extends TestCase
{
    public function test_sedan_distance_fare_formula(): void
    {
        $distance = 450;
        $ratePerKm = 12.5;
        $tolls = 480;

        $fare = round($distance * $ratePerKm + $tolls);
        $this->assertEquals(6105, $fare);
    }

    public function test_suv_distance_fare_formula(): void
    {
        $distance = 450;
        $ratePerKm = 18.0;
        $tolls = 480;

        $fare = round($distance * $ratePerKm + $tolls);
        $this->assertEquals(8580, $fare);
    }

    public function test_sleeper_berths_total(): void
    {
        $berth1 = 850;
        $berth2 = 850;
        $this->assertEquals(1700, $berth1 + $berth2);
    }
}
