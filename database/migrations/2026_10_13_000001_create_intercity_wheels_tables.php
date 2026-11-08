<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_fares', function (Blueprint $table) {
            $table->id();
            $table->string('from_city');
            $table->string('to_city');
            $table->decimal('from_lat', 10, 7);
            $table->decimal('from_lng', 10, 7);
            $table->decimal('to_lat', 10, 7);
            $table->decimal('to_lng', 10, 7);
            $table->integer('distance_km');
            $table->string('estimated_time');
            $table->decimal('base_toll', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('avatar')->nullable();
            $table->string('vehicle_model');
            $table->string('vehicle_plate')->unique();
            $table->string('fleet_category'); // sedan, prime, suv, luxury
            $table->decimal('rating', 3, 2)->default(4.90);
            $table->integer('trips_count')->default(0);
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_online')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference')->unique();
            $table->string('service_type'); // cab, bus
            $table->string('trip_type')->default('oneway'); // oneway, roundtrip, airport
            $table->string('route_summary');
            $table->string('pickup_location');
            $table->string('drop_location');
            $table->dateTime('departure_time')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->json('passengers')->nullable();
            $table->json('selected_units')->nullable(); // berths or vehicle class
            $table->decimal('base_fare', 10, 2);
            $table->decimal('toll_fare', 10, 2)->default(0);
            $table->decimal('addon_fare', 10, 2)->default(0);
            $table->decimal('discount_fare', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('Pending'); // Requested, Confirmed, Driver Assigned, Started, Completed, Cancelled
            $table->string('start_otp', 6);
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->json('dynamic_fields_data')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->decimal('bid_amount', 10, 2);
            $table->decimal('counter_amount', 10, 2)->nullable();
            $table->string('status')->default('pending'); // pending, countered, accepted, rejected
            $table->timestamps();
        });

        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_key')->unique();
            $table->string('label');
            $table->string('field_type'); // text, number, select, checkbox
            $table->string('applies_to')->default('all'); // all, cab, bus, airport
            $table->string('conditional_logic')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->string('stage_key')->unique();
            $table->string('stage_name');
            $table->string('description');
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seat_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('coach_code');
            $table->string('operator_name');
            $table->string('deck'); // lower, upper
            $table->string('berth_number');
            $table->string('berth_type'); // window, aisle
            $table->decimal('price', 10, 2);
            $table->boolean('is_ladies_quota')->default(false);
            $table->boolean('is_booked')->default(false);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type'); // percentage, flat
            $table->decimal('value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_spend', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('seat_layouts');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('dynamic_form_fields');
        Schema::dropIfExists('driver_bids');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('route_fares');
    }
};
