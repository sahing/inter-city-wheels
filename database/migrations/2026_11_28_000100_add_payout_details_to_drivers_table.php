<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('drivers', 'preferred_payout_method')) {
                $table->string('preferred_payout_method', 20)->default('upi');
            }
            if (! Schema::hasColumn('drivers', 'payout_upi_id')) {
                $table->string('payout_upi_id', 120)->nullable()->after('preferred_payout_method');
            }
            if (! Schema::hasColumn('drivers', 'payout_upi_name')) {
                $table->string('payout_upi_name', 120)->nullable()->after('payout_upi_id');
            }
            if (! Schema::hasColumn('drivers', 'bank_account_holder')) {
                $table->string('bank_account_holder', 120)->nullable()->after('payout_upi_name');
            }
            if (! Schema::hasColumn('drivers', 'bank_name')) {
                $table->string('bank_name', 120)->nullable()->after('bank_account_holder');
            }
            if (! Schema::hasColumn('drivers', 'bank_account_number')) {
                $table->string('bank_account_number', 40)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('drivers', 'bank_ifsc')) {
                $table->string('bank_ifsc', 20)->nullable()->after('bank_account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('drivers', 'preferred_payout_method') ? 'preferred_payout_method' : null,
                Schema::hasColumn('drivers', 'payout_upi_id') ? 'payout_upi_id' : null,
                Schema::hasColumn('drivers', 'payout_upi_name') ? 'payout_upi_name' : null,
                Schema::hasColumn('drivers', 'bank_account_holder') ? 'bank_account_holder' : null,
                Schema::hasColumn('drivers', 'bank_name') ? 'bank_name' : null,
                Schema::hasColumn('drivers', 'bank_account_number') ? 'bank_account_number' : null,
                Schema::hasColumn('drivers', 'bank_ifsc') ? 'bank_ifsc' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
