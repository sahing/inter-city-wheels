<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'is_beyond_region')) {
                $table->boolean('is_beyond_region')->default(false)->after('trip_type');
            }
            if (! Schema::hasColumn('bookings', 'availability_status')) {
                $table->string('availability_status', 40)->default('guaranteed')->after('is_beyond_region');
            }
            if (! Schema::hasColumn('bookings', 'region_note')) {
                $table->text('region_note')->nullable()->after('availability_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('bookings', 'region_note') ? 'region_note' : null,
                Schema::hasColumn('bookings', 'availability_status') ? 'availability_status' : null,
                Schema::hasColumn('bookings', 'is_beyond_region') ? 'is_beyond_region' : null,
            ]));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
