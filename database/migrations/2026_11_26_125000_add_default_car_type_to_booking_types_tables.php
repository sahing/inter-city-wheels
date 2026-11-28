<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('route_fares') && ! Schema::hasColumn('route_fares', 'default_car_type')) {
            Schema::table('route_fares', function (Blueprint $table) {
                $table->string('default_car_type', 60)->nullable()->after('roundtrip_discount_pct');
            });
        }

        if (Schema::hasTable('local_packages') && ! Schema::hasColumn('local_packages', 'default_car_type')) {
            Schema::table('local_packages', function (Blueprint $table) {
                $table->string('default_car_type', 60)->nullable()->after('extra_hour_rate');
            });
        }

        if (Schema::hasTable('airport_hubs') && ! Schema::hasColumn('airport_hubs', 'default_car_type')) {
            Schema::table('airport_hubs', function (Blueprint $table) {
                $table->string('default_car_type', 60)->nullable()->after('suv_base_fare');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('route_fares') && Schema::hasColumn('route_fares', 'default_car_type')) {
            Schema::table('route_fares', function (Blueprint $table) {
                $table->dropColumn('default_car_type');
            });
        }

        if (Schema::hasTable('local_packages') && Schema::hasColumn('local_packages', 'default_car_type')) {
            Schema::table('local_packages', function (Blueprint $table) {
                $table->dropColumn('default_car_type');
            });
        }

        if (Schema::hasTable('airport_hubs') && Schema::hasColumn('airport_hubs', 'default_car_type')) {
            Schema::table('airport_hubs', function (Blueprint $table) {
                $table->dropColumn('default_car_type');
            });
        }
    }
};
