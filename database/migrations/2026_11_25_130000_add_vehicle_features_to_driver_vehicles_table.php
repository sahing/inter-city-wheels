<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fleet amenities live as a JSON list of feature labels (e.g. "Air Conditioning").
     * A list keeps the catalogue editable from the admin console without a join table.
     */
    public function up(): void
    {
        Schema::table('driver_vehicles', function (Blueprint $table) {
            $table->json('features')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('driver_vehicles', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
