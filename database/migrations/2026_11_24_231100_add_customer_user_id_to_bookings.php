<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0-8: public booking lookup leaked any passenger's PII to anyone holding a
 * reference. Binding a booking to the signed-in customer lets the lookup
 * authorise by ownership; guests keep access through a signed URL returned at
 * creation time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('customer_user_id')
                ->nullable()
                ->after('booking_reference')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_user_id');
        });
    }
};
