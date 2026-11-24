<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the winning driver is actually owed.
 *
 * Until now a driver was paid 90% of the customer's fare. Under the auction the
 * driver is paid their winning bid, and the admin keeps the difference, so the
 * payout has to be stored on the booking at award time rather than derived from
 * `total_amount` at completion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('driver_payout_amount', 10, 2)->nullable()->after('total_amount');
            $table->foreignId('assigned_bid_id')->nullable()->after('assigned_driver_id')
                ->constrained('driver_bids')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_bid_id');
            $table->dropColumn('driver_payout_amount');
        });
    }
};
