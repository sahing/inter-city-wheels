<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0-4: coupons were honoured without any expiry or usage ceiling, so a
 * one-off promo could be redeemed forever. These columns give a coupon a
 * lifecycle that the pricing service enforces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('is_active');
            $table->unsignedInteger('usage_limit')->nullable()->after('expires_at');
            $table->unsignedInteger('used_count')->default(0)->after('usage_limit');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'usage_limit', 'used_count']);
        });
    }
};
