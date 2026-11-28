<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_type_car_rates')) {
            Schema::create('booking_type_car_rates', function (Blueprint $table) {
                $table->id();
                $table->string('booking_type', 40); // oneway, roundtrip, local, airport_pickup, airport_drop
                $table->foreignId('car_type_id')->constrained('car_types')->cascadeOnDelete();
                $table->decimal('base_fare', 10, 2)->default(0);
                $table->decimal('rate_per_km', 8, 2)->default(0);
                $table->decimal('extra_km_rate', 8, 2)->default(0);
                $table->decimal('extra_hour_rate', 8, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['booking_type', 'car_type_id'], 'bt_car_rates_type_car_unique');
            });
        }

        if (Schema::hasTable('route_fares')) {
            Schema::table('route_fares', function (Blueprint $table) {
                if (! Schema::hasColumn('route_fares', 'additional_amount')) {
                    $table->decimal('additional_amount', 10, 2)->default(0)->after('base_toll');
                }
                if (! Schema::hasColumn('route_fares', 'additional_amount_label')) {
                    $table->string('additional_amount_label', 120)->nullable()->after('additional_amount');
                }
            });
        }

        if (Schema::hasTable('local_packages')) {
            Schema::table('local_packages', function (Blueprint $table) {
                if (! Schema::hasColumn('local_packages', 'car_fares')) {
                    $table->json('car_fares')->nullable()->after('suv_fare');
                }
            });
        }

        if (Schema::hasTable('airport_hubs')) {
            Schema::table('airport_hubs', function (Blueprint $table) {
                if (! Schema::hasColumn('airport_hubs', 'car_fares')) {
                    $table->json('car_fares')->nullable()->after('suv_base_fare');
                }
            });
        }

        if (Schema::hasTable('dynamic_form_fields')) {
            Schema::table('dynamic_form_fields', function (Blueprint $table) {
                if (! Schema::hasColumn('dynamic_form_fields', 'is_price_field')) {
                    $table->boolean('is_price_field')->default(false)->after('field_type');
                }
                if (! Schema::hasColumn('dynamic_form_fields', 'price_amount')) {
                    $table->decimal('price_amount', 10, 2)->default(0)->after('is_price_field');
                }
                if (! Schema::hasColumn('dynamic_form_fields', 'price_mode')) {
                    $table->string('price_mode', 20)->default('flat')->after('price_amount');
                }
                if (! Schema::hasColumn('dynamic_form_fields', 'charge_behavior')) {
                    $table->string('charge_behavior', 20)->default('auto')->after('price_mode');
                }
                if (! Schema::hasColumn('dynamic_form_fields', 'booking_type_scope')) {
                    $table->string('booking_type_scope', 40)->default('all')->after('charge_behavior');
                }
                if (! Schema::hasColumn('dynamic_form_fields', 'route_type_scope')) {
                    $table->string('route_type_scope', 40)->default('all')->after('booking_type_scope');
                }
                if (! Schema::hasColumn('dynamic_form_fields', 'route_fare_id')) {
                    $table->foreignId('route_fare_id')->nullable()->after('route_type_scope')->constrained('route_fares')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'booked_distance_km')) {
                    $table->integer('booked_distance_km')->nullable()->after('selected_units');
                }
                if (! Schema::hasColumn('bookings', 'actual_distance_km')) {
                    $table->integer('actual_distance_km')->nullable()->after('booked_distance_km');
                }
                if (! Schema::hasColumn('bookings', 'extra_km')) {
                    $table->integer('extra_km')->default(0)->after('actual_distance_km');
                }
                if (! Schema::hasColumn('bookings', 'extra_km_rate')) {
                    $table->decimal('extra_km_rate', 8, 2)->default(0)->after('extra_km');
                }
                if (! Schema::hasColumn('bookings', 'extra_km_fare')) {
                    $table->decimal('extra_km_fare', 10, 2)->default(0)->after('extra_km_rate');
                }
                if (! Schema::hasColumn('bookings', 'route_additional_fare')) {
                    $table->decimal('route_additional_fare', 10, 2)->default(0)->after('base_fare');
                }
                if (! Schema::hasColumn('bookings', 'custom_price_fields_fare')) {
                    $table->decimal('custom_price_fields_fare', 10, 2)->default(0)->after('route_additional_fare');
                }
                if (! Schema::hasColumn('bookings', 'fare_breakdown')) {
                    $table->json('fare_breakdown')->nullable()->after('dynamic_fields_data');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $cols = [];
                foreach ([
                    'booked_distance_km',
                    'actual_distance_km',
                    'extra_km',
                    'extra_km_rate',
                    'extra_km_fare',
                    'route_additional_fare',
                    'custom_price_fields_fare',
                    'fare_breakdown',
                ] as $col) {
                    if (Schema::hasColumn('bookings', $col)) {
                        $cols[] = $col;
                    }
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('dynamic_form_fields')) {
            Schema::table('dynamic_form_fields', function (Blueprint $table) {
                if (Schema::hasColumn('dynamic_form_fields', 'route_fare_id')) {
                    $table->dropConstrainedForeignId('route_fare_id');
                }
                $cols = [];
                foreach ([
                    'is_price_field',
                    'price_amount',
                    'price_mode',
                    'charge_behavior',
                    'booking_type_scope',
                    'route_type_scope',
                ] as $col) {
                    if (Schema::hasColumn('dynamic_form_fields', $col)) {
                        $cols[] = $col;
                    }
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('airport_hubs') && Schema::hasColumn('airport_hubs', 'car_fares')) {
            Schema::table('airport_hubs', function (Blueprint $table) {
                $table->dropColumn('car_fares');
            });
        }

        if (Schema::hasTable('local_packages') && Schema::hasColumn('local_packages', 'car_fares')) {
            Schema::table('local_packages', function (Blueprint $table) {
                $table->dropColumn('car_fares');
            });
        }

        if (Schema::hasTable('route_fares')) {
            Schema::table('route_fares', function (Blueprint $table) {
                $cols = [];
                foreach (['additional_amount', 'additional_amount_label'] as $col) {
                    if (Schema::hasColumn('route_fares', $col)) {
                        $cols[] = $col;
                    }
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        Schema::dropIfExists('booking_type_car_rates');
    }
};
