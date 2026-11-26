<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'payment_mode')) {
                $table->string('payment_mode', 30)->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('bookings', 'advance_percentage')) {
                $table->decimal('advance_percentage', 5, 2)->nullable()->after('payment_mode');
            }
            if (! Schema::hasColumn('bookings', 'advance_amount')) {
                $table->decimal('advance_amount', 10, 2)->nullable()->after('advance_percentage');
            }
            if (! Schema::hasColumn('bookings', 'balance_amount')) {
                $table->decimal('balance_amount', 10, 2)->nullable()->after('advance_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('bookings', 'payment_mode') ? 'payment_mode' : null,
                Schema::hasColumn('bookings', 'advance_percentage') ? 'advance_percentage' : null,
                Schema::hasColumn('bookings', 'advance_amount') ? 'advance_amount' : null,
                Schema::hasColumn('bookings', 'balance_amount') ? 'balance_amount' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
