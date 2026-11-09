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
            // West Bengal Murshidabad-centric routes
            ['from_city' => 'Baharampur', 'to_city' => 'Kolkata', 'from_lat' => 24.0988, 'from_lng' => 88.2514, 'to_lat' => 22.6548, 'to_lng' => 88.4467, 'distance_km' => 205, 'estimated_time' => '5h 15m', 'base_toll' => 180],
            ['from_city' => 'Kolkata', 'to_city' => 'Baharampur', 'from_lat' => 22.6548, 'from_lng' => 88.4467, 'to_lat' => 24.0988, 'to_lng' => 88.2514, 'distance_km' => 205, 'estimated_time' => '5h 15m', 'base_toll' => 180],
            ['from_city' => 'Murshidabad', 'to_city' => 'Kolkata', 'from_lat' => 24.1750, 'from_lng' => 88.2800, 'to_lat' => 22.6548, 'to_lng' => 88.4467, 'distance_km' => 218, 'estimated_time' => '5h 30m', 'base_toll' => 180],
            ['from_city' => 'Kolkata', 'to_city' => 'Murshidabad', 'from_lat' => 22.6548, 'from_lng' => 88.4467, 'to_lat' => 24.1750, 'to_lng' => 88.2800, 'distance_km' => 218, 'estimated_time' => '5h 30m', 'base_toll' => 180],
            ['from_city' => 'Baharampur', 'to_city' => 'Farakka', 'from_lat' => 24.0988, 'from_lng' => 88.2514, 'to_lat' => 24.8167, 'to_lng' => 87.9000, 'distance_km' => 102, 'estimated_time' => '2h 30m', 'base_toll' => 90],
            ['from_city' => 'Farakka', 'to_city' => 'Baharampur', 'from_lat' => 24.8167, 'from_lng' => 87.9000, 'to_lat' => 24.0988, 'to_lng' => 88.2514, 'distance_km' => 102, 'estimated_time' => '2h 30m', 'base_toll' => 90],
            ['from_city' => 'Baharampur', 'to_city' => 'Jangipur', 'from_lat' => 24.0988, 'from_lng' => 88.2514, 'to_lat' => 24.4686, 'to_lng' => 88.0700, 'distance_km' => 65, 'estimated_time' => '1h 35m', 'base_toll' => 50],
            ['from_city' => 'Jangipur', 'to_city' => 'Baharampur', 'from_lat' => 24.4686, 'from_lng' => 88.0700, 'to_lat' => 24.0988, 'to_lng' => 88.2514, 'distance_km' => 65, 'estimated_time' => '1h 35m', 'base_toll' => 50],
            ['from_city' => 'Baharampur', 'to_city' => 'Kandi', 'from_lat' => 24.0988, 'from_lng' => 88.2514, 'to_lat' => 23.9556, 'to_lng' => 88.0378, 'distance_km' => 32, 'estimated_time' => '55m', 'base_toll' => 0],
            ['from_city' => 'Kandi', 'to_city' => 'Baharampur', 'from_lat' => 23.9556, 'from_lng' => 88.0378, 'to_lat' => 24.0988, 'to_lng' => 88.2514, 'distance_km' => 32, 'estimated_time' => '55m', 'base_toll' => 0],
            ['from_city' => 'Kolkata', 'to_city' => 'Mayapur', 'from_lat' => 22.6548, 'from_lng' => 88.4467, 'to_lat' => 23.4232, 'to_lng' => 88.3900, 'distance_km' => 132, 'estimated_time' => '3h 30m', 'base_toll' => 120],
            ['from_city' => 'Mayapur', 'to_city' => 'Kolkata', 'from_lat' => 23.4232, 'from_lng' => 88.3900, 'to_lat' => 22.6548, 'to_lng' => 88.4467, 'distance_km' => 132, 'estimated_time' => '3h 30m', 'base_toll' => 120],
        ];
        foreach ($routes as $r) {
            RouteFare::firstOrCreate(['from_city' => $r['from_city'], 'to_city' => $r['to_city']], $r);
        }

        // 2. Seed Verified Drivers
        $drivers = [
            ['name' => 'Ramesh Patil', 'phone' => '+91 98220 11920', 'avatar' => '👨🏽‍✈️', 'vehicle_model' => 'Toyota Innova Crysta (White)', 'vehicle_plate' => 'MH 12 QX 4920', 'fleet_category' => 'suv', 'rating' => 4.90, 'trips_count' => 840, 'wallet_balance' => 14500.00],
            ['name' => 'Ajay Solanki', 'phone' => '+91 98221 44821', 'avatar' => '🧔🏻‍♂️', 'vehicle_model' => 'Maruti Ertiga Hybrid (Silver)', 'vehicle_plate' => 'MH 14 BT 8812', 'fleet_category' => 'suv', 'rating' => 4.80, 'trips_count' => 512, 'wallet_balance' => 8200.00],
            ['name' => 'Vikram Deshmukh', 'phone' => '+91 98222 77319', 'avatar' => '👨🏻‍🦱', 'vehicle_model' => 'Honda City ZX (Golden Sand)', 'vehicle_plate' => 'MH 02 CK 1009', 'fleet_category' => 'prime', 'rating' => 4.95, 'trips_count' => 1240, 'wallet_balance' => 22000.00],
        ];
        foreach ($drivers as $d) {
            Driver::firstOrCreate(['vehicle_plate' => $d['vehicle_plate']], $d);
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
            SeatLayout::firstOrCreate(['coach_code' => $b['coach_code'], 'berth_number' => $b['berth_number']], $b);
        }

        // 4. Seed Dynamic Form Fields (from PRD)
        $fields = [
            ['field_key' => 'flight_number', 'label' => 'Arrival Flight Number (For Airport Pickup)', 'field_type' => 'text', 'applies_to' => 'airport', 'conditional_logic' => 'Airport Trips Only', 'is_required' => false, 'order_index' => 1],
            ['field_key' => 'pet_breed', 'label' => 'Pet Breed & Estimated Weight', 'field_type' => 'text', 'applies_to' => 'cab', 'conditional_logic' => 'Pet-friendly Selected', 'is_required' => false, 'order_index' => 2],
            ['field_key' => 'gstin', 'label' => 'Company GSTIN for Tax Invoicing', 'field_type' => 'text', 'applies_to' => 'all', 'conditional_logic' => 'Optional Corporate', 'is_required' => false, 'order_index' => 3],
        ];
        foreach ($fields as $f) {
            DynamicFormField::firstOrCreate(['field_key' => $f['field_key']], $f);
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
            WorkflowStage::firstOrCreate(['stage_key' => $s['stage_key']], $s);
        }

        // 6. Seed Coupons
        $coupons = [
            ['code' => 'FIRSTWHEELS', 'type' => 'percentage', 'value' => 15.00, 'max_discount' => 500.00, 'min_spend' => 1000.00],
            ['code' => 'INTERCITY100', 'type' => 'flat', 'value' => 100.00, 'max_discount' => 100.00, 'min_spend' => 500.00],
        ];
        foreach ($coupons as $c) {
            Coupon::firstOrCreate(['code' => $c['code']], $c);
        }

        // 7. Seed Initial Booking
        Booking::firstOrCreate(['booking_reference' => 'ICW-90412'], [
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

        // 9. Seed Service Locations (Setup by Admin for Cabs & Transit)
        $locations = [
            ['name' => 'Pune', 'state' => 'Maharashtra', 'latitude' => 18.5204, 'longitude' => 73.8567, 'airport_code' => 'PNQ', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Mumbai', 'state' => 'Maharashtra', 'latitude' => 19.0760, 'longitude' => 72.8777, 'airport_code' => 'BOM', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Goa', 'state' => 'Goa', 'latitude' => 15.4909, 'longitude' => 73.8278, 'airport_code' => 'GOI', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Bengaluru', 'state' => 'Karnataka', 'latitude' => 12.9716, 'longitude' => 77.5946, 'airport_code' => 'BLR', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Hyderabad', 'state' => 'Telangana', 'latitude' => 17.3850, 'longitude' => 78.4867, 'airport_code' => 'HYD', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Delhi', 'state' => 'Delhi NCR', 'latitude' => 28.6139, 'longitude' => 77.2090, 'airport_code' => 'DEL', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Jaipur', 'state' => 'Rajasthan', 'latitude' => 26.9124, 'longitude' => 75.7873, 'airport_code' => 'JAI', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Chennai', 'state' => 'Tamil Nadu', 'latitude' => 13.0827, 'longitude' => 80.2707, 'airport_code' => 'MAA', 'has_airport_service' => true, 'is_active' => true],
            // West Bengal - Murshidabad Centric Network Hubs
            ['name' => 'Baharampur', 'state' => 'West Bengal (Murshidabad)', 'latitude' => 24.0988, 'longitude' => 88.2514, 'airport_code' => null, 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Murshidabad', 'state' => 'West Bengal (Heritage City)', 'latitude' => 24.1750, 'longitude' => 88.2800, 'airport_code' => null, 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Jangipur', 'state' => 'West Bengal (Murshidabad)', 'latitude' => 24.4686, 'longitude' => 88.0700, 'airport_code' => null, 'has_airport_service' => false, 'is_active' => true],
            ['name' => 'Farakka', 'state' => 'West Bengal (Murshidabad)', 'latitude' => 24.8167, 'longitude' => 87.9000, 'airport_code' => null, 'has_airport_service' => false, 'is_active' => true],
            ['name' => 'Kandi', 'state' => 'West Bengal (Murshidabad)', 'latitude' => 23.9556, 'longitude' => 88.0378, 'airport_code' => null, 'has_airport_service' => false, 'is_active' => true],
            ['name' => 'Kolkata', 'state' => 'West Bengal', 'latitude' => 22.6548, 'longitude' => 88.4467, 'airport_code' => 'CCU', 'has_airport_service' => true, 'is_active' => true],
            ['name' => 'Mayapur', 'state' => 'West Bengal (Nadia)', 'latitude' => 23.4232, 'longitude' => 88.3900, 'airport_code' => null, 'has_airport_service' => true, 'is_active' => true],
        ];
        foreach ($locations as $loc) {
            \App\Models\ServiceLocation::firstOrCreate(['name' => $loc['name']], $loc);
        }

        // 10. Seed Berth Routes (Setup by Admin for Sleeper Bus / Berth Van System)
        $berthRoutes = [
            [
                'from_city' => 'Pune',
                'to_city' => 'Goa',
                'operator_name' => 'Intercity Express',
                'coach_code' => '8092',
                'vehicle_type' => 'Volvo Multi-Axle AC Sleeper',
                'departure_time' => '09:30 PM',
                'arrival_time' => '06:45 AM',
                'base_price' => 850.00,
                'total_berths' => 30,
                'is_active' => true
            ],
            [
                'from_city' => 'Mumbai',
                'to_city' => 'Goa',
                'operator_name' => 'Zingbus Platinum',
                'coach_code' => '9104',
                'vehicle_type' => 'Scania Luxury AC Sleeper',
                'departure_time' => '08:00 PM',
                'arrival_time' => '07:30 AM',
                'base_price' => 950.00,
                'total_berths' => 32,
                'is_active' => true
            ],
            [
                'from_city' => 'Bengaluru',
                'to_city' => 'Hyderabad',
                'operator_name' => 'Intercity Star Royal',
                'coach_code' => '7721',
                'vehicle_type' => 'Volvo 9600 AC Berth Coach',
                'departure_time' => '10:00 PM',
                'arrival_time' => '06:00 AM',
                'base_price' => 800.00,
                'total_berths' => 30,
                'is_active' => true
            ],
            [
                'from_city' => 'Pune',
                'to_city' => 'Mumbai',
                'operator_name' => 'Executive Urbania Transit',
                'coach_code' => '5510',
                'vehicle_type' => 'Force Urbania 7-Berth Luxury Van',
                'departure_time' => '06:00 AM',
                'arrival_time' => '09:30 AM',
                'base_price' => 550.00,
                'total_berths' => 7,
                'is_active' => true
            ],
            [
                'from_city' => 'Baharampur',
                'to_city' => 'Kolkata',
                'operator_name' => 'Royal Bengal Sleeper Express',
                'coach_code' => 'WB-8012',
                'vehicle_type' => 'BharatBenz Luxury AC Sleeper',
                'departure_time' => '11:00 PM',
                'arrival_time' => '05:30 AM',
                'base_price' => 450.00,
                'total_berths' => 30,
                'is_active' => true
            ],
            [
                'from_city' => 'Kolkata',
                'to_city' => 'Baharampur',
                'operator_name' => 'Royal Bengal Sleeper Express',
                'coach_code' => 'WB-8013',
                'vehicle_type' => 'BharatBenz Luxury AC Sleeper',
                'departure_time' => '05:30 AM',
                'arrival_time' => '11:00 AM',
                'base_price' => 450.00,
                'total_berths' => 30,
                'is_active' => true
            ]
        ];
        foreach ($berthRoutes as $br) {
            \App\Models\BerthRoute::firstOrCreate(['coach_code' => $br['coach_code']], $br);
        }

        // Additional berths for Coach 5510 (Force Urbania 7-Berth Van)
        $vanBerths = [
            ['coach_code' => '5510', 'operator_name' => 'Executive Urbania Transit', 'deck' => 'lower', 'berth_number' => 'B1', 'berth_type' => 'window', 'price' => 550, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '5510', 'operator_name' => 'Executive Urbania Transit', 'deck' => 'lower', 'berth_number' => 'B2', 'berth_type' => 'window', 'price' => 550, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '5510', 'operator_name' => 'Executive Urbania Transit', 'deck' => 'lower', 'berth_number' => 'B3', 'berth_type' => 'aisle', 'price' => 500, 'is_ladies_quota' => true, 'is_booked' => false],
            ['coach_code' => '5510', 'operator_name' => 'Executive Urbania Transit', 'deck' => 'lower', 'berth_number' => 'B4', 'berth_type' => 'window', 'price' => 550, 'is_ladies_quota' => false, 'is_booked' => true],
            ['coach_code' => '5510', 'operator_name' => 'Executive Urbania Transit', 'deck' => 'lower', 'berth_number' => 'B5', 'berth_type' => 'window', 'price' => 550, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '5510', 'operator_name' => 'Executive Urbania Transit', 'deck' => 'lower', 'berth_number' => 'B6', 'berth_type' => 'aisle', 'price' => 500, 'is_ladies_quota' => false, 'is_booked' => false],
            ['coach_code' => '5510', 'operator_name' => 'Executive Urbania Transit', 'deck' => 'lower', 'berth_number' => 'B7', 'berth_type' => 'window', 'price' => 550, 'is_ladies_quota' => false, 'is_booked' => false],
        ];
        foreach ($vanBerths as $vb) {
            \App\Models\SeatLayout::firstOrCreate(['coach_code' => $vb['coach_code'], 'berth_number' => $vb['berth_number']], $vb);
        }

        // 11. Seed Driver Vehicles (Driver Listed Vehicles & Pending Approvals)
        $ramesh = \App\Models\Driver::first();
        \App\Models\DriverVehicle::firstOrCreate(
            ['vehicle_plate' => 'MH 12 QX 4920'],
            [
                'driver_id' => $ramesh ? $ramesh->id : null,
                'driver_name' => 'Ramesh Patil',
                'driver_phone' => '+91 98220 11920',
                'vehicle_model' => 'Toyota Innova Crysta (White)',
                'fleet_category' => 'suv',
                'seating_capacity' => 7,
                'berth_capacity' => 0,
                'coach_code' => null,
                'fuel_type' => 'Diesel',
                'model_year' => '2023',
                'rc_number' => 'RC-MH12-984129',
                'status' => 'Approved',
                'admin_notes' => 'Verified commercial taxi permit and fastag active.',
                'is_active' => true
            ]
        );

        \App\Models\DriverVehicle::firstOrCreate(
            ['vehicle_plate' => 'MH 12 TS 9901'],
            [
                'driver_id' => $ramesh ? $ramesh->id : null,
                'driver_name' => 'Suniel Jadhav',
                'driver_phone' => '+91 98225 33412',
                'vehicle_model' => 'Toyota Fortuner 4x4 (Black)',
                'fleet_category' => 'luxury',
                'seating_capacity' => 7,
                'berth_capacity' => 0,
                'coach_code' => null,
                'fuel_type' => 'Diesel',
                'model_year' => '2024',
                'rc_number' => 'RC-MH12-110022',
                'status' => 'Pending',
                'admin_notes' => 'Uploaded RC copy and insurance certificate pending physical inspection.',
                'is_active' => false
            ]
        );

        \App\Models\DriverVehicle::firstOrCreate(
            ['vehicle_plate' => 'MH 14 CL 7720'],
            [
                'driver_id' => null,
                'driver_name' => 'Manoj Kumar',
                'driver_phone' => '+91 98900 12345',
                'vehicle_model' => 'Force Urbania Berth Van (Executive)',
                'fleet_category' => 'sleeper_van',
                'seating_capacity' => 9,
                'berth_capacity' => 7,
                'coach_code' => '5510',
                'fuel_type' => 'Diesel',
                'model_year' => '2023',
                'rc_number' => 'RC-MH14-883921',
                'status' => 'Pending',
                'admin_notes' => '7 sleeper berths fitted. Pending berth safety certificate check.',
                'is_active' => false
            ]
        );

        // 12. Seed Platform Settings (Policy URLs)
        \App\Models\PlatformSetting::set('privacy_policy_url', '/privacy-policy');
        \App\Models\PlatformSetting::set('terms_conditions_url', '/terms-conditions');

        // 13. Seed Configured Local Rental Packages (Hourly & KM)
        $localPackages = [
            [
                'package_code' => '4_40',
                'city' => 'All Cities',
                'name' => '4 Hours / 40 KM',
                'duration_hours' => 4,
                'included_km' => 40,
                'sedan_fare' => 1400.00,
                'suv_fare' => 1900.00,
                'extra_km_rate' => 14.00,
                'extra_hour_rate' => 150.00,
                'is_active' => true,
            ],
            [
                'package_code' => '8_80',
                'city' => 'All Cities',
                'name' => '8 Hours / 80 KM (Half Day)',
                'duration_hours' => 8,
                'included_km' => 80,
                'sedan_fare' => 2400.00,
                'suv_fare' => 3200.00,
                'extra_km_rate' => 14.00,
                'extra_hour_rate' => 150.00,
                'is_active' => true,
            ],
            [
                'package_code' => '10_100',
                'city' => 'All Cities',
                'name' => '10 Hours / 100 KM (Full Day)',
                'duration_hours' => 10,
                'included_km' => 100,
                'sedan_fare' => 2900.00,
                'suv_fare' => 3800.00,
                'extra_km_rate' => 14.00,
                'extra_hour_rate' => 150.00,
                'is_active' => true,
            ],
            [
                'package_code' => '12_120',
                'city' => 'All Cities',
                'name' => '12 Hours / 120 KM (Extended Day)',
                'duration_hours' => 12,
                'included_km' => 120,
                'sedan_fare' => 3400.00,
                'suv_fare' => 4400.00,
                'extra_km_rate' => 14.00,
                'extra_hour_rate' => 150.00,
                'is_active' => true,
            ],
        ];
        foreach ($localPackages as $lp) {
            \App\Models\LocalPackage::firstOrCreate(['package_code' => $lp['package_code']], $lp);
        }

        // 14. Seed Configured Airport Transit Hubs
        $airportHubs = [
            [
                'airport_name' => 'Netaji Subhash Chandra Bose International Airport',
                'iata_code' => 'CCU',
                'city' => 'Kolkata',
                'terminals' => 'Terminal 1 (Domestic & International)',
                'pickup_surcharge' => 250.00,
                'drop_surcharge' => 150.00,
                'sedan_base_fare' => 950.00,
                'suv_base_fare' => 1450.00,
                'is_active' => true,
            ],
            [
                'airport_name' => 'Indira Gandhi International Airport',
                'iata_code' => 'DEL',
                'city' => 'New Delhi',
                'terminals' => 'Terminal 1, Terminal 2, Terminal 3',
                'pickup_surcharge' => 300.00,
                'drop_surcharge' => 180.00,
                'sedan_base_fare' => 1100.00,
                'suv_base_fare' => 1650.00,
                'is_active' => true,
            ],
            [
                'airport_name' => 'Chhatrapati Shivaji Maharaj International Airport',
                'iata_code' => 'BOM',
                'city' => 'Mumbai',
                'terminals' => 'Terminal 1 (Santacruz) & Terminal 2 (Sahar)',
                'pickup_surcharge' => 300.00,
                'drop_surcharge' => 180.00,
                'sedan_base_fare' => 1200.00,
                'suv_base_fare' => 1750.00,
                'is_active' => true,
            ],
            [
                'airport_name' => 'Pune International Airport',
                'iata_code' => 'PNQ',
                'city' => 'Pune',
                'terminals' => 'Terminal 1 (Lohegaon New Integrated Terminal)',
                'pickup_surcharge' => 200.00,
                'drop_surcharge' => 120.00,
                'sedan_base_fare' => 850.00,
                'suv_base_fare' => 1300.00,
                'is_active' => true,
            ],
            [
                'airport_name' => 'Kempegowda International Airport',
                'iata_code' => 'BLR',
                'city' => 'Bengaluru',
                'terminals' => 'Terminal 1 & Terminal 2 (Garden Terminal)',
                'pickup_surcharge' => 300.00,
                'drop_surcharge' => 180.00,
                'sedan_base_fare' => 1250.00,
                'suv_base_fare' => 1800.00,
                'is_active' => true,
            ],
            [
                'airport_name' => 'Rajiv Gandhi International Airport',
                'iata_code' => 'HYD',
                'city' => 'Hyderabad',
                'terminals' => 'Main Integrated Passenger Terminal (Shamshabad)',
                'pickup_surcharge' => 250.00,
                'drop_surcharge' => 150.00,
                'sedan_base_fare' => 1050.00,
                'suv_base_fare' => 1550.00,
                'is_active' => true,
            ],
            [
                'airport_name' => 'Kazi Nazrul Islam Airport',
                'iata_code' => 'RDP',
                'city' => 'Durgapur',
                'terminals' => 'Domestic Passenger Terminal',
                'pickup_surcharge' => 200.00,
                'drop_surcharge' => 120.00,
                'sedan_base_fare' => 800.00,
                'suv_base_fare' => 1250.00,
                'is_active' => true,
            ],
            [
                'airport_name' => 'Bagdogra International Airport',
                'iata_code' => 'IXB',
                'city' => 'Siliguri',
                'terminals' => 'Main Terminal Building',
                'pickup_surcharge' => 250.00,
                'drop_surcharge' => 150.00,
                'sedan_base_fare' => 950.00,
                'suv_base_fare' => 1400.00,
                'is_active' => true,
            ],
        ];
        foreach ($airportHubs as $ah) {
            \App\Models\AirportHub::firstOrCreate(['iata_code' => $ah['iata_code']], $ah);
        }
    }
}
