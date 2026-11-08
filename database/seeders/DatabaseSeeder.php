<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RouteFare;
use App\Models\Driver;
use App\Models\SeatLayout;
use App\Models\DynamicFormField;
use App\Models\WorkflowStage;
use App\Models\Coupon;
use App\Models\Booking;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Intercity Routes
        $routes = [
            ['from_city' => 'Pune', 'to_city' => 'Goa', 'from_lat' => 18.5204, 'from_lng' => 73.8567, 'to_lat' => 15.4909, 'to_lng' => 73.8278, 'distance_km' => 450, 'estimated_time' => '9h 15m', 'base_toll' => 480],
            ['from_city' => 'Goa', 'to_city' => 'Pune', 'from_lat' => 15.4909, 'from_lng' => 73.8278, 'to_lat' => 18.5204, 'to_lng' => 73.8567, 'distance_km' => 450, 'estimated_time' => '9h 15m', 'base_toll' => 480],
            ['from_city' => 'Mumbai', 'to_city' => 'Pune', 'from_lat' => 19.0760, 'from_lng' => 72.8777, 'to_lat' => 18.5204, 'to_lng' => 73.8567, 'distance_km' => 152, 'estimated_time' => '2h 45m', 'base_toll' => 320],
            ['from_city' => 'Pune', 'to_city' => 'Mumbai', 'from_lat' => 18.5204, 'from_lng' => 73.8567, 'to_lat' => 19.0760, 'to_lng' => 72.8777, 'distance_km' => 152, 'estimated_time' => '2h 45m', 'base_toll' => 320],
            ['from_city' => 'Mumbai', 'to_city' => 'Goa', 'from_lat' => 19.0760, 'from_lng' => 72.8777, 'to_lat' => 15.4909, 'to_lng' => 73.8278, 'distance_km' => 590, 'estimated_time' => '11h 20m', 'base_toll' => 650],
            ['from_city' => 'Bengaluru', 'to_city' => 'Hyderabad', 'from_lat' => 12.9716, 'from_lng' => 77.5946, 'to_lat' => 17.3850, 'to_lng' => 78.4867, 'distance_km' => 570, 'estimated_time' => '9h 00m', 'base_toll' => 620],
            ['from_city' => 'Bengaluru', 'to_city' => 'Chennai', 'from_lat' => 12.9716, 'from_lng' => 77.5946, 'to_lat' => 13.0827, 'to_lng' => 80.2707, 'distance_km' => 346, 'estimated_time' => '6h 15m', 'base_toll' => 390],
            ['from_city' => 'Delhi', 'to_city' => 'Jaipur', 'from_lat' => 28.6139, 'from_lng' => 77.2090, 'to_lat' => 26.9124, 'to_lng' => 75.7873, 'distance_km' => 280, 'estimated_time' => '4h 45m', 'base_toll' => 340],
        ];
        foreach ($routes as $r) {
            RouteFare::create($r);
        }

        // 2. Seed Verified Drivers
        $drivers = [
            ['name' => 'Ramesh Patil', 'phone' => '+91 98220 11920', 'avatar' => '👨🏽‍✈️', 'vehicle_model' => 'Toyota Innova Crysta (White)', 'vehicle_plate' => 'MH 12 QX 4920', 'fleet_category' => 'suv', 'rating' => 4.90, 'trips_count' => 840, 'wallet_balance' => 14500.00],
            ['name' => 'Ajay Solanki', 'phone' => '+91 98221 44821', 'avatar' => '🧔🏻‍♂️', 'vehicle_model' => 'Maruti Ertiga Hybrid (Silver)', 'vehicle_plate' => 'MH 14 BT 8812', 'fleet_category' => 'suv', 'rating' => 4.80, 'trips_count' => 512, 'wallet_balance' => 8200.00],
            ['name' => 'Vikram Deshmukh', 'phone' => '+91 98222 77319', 'avatar' => '👨🏻‍🦱', 'vehicle_model' => 'Honda City ZX (Golden Sand)', 'vehicle_plate' => 'MH 02 CK 1009', 'fleet_category' => 'prime', 'rating' => 4.95, 'trips_count' => 1240, 'wallet_balance' => 22000.00],
        ];
        foreach ($drivers as $d) {
            Driver::create($d);
        }

        // 3. Seed Seat Layouts (Volvo Sleeper Coach from screen.png)
        $berths = [
            // Lower Deck
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L1', 'berth_type' => 'window', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L2', 'berth_type' => 'aisle', 'price' => 850, 'is_ladies_quota' => true, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L3', 'berth_type' => 'window', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => true],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L4', 'berth_type' => 'window', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L5', 'berth_type' => 'aisle', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L6', 'berth_type' => 'window', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L7', 'berth_type' => 'window', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L8', 'berth_type' => 'aisle', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'lower', 'berth_number' => 'L9', 'berth_type' => 'window', 'price' => 850, 'is_ladies_quota' => false, 'is_booked' => false],

            // Upper Deck
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'upper', 'berth_number' => 'U1', 'berth_type' => 'window', 'price' => 950, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'upper', 'berth_number' => 'U2', 'berth_type' => 'aisle', 'price' => 950, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'upper', 'berth_number' => 'U3', 'berth_type' => 'window', 'price' => 950, 'is_ladies_quota' => false, 'is_booked' => true],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'upper', 'berth_number' => 'U4', 'berth_type' => 'window', 'price' => 950, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'upper', 'berth_number' => 'U5', 'berth_type' => 'aisle', 'price' => 950, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '8092', 'operator_name' => 'Intercity Express', 'deck' => 'upper', 'berth_number' => 'U6', 'berth_type' => 'window', 'price' => 950, 'is_ladies_quota' => false, 'is_booked' => false],
        ];
        foreach ($berths as $b) {
            SeatLayout::create($b);
        }

        // 4. Seed Dynamic Form Fields (from PRD)
        $fields = [
            ['field_key' => 'flight_number', 'label' => 'Arrival Flight Number (For Airport Pickup)', 'field_type' => 'text', 'applies_to' => 'airport', 'conditional_logic' => 'Airport Trips Only', 'is_required' => false, 'order_index' => 1],
            ['field_key' => 'pet_breed', 'label' => 'Pet Breed & Estimated Weight', 'field_type' => 'text', 'applies_to' => 'cab', 'conditional_logic' => 'Pet-friendly Selected', 'is_required' => false, 'order_index' => 2],
            ['field_key' => 'gstin', 'label' => 'Company GSTIN for Tax Invoicing', 'field_type' => 'text', 'applies_to' => 'all', 'conditional_logic' => 'Optional Corporate', 'is_required' => false, 'order_index' => 3],
        ];
        foreach ($fields as $f) {
            DynamicFormField::create($f);
        }

        // 5. Seed Workflow Stages
        $stages = [
            ['stage_key' => 'requested', 'stage_name' => 'Trip Requested', 'description' => 'User submits route, date & requirements', 'order_index' => 1],
            ['stage_key' => 'bidding', 'stage_name' => 'Driver Auction & Bidding', 'description' => 'Registered drivers submit live counter bids', 'order_index' => 2],
            ['stage_key' => 'confirmed', 'stage_name' => 'Bid Accepted & Escrow Locked', 'description' => 'Fare locked, ride confirmation emitted', 'order_index' => 3],
            ['stage_key' => 'started', 'stage_name' => 'OTP Start Validation', 'description' => 'Driver enters 4-digit customer ride OTP', 'order_index' => 4],
            ['stage_key' => 'completed', 'stage_name' => 'Trip Completed & Payout', 'description' => 'Tolls reconciled & driver wallet credited', 'order_index' => 5],
        ];
        foreach ($stages as $s) {
            WorkflowStage::create($s);
        }

        // 6. Seed Coupons
        $coupons = [
            ['code' => 'FIRSTWHEELS', 'type' => 'percentage', 'value' => 15.00, 'max_discount' => 500.00, 'min_spend' => 1000.00],
            ['code' => 'INTERCITY100', 'type' => 'flat', 'value' => 100.00, 'max_discount' => 100.00, 'min_spend' => 500.00],
        ];
        foreach ($coupons as $c) {
            Coupon::create($c);
        }

        // 7. Seed Initial Booking
        Booking::create([
            'booking_reference' => 'ICW-90412',
            'service_type' => 'bus',
            'trip_type' => 'oneway',
            'route_summary' => 'Pune → Goa',
            'pickup_location' => 'Swargate Bus Terminal, Bay 4 (09:30 PM)',
            'drop_location' => 'Panjim KTC Bus Stand, Platform 1 (06:45 AM)',
            'customer_name' => 'Rahul Sharma',
            'customer_phone' => '+91 98765 43210',
            'customer_email' => 'rahul@example.com',
            'passengers' => [
                ['name' => 'Rahul Sharma', 'age' => 29, 'gender' => 'male', 'berth' => 'L4'],
                ['name' => 'Sneha Verma', 'age' => 27, 'gender' => 'female', 'berth' => 'L5']
            ],
            'selected_units' => ['L4', 'L5'],
            'base_fare' => 1700.00,
            'toll_fare' => 0.00,
            'addon_fare' => 0.00,
            'discount_fare' => 0.00,
            'total_amount' => 1700.00,
            'status' => 'Confirmed',
            'start_otp' => '8492'
        ]);

        // 8. Seed Role-Based Demo Accounts
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@intercitywheels.com'],
            [
                'name' => 'Vikram Singhania (Ops Admin)',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'admin',
                'phone' => '+91 99000 00001',
                'avatar' => '🛡️'
            ]
        );

        \App\Models\User::firstOrCreate(
            ['email' => 'driver@intercitywheels.com'],
            [
                'name' => 'Ramesh Patil (Partner Chauffeur)',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'driver',
                'phone' => '+91 98220 11920',
                'avatar' => '👨🏽‍✈️'
            ]
        );

        \App\Models\User::firstOrCreate(
            ['email' => 'customer@intercitywheels.com'],
            [
                'name' => 'Rahul Sharma (Traveler)',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'customer',
                'phone' => '+91 98765 43210',
                'avatar' => '👤'
            ]
        );
    }
}
