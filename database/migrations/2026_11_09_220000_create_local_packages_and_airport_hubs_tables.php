<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_packages')) {
            Schema::create('local_packages', function (Blueprint $table) {
                $table->id();
                $table->string('package_code')->unique();
                $table->string('city')->default('All Cities');
                $table->string('name');
                $table->integer('duration_hours');
                $table->integer('included_km');
                $table->decimal('sedan_fare', 10, 2);
                $table->decimal('suv_fare', 10, 2);
                $table->decimal('extra_km_rate', 10, 2)->default(14.00);
                $table->decimal('extra_hour_rate', 10, 2)->default(150.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('airport_hubs')) {
            Schema::create('airport_hubs', function (Blueprint $table) {
                $table->id();
                $table->string('airport_name');
                $table->string('iata_code', 10);
                $table->string('city');
                $table->string('terminals')->nullable();
                $table->decimal('pickup_surcharge', 10, 2)->default(250.00);
                $table->decimal('drop_surcharge', 10, 2)->default(150.00);
                $table->decimal('sedan_base_fare', 10, 2)->default(900.00);
                $table->decimal('suv_base_fare', 10, 2)->default(1400.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('route_fares')) {
            Schema::table('route_fares', function (Blueprint $table) {
                if (! Schema::hasColumn('route_fares', 'sedan_fare')) {
                    $table->decimal('sedan_fare', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('route_fares', 'suv_fare')) {
                    $table->decimal('suv_fare', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('route_fares', 'roundtrip_discount_pct')) {
                    $table->decimal('roundtrip_discount_pct', 5, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('airport_hubs');
        Schema::dropIfExists('local_packages');
        if (Schema::hasTable('route_fares')) {
            Schema::table('route_fares', function (Blueprint $table) {
                if (Schema::hasColumn('route_fares', 'sedan_fare')) {
                    $table->dropColumn(['sedan_fare', 'suv_fare', 'roundtrip_discount_pct']);
                }
            });
        }
    }
};
