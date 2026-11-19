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

    public function test_roundtrip_discount_calculation(): void
    {
        $baseOneWay = 2000;
        $roundtripTotal = $baseOneWay * 2;
        $discountPct = 10.0; // 10% discount on return

        $discountedTotal = round($roundtripTotal * (1 - ($discountPct / 100)));
        $this->assertEquals(3600, $discountedTotal);
    }

    public function test_percentage_coupon_with_cap(): void
    {
        $grossFare = 6000;
        $couponRate = 15; // 15% FIRSTWHEELS
        $maxCap = 500;

        $calculatedDiscount = ($grossFare * $couponRate) / 100; // 900
        $appliedDiscount = min($calculatedDiscount, $maxCap); // capped at 500

        $this->assertEquals(500, $appliedDiscount);
        $this->assertEquals(5500, $grossFare - $appliedDiscount);
    }

    public function test_flat_coupon_discount(): void
    {
        $grossFare = 1500;
        $flatDiscount = 100; // INTERCITY100

        $this->assertEquals(1400, $grossFare - $flatDiscount);
    }

    public function test_addons_itemized_sum(): void
    {
        $petFriendly = 350;
        $nightAllowance = 400;
        $luggageCarrier = 200;

        $totalAddons = $petFriendly + $nightAllowance + $luggageCarrier;
        $this->assertEquals(950, $totalAddons);
    }

    public function test_local_hourly_package_fare(): void
    {
        $includedKm = 80;
        $multiplier = 1.7;
        $ratePerKm = 18.0; // SUV
        $expectedFare = round($includedKm * $multiplier * $ratePerKm);

        $this->assertEquals(2448, $expectedFare);
    }

    public function test_airport_surcharges_addition(): void
    {
        $baseFare = 950;
        $pickupSurcharge = 250;
        $airportToll = 120;

        $totalAirportFare = $baseFare + $pickupSurcharge + $airportToll;
        $this->assertEquals(1320, $totalAirportFare);
    }
}
