<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'payment_gateway')) {
                $table->string('payment_gateway', 30)->nullable()->after('balance_amount');
            }
            if (! Schema::hasColumn('bookings', 'payment_status')) {
                $table->string('payment_status', 30)->nullable()->after('payment_gateway');
            }
            if (! Schema::hasColumn('bookings', 'razorpay_order_id')) {
                $table->string('razorpay_order_id', 100)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('bookings', 'razorpay_payment_id')) {
                $table->string('razorpay_payment_id', 100)->nullable()->after('razorpay_order_id');
            }
            if (! Schema::hasColumn('bookings', 'razorpay_signature')) {
                $table->string('razorpay_signature', 255)->nullable()->after('razorpay_payment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('bookings', 'payment_gateway') ? 'payment_gateway' : null,
                Schema::hasColumn('bookings', 'payment_status') ? 'payment_status' : null,
                Schema::hasColumn('bookings', 'razorpay_order_id') ? 'razorpay_order_id' : null,
                Schema::hasColumn('bookings', 'razorpay_payment_id') ? 'razorpay_payment_id' : null,
                Schema::hasColumn('bookings', 'razorpay_signature') ? 'razorpay_signature' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
