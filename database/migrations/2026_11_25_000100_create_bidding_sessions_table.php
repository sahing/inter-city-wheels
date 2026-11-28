<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reverse auction for one booking.
 *
 * The admin fixes a starting (maximum acceptable) driver price and a minimum
 * margin percentage; registered drivers are handed a private link and compete
 * downwards. The winning bid is the lowest one, but it only becomes an
 * assignment when the admin approves it.
 *
 * The route summary is snapshotted here (city pair + distance) so the public
 * bidding payload never has to join the Booking — the row physically cannot
 * leak the customer's name, contact or exact addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidding_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('token', 64)->unique();          // the WhatsApp link secret
            $table->string('pickup_city');
            $table->string('drop_city');
            $table->unsignedInteger('distance_km')->nullable();
            $table->decimal('start_amount', 10, 2);         // max acceptable driver price
            $table->decimal('admin_fee_pct', 5, 2)->default(15.00);
            $table->string('status')->default('open');      // open, closed, awarded, cancelled
            $table->dateTime('closes_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('awarded_bid_id')->nullable()->constrained('driver_bids')->nullOnDelete();
            $table->dateTime('awarded_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'closes_at']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidding_sessions');
    }
};
