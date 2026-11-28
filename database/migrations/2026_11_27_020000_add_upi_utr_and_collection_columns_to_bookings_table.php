<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'upi_utr')) {
                $table->string('upi_utr', 80)->nullable()->after('razorpay_signature');
            }
            if (! Schema::hasColumn('bookings', 'upi_verified_at')) {
                $table->timestamp('upi_verified_at')->nullable()->after('upi_utr');
            }
            if (! Schema::hasColumn('bookings', 'upi_verified_by')) {
                $table->unsignedBigInteger('upi_verified_by')->nullable()->after('upi_verified_at');
            }
            if (! Schema::hasColumn('bookings', 'payment_collection_by')) {
                $table->string('payment_collection_by', 20)->default('platform')->after('upi_verified_by');
            }
            if (! Schema::hasColumn('bookings', 'driver_collected_amount')) {
                $table->decimal('driver_collected_amount', 10, 2)->nullable()->after('payment_collection_by');
            }
            if (! Schema::hasColumn('bookings', 'driver_collected_at')) {
                $table->timestamp('driver_collected_at')->nullable()->after('driver_collected_amount');
            }
            if (! Schema::hasColumn('bookings', 'payment_notes')) {
                $table->string('payment_notes', 255)->nullable()->after('driver_collected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('bookings', 'upi_utr') ? 'upi_utr' : null,
                Schema::hasColumn('bookings', 'upi_verified_at') ? 'upi_verified_at' : null,
                Schema::hasColumn('bookings', 'upi_verified_by') ? 'upi_verified_by' : null,
                Schema::hasColumn('bookings', 'payment_collection_by') ? 'payment_collection_by' : null,
                Schema::hasColumn('bookings', 'driver_collected_amount') ? 'driver_collected_amount' : null,
                Schema::hasColumn('bookings', 'driver_collected_at') ? 'driver_collected_at' : null,
                Schema::hasColumn('bookings', 'payment_notes') ? 'payment_notes' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
