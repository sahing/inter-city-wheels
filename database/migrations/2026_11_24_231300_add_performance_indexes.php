<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-4: hot lookups ran as full table scans. Every index below backs a specific
 * query path in the controllers (booking status filters, reference/phone
 * search, berth resolution, driver bidding, catalogue lookups).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('status');
            $table->index('customer_phone');
            $table->index('assigned_driver_id');
            $table->index('departure_time');
        });

        Schema::table('seat_layouts', function (Blueprint $table) {
            // Berth numbers repeat per coach, so availability is keyed by both.
            $table->index(['coach_code', 'berth_number']);
            $table->index('is_booked');
        });

        Schema::table('driver_bids', function (Blueprint $table) {
            $table->index(['booking_id', 'status']);
            $table->index('driver_id');
        });

        Schema::table('service_locations', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('route_fares', function (Blueprint $table) {
            $table->index(['from_city', 'to_city']);
            $table->index('is_active');
        });

        Schema::table('driver_vehicles', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('car_types', function (Blueprint $table) {
            $table->index(['is_active', 'order_index']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['customer_phone']);
            $table->dropIndex(['assigned_driver_id']);
            $table->dropIndex(['departure_time']);
        });

        Schema::table('seat_layouts', function (Blueprint $table) {
            $table->dropIndex(['coach_code', 'berth_number']);
            $table->dropIndex(['is_booked']);
        });

        Schema::table('driver_bids', function (Blueprint $table) {
            $table->dropIndex(['booking_id', 'status']);
            $table->dropIndex(['driver_id']);
        });

        Schema::table('service_locations', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('route_fares', function (Blueprint $table) {
            $table->dropIndex(['from_city', 'to_city']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('driver_vehicles', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('car_types', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'order_index']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
