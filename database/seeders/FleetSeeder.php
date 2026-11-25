<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\DriverVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Populates a realistic regional fleet: 100 partner drivers and 500 cars, each
 * car carrying an amenity list from {@see DriverVehicle::FEATURES}.
 *
 * The data is generated deterministically (no Faker, fixed rotation) and every
 * row is keyed by its unique phone / plate, so running the seeder twice tops the
 * fleet up instead of duplicating it. Rows are bulk-inserted to keep the suite
 * fast: seeding is part of DatabaseSeeder and therefore of every feature test.
 */
class FleetSeeder extends Seeder
{
    public const DRIVER_COUNT = 100;

    public const VEHICLE_COUNT = 500;

    private const FIRST_NAMES = [
        'Ramesh', 'Ajay', 'Vikram', 'Suniel', 'Manoj', 'Sandeep', 'Rakesh', 'Imran',
        'Prakash', 'Deepak', 'Suresh', 'Arjun', 'Harish', 'Naveen', 'Kiran', 'Ravi',
        'Amit', 'Sanjay', 'Rohit', 'Anil', 'Gopal', 'Bharat', 'Dinesh', 'Farhan',
        'Jagdish', 'Karthik', 'Lalit', 'Mahesh', 'Nitin', 'Omkar', 'Pankaj', 'Rajat',
        'Satish', 'Tarun', 'Umesh', 'Vinod', 'Yash', 'Zubair', 'Ashok', 'Bhavesh',
    ];

    private const LAST_NAMES = [
        'Patil', 'Solanki', 'Deshmukh', 'Jadhav', 'Kumar', 'Sharma', 'Singh', 'Khan',
        'Ghosh', 'Banerjee', 'Das', 'Roy', 'Reddy', 'Rao', 'Nair', 'Menon',
        'Iyer', 'Shetty', 'Naik', 'Pawar', 'Shinde', 'Chavan', 'Yadav', 'Verma',
        'Gupta', 'Mehta', 'Shah', 'Joshi', 'Kulkarni', 'Bose', 'Mandal', 'Sarkar',
    ];

    private const AVATARS = ['👨🏽‍✈️', '🧔🏻‍♂️', '👨🏻‍🦱', '👨🏾‍🦲', '🧑🏽‍✈️', '👨🏼‍🦳', '🧔🏽‍♂️', '👩🏽‍✈️'];

    /** Weighted fleet-class mix: 30 sedans, 18 prime, 24 SUV, 13 luxury, 15 sleeper vans. */
    private const CATEGORY_POOL = [
        'sedan', 'sedan', 'sedan', 'sedan', 'sedan', 'sedan',
        'prime', 'prime', 'prime', 'prime',
        'suv', 'suv', 'suv', 'suv', 'suv',
        'luxury', 'luxury', 'luxury',
        'sleeper_van', 'sleeper_van', 'sleeper_van',
    ];

    private const CATEGORY_MODELS = [
        'sedan' => ['Maruti Dzire', 'Honda Amaze', 'Hyundai Aura', 'Tata Tigor', 'Toyota Etios'],
        'prime' => ['Honda City', 'Hyundai Verna', 'Maruti Ciaz', 'Skoda Slavia', 'Volkswagen Virtus'],
        'suv' => ['Maruti Ertiga', 'Kia Carens', 'Toyota Innova Crysta', 'Mahindra Scorpio N', 'Maruti XL6'],
        'luxury' => ['Toyota Fortuner', 'Mercedes-Benz E-Class', 'BMW 5 Series', 'Audi A6', 'Volvo S90'],
        'sleeper_van' => ['Force Urbania', 'Force Traveller Sleeper', 'Tata Winger', 'Mahindra Supro'],
    ];

    private const CATEGORY_SEATS = [
        'sedan' => [4, 5],
        'prime' => [4, 5],
        'suv' => [6, 7],
        'luxury' => [4, 7],
        'sleeper_van' => [9, 12],
    ];

    /** Amenities that always come with the class, before the rotating extras. */
    private const CATEGORY_FEATURES = [
        'sedan' => ['USB Charging', 'Music System'],
        'prime' => ['USB Charging', 'Music System', 'First Aid Kit'],
        'suv' => ['Extra Luggage Space', 'Roof Carrier', 'Child Seat'],
        'luxury' => ['Leather Seats', 'Sunroof', 'Wi-Fi Hotspot', 'Dash Cam'],
        'sleeper_van' => ['Reading Lights', 'Blankets & Pillows', 'Cooler Box'],
    ];

    private const REGIONS = [
        ['MH', '12'], ['MH', '14'], ['KA', '01'], ['KA', '05'],
        ['WB', '02'], ['WB', '24'], ['DL', '01'], ['DL', '08'],
        ['TS', '09'], ['GJ', '01'], ['RJ', '14'], ['UP', '16'],
    ];

    private const FUEL_CYCLE = ['Diesel', 'Diesel', 'Petrol', 'CNG', 'Diesel', 'EV', 'Petrol'];

