<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bids become session-scoped and keep the evidence needed to police them:
 * who bid, from where, when, and whether the bid is the current winner.
 *
 * `is_winning` is maintained by the bidding service so exactly one bid per
 * session can be the provisional winner (and, after approval, the awarded bid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_bids', function (Blueprint $table) {
            $table->foreignId('session_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('bidding_sessions')
                ->cascadeOnDelete();
            $table->boolean('is_winning')->default(false)->after('bid_amount');
            $table->string('ip_address', 45)->nullable()->after('status');
            $table->boolean('flagged')->default(false)->after('ip_address');
            $table->string('flag_reason', 120)->nullable()->after('flagged');

            $table->index(['session_id', 'bid_amount']);
            $table->index(['session_id', 'is_winning']);
        });
    }

    public function down(): void
    {
        Schema::table('driver_bids', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'bid_amount']);
            $table->dropIndex(['session_id', 'is_winning']);
            $table->dropConstrainedForeignId('session_id');
            $table->dropColumn(['is_winning', 'ip_address', 'flagged', 'flag_reason']);
        });
    }
};
