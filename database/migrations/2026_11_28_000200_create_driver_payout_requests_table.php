<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('payout_reference', 32)->unique();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payout_method', 20); // upi | bank
            $table->json('payout_destination');
            $table->string('status', 24)->default('pending'); // pending | paid | rejected
            $table->string('payout_mode', 20)->nullable(); // manual | gateway
            $table->string('gateway_provider', 40)->nullable();
            $table->string('transaction_reference', 120)->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('admin_notes', 500)->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('refund_wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payout_requests');
    }
};
