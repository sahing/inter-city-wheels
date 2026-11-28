<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_mode', 30);
            $table->string('payment_channel', 30); // razorpay, manual_upi, driver_collect
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status', 40)->default('initiated'); // initiated, utr_submitted, verified, failed, rejected, driver_collect, collected_by_driver
            $table->string('gateway_order_id', 120)->nullable();
            $table->string('gateway_payment_id', 120)->nullable();
            $table->string('upi_utr', 80)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