    public function run(): void
    {
        $now = Carbon::now();
        $drivers = $this->driverRows($now);

        $existingPhones = Driver::whereIn('phone', array_column($drivers, 'phone'))
            ->pluck('phone')
            ->all();

        $newDrivers = array_values(array_filter(
            $drivers,
            fn (array $driver) => ! in_array($driver['phone'], $existingPhones, true)
        ));

        foreach (array_chunk($newDrivers, 100) as $chunk) {
            Driver::insert($chunk);
        }

        $driverIds = Driver::whereIn('phone', array_column($drivers, 'phone'))->pluck('id', 'phone');

        $vehicles = $this->vehicleRows($now, $drivers, $driverIds);

        $existingPlates = DriverVehicle::whereIn('vehicle_plate', array_column($vehicles, 'vehicle_plate'))
            ->pluck('vehicle_plate')
            ->all();

        $newVehicles = array_values(array_filter(
            $vehicles,
            fn (array $vehicle) => ! in_array($vehicle['vehicle_plate'], $existingPlates, true)
        ));

        foreach (array_chunk($newVehicles, 100) as $chunk) {
            DriverVehicle::insert($chunk);
        }

        $this->command?->info(sprintf(
            'FleetSeeder: %d new drivers (of %d) and %d new cars (of %d).',
            count($newDrivers),
            self::DRIVER_COUNT,
            count($newVehicles),
            self::VEHICLE_COUNT
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function driverRows(Carbon $now): array
    {
        $rows = [];

        $perDriver = intdiv(self::VEHICLE_COUNT, self::DRIVER_COUNT);

        for ($i = 0; $i < self::DRIVER_COUNT; $i++) {
            // The driver's legacy "my vehicle" fields mirror their first car, so
            // the driver roster and the fleet table never disagree about a car.
            $primary = $i * $perDriver;
            $category = $this->categoryFor($primary);
            $model = self::CATEGORY_MODELS[$category][$primary % count(self::CATEGORY_MODELS[$category])];

            $rows[] = [
                'name' => self::FIRST_NAMES[$i % count(self::FIRST_NAMES)]
                    .' '.self::LAST_NAMES[($i * 7) % count(self::LAST_NAMES)],
                'phone' => sprintf('+91 90000 %05d', 10000 + $i),
                'avatar' => self::AVATARS[$i % count(self::AVATARS)],
                'vehicle_model' => $model.' ('.($primary % 2 === 0 ? 'White' : 'Silver').')',
                'vehicle_plate' => $this->plateFor($primary),
                'fleet_category' => $category,
                'rating' => round(4.20 + (($i * 37) % 81) / 100, 2),
                'trips_count' => 35 + (($i * 137) % 1800),
                'wallet_balance' => 250 + (($i * 977) % 28000),
                'is_verified' => $i % 23 !== 0,
                'is_online' => $i % 3 !== 0,
                'created_at' => $now->copy()->subDays($i % 180),
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Five cars per driver, so every partner has a small garage of their own.
     *
     * @param  array<int, array<string, mixed>>  $drivers
     * @param  Collection<string, int>  $driverIds
     * @return array<int, array<string, mixed>>
     */
    private function vehicleRows(Carbon $now, array $drivers, $driverIds): array
    {
        $rows = [];
        $perDriver = intdiv(self::VEHICLE_COUNT, self::DRIVER_COUNT);

        for ($v = 0; $v < self::VEHICLE_COUNT; $v++) {
            $driver = $drivers[intdiv($v, $perDriver) % self::DRIVER_COUNT];
            $category = $this->categoryFor($v);
            $models = self::CATEGORY_MODELS[$category];
            [$minSeats, $maxSeats] = self::CATEGORY_SEATS[$category];
            $status = $this->statusFor($v);

            $rows[] = [
                'driver_id' => $driverIds[$driver['phone']] ?? null,
                'user_id' => null,
                'driver_name' => $driver['name'],
                'driver_phone' => $driver['phone'],
                'vehicle_model' => $models[$v % count($models)].' ('.($v % 2 === 0 ? 'White' : 'Silver').')',
                'vehicle_plate' => $this->plateFor($v),
                'fleet_category' => $category,
                'seating_capacity' => $minSeats + ($v % ($maxSeats - $minSeats + 1)),
                'berth_capacity' => $category === 'sleeper_van' ? 4 + ($v % 5) : 0,
                'coach_code' => null,
                'fuel_type' => self::FUEL_CYCLE[$v % count(self::FUEL_CYCLE)],
                'model_year' => (string) (2019 + ($v % 7)),
                'rc_number' => 'RC-'.$this->regionFor($v)[0].$this->regionFor($v)[1].'-'.(700000 + $v * 37),
                'features' => json_encode($this->featuresFor($category, $v)),
                'status' => $status,
                'admin_notes' => $this->notesFor($status),
                'is_active' => $status === 'Approved',
                'created_at' => $now->copy()->subDays($v % 180),
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function categoryFor(int $index): string
    {
        return self::CATEGORY_POOL[$index % count(self::CATEGORY_POOL)];
    }

    /**
     * Base class amenities plus two rotating extras, capped at the catalogue.
     *
     * @return array<int, string>
     */
    private function featuresFor(string $category, int $index): array
    {
        $catalogue = DriverVehicle::FEATURES;
        $features = array_merge(['Air Conditioning'], self::CATEGORY_FEATURES[$category] ?? []);

        for ($pick = 0; $pick < 2; $pick++) {
            $features[] = $catalogue[($index * 3 + $pick * 7 + strlen($category)) % count($catalogue)];
        }

        return array_values(array_unique(array_intersect($features, $catalogue)));
    }

    private function statusFor(int $index): string
    {
        return match ($index % 20) {
            0 => 'Pending',
            7 => 'Rejected',
            default => 'Approved',
        };
    }

    private function notesFor(string $status): string
    {
        return match ($status) {
            'Pending' => 'Documents uploaded; awaiting physical inspection and FASTag verification.',
            'Rejected' => 'Rejected: vehicle fitness certificate expired. Re-submit after renewal.',
            default => 'Commercial permit, insurance and FASTag verified.',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function regionFor(int $index): array
    {
        return self::REGIONS[$index % count(self::REGIONS)];
    }

    private function plateFor(int $index): string
    {
        [$state, $rto] = $this->regionFor($index);

        return sprintf('%s %s IC %04d', $state, $rto, 1000 + $index);
    }
}
