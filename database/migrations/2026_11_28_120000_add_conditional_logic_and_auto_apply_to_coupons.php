<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (! Schema::hasColumn('coupons', 'title')) {
                    $table->string('title', 160)->nullable()->after('code');
                }
                if (! Schema::hasColumn('coupons', 'description')) {
                    $table->string('description', 255)->nullable()->after('title');
                }
                if (! Schema::hasColumn('coupons', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable()->after('min_spend');
                }
                if (! Schema::hasColumn('coupons', 'booking_types')) {
                    $table->json('booking_types')->nullable()->after('used_count');
                }
                if (! Schema::hasColumn('coupons', 'car_types')) {
                    $table->json('car_types')->nullable()->after('booking_types');
                }
                if (! Schema::hasColumn('coupons', 'route_fare_ids')) {
                    $table->json('route_fare_ids')->nullable()->after('car_types');
                }
                if (! Schema::hasColumn('coupons', 'target_user_ids')) {
                    $table->json('target_user_ids')->nullable()->after('route_fare_ids');
                }
                if (! Schema::hasColumn('coupons', 'target_driver_ids')) {
                    $table->json('target_driver_ids')->nullable()->after('target_user_ids');
                }
                if (! Schema::hasColumn('coupons', 'per_user_limit')) {
                    $table->unsignedInteger('per_user_limit')->nullable()->after('target_driver_ids');
                }
                if (! Schema::hasColumn('coupons', 'auto_apply')) {
                    $table->boolean('auto_apply')->default(false)->after('per_user_limit');
                }
                if (! Schema::hasColumn('coupons', 'show_on_checkout')) {
                    $table->boolean('show_on_checkout')->default(true)->after('auto_apply');
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'coupon_code')) {
                    $table->string('coupon_code', 40)->nullable()->after('discount_fare');
                }
                if (! Schema::hasColumn('bookings', 'coupon_id')) {
                    $table->unsignedBigInteger('coupon_id')->nullable()->after('coupon_code');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $cols = [];
                foreach (['coupon_code', 'coupon_id'] as $col) {
                    if (Schema::hasColumn('bookings', $col)) {
                        $cols[] = $col;
                    }
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                $cols = [];
                foreach ([
                    'title',
                    'description',
                    'starts_at',
                    'booking_types',
                    'car_types',
                    'route_fare_ids',
                    'target_user_ids',
                    'target_driver_ids',
                    'per_user_limit',
                    'auto_apply',
                    'show_on_checkout',
                ] as $col) {
                    if (Schema::hasColumn('coupons', $col)) {
                        $cols[] = $col;
                    }
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
