<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('geo_place_cache')) {
            return;
        }

        Schema::table('geo_place_cache', function (Blueprint $table) {
            if (! Schema::hasColumn('geo_place_cache', 'is_beyond_region')) {
                $table->boolean('is_beyond_region')->default(false)->after('display_name');
            }
            if (! Schema::hasColumn('geo_place_cache', 'approval_status')) {
                $table->string('approval_status', 20)->default('cached')->after('is_beyond_region');
            }
            if (! Schema::hasColumn('geo_place_cache', 'service_location_id')) {
                $table->unsignedBigInteger('service_location_id')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('geo_place_cache', 'last_booking_id')) {
                $table->unsignedBigInteger('last_booking_id')->nullable()->after('service_location_id');
            }
            if (! Schema::hasColumn('geo_place_cache', 'last_booking_reference')) {
                $table->string('last_booking_reference', 40)->nullable()->after('last_booking_id');
            }
            if (! Schema::hasColumn('geo_place_cache', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('last_booking_reference');
            }
            if (! Schema::hasColumn('geo_place_cache', 'approved_by_user_id')) {
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('geo_place_cache')) {
            return;
        }

        Schema::table('geo_place_cache', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('geo_place_cache', 'is_beyond_region') ? 'is_beyond_region' : null,
                Schema::hasColumn('geo_place_cache', 'approval_status') ? 'approval_status' : null,
                Schema::hasColumn('geo_place_cache', 'service_location_id') ? 'service_location_id' : null,
                Schema::hasColumn('geo_place_cache', 'last_booking_id') ? 'last_booking_id' : null,
                Schema::hasColumn('geo_place_cache', 'last_booking_reference') ? 'last_booking_reference' : null,
                Schema::hasColumn('geo_place_cache', 'approved_at') ? 'approved_at' : null,
                Schema::hasColumn('geo_place_cache', 'approved_by_user_id') ? 'approved_by_user_id' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
