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
        Schema::table('route_fares', function (Blueprint $table) {
            if (! Schema::hasColumn('route_fares', 'hatchback_fare')) {
                $table->decimal('hatchback_fare', 10, 2)->nullable()->after('suv_fare');
            }
            if (! Schema::hasColumn('route_fares', 'innova_fare')) {
                $table->decimal('innova_fare', 10, 2)->nullable()->after('hatchback_fare');
            }
            if (! Schema::hasColumn('route_fares', 'tempo_fare')) {
                $table->decimal('tempo_fare', 10, 2)->nullable()->after('innova_fare');
            }
            if (! Schema::hasColumn('route_fares', 'luxury_fare')) {
                $table->decimal('luxury_fare', 10, 2)->nullable()->after('tempo_fare');
            }
            if (! Schema::hasColumn('route_fares', 'car_fares')) {
                $table->json('car_fares')->nullable()->after('luxury_fare');
            }
            if (! Schema::hasColumn('route_fares', 'departure_schedule')) {
                $table->string('departure_schedule')->nullable()->after('roundtrip_discount_pct');
            }
            if (! Schema::hasColumn('route_fares', 'schedule_frequency')) {
                $table->string('schedule_frequency')->default('Daily Express')->after('departure_schedule');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_fares', function (Blueprint $table) {
            $cols = [];
            foreach (['hatchback_fare', 'innova_fare', 'tempo_fare', 'luxury_fare', 'car_fares', 'departure_schedule', 'schedule_frequency'] as $c) {
                if (Schema::hasColumn('route_fares', $c)) {
                    $cols[] = $c;
                }
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
