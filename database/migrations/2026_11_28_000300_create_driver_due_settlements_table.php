<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_due_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_reference', 40)->unique();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_channel', 30); // gateway | upi_manual
            $table->string('status', 30)->default('pending_verification'); // pending_verification | paid | rejected
            $table->string('upi_utr', 80)->nullable();
            $table->string('razorpay_order_id', 120)->nullable();
            $table->string('razorpay_payment_id', 120)->nullable();
            $table->string('razorpay_signature', 200)->nullable();
            $table->text('driver_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_due_settlements');
    }
};
