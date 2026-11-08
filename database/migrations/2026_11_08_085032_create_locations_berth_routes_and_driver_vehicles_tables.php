<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Service Locations (Setup by Admin for Cab & Transport Network)
        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('state');
            $table->decimal('latitude', 10, 7)->default(18.5204);
            $table->decimal('longitude', 10, 7)->default(73.8567);
            $table->string('airport_code')->nullable(); // e.g. PNQ, BOM, GOI
            $table->boolean('has_airport_service')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Berth Routes (Setup by Admin for Sleeper Bus / Berth Van System)
        Schema::create('berth_routes', function (Blueprint $table) {
            $table->id();
            $table->string('from_city');
            $table->string('to_city');
            $table->string('operator_name');
            $table->string('coach_code'); // links to seat_layouts
            $table->string('vehicle_type')->default('Volvo Multi-Axle AC Sleeper');
            $table->string('departure_time')->default('09:30 PM');
            $table->string('arrival_time')->default('06:45 AM');
            $table->decimal('base_price', 10, 2)->default(850.00);
            $table->integer('total_berths')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Driver Vehicles & Listings (Submitted by Driver, Pending Approval by Admin)
        Schema::create('driver_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('driver_name');
            $table->string('driver_phone');
            $table->string('vehicle_model'); // e.g. Toyota Innova Crysta
            $table->string('vehicle_plate')->unique(); // e.g. MH 12 AB 1234
            $table->string('fleet_category')->default('suv'); // sedan, prime, suv, luxury, sleeper_van
            $table->integer('seating_capacity')->default(4);
            $table->integer('berth_capacity')->default(0); // For berth-enabled cars/vans
            $table->string('coach_code')->nullable(); // For vehicles that have specific seat/berth layout
            $table->string('fuel_type')->default('Diesel'); // Diesel, Petrol, EV, CNG
            $table->string('model_year')->default('2023');
            $table->string('rc_number')->nullable();
            $table->string('status')->default('Pending'); // Pending, Approved, Rejected
            $table->text('admin_notes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_vehicles');
        Schema::dropIfExists('berth_routes');
        Schema::dropIfExists('service_locations');
    }
};
