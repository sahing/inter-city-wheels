<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('car_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Comfort Sedan", "Executive SUV"
            $table->string('category_key')->unique(); // e.g. "sedan", "suv", "hatchback", "innova", "tempo", "luxury", "ev"
            $table->string('badge')->default('Popular'); // e.g. "Value Choice", "Spacious Family", "Top Rated"
            $table->string('models'); // e.g. "Dzire, Etios, Aura"
            $table->string('seats')->default('4 Seats');
            $table->string('luggage')->default('2 Bags');
            $table->decimal('rate_per_km', 8, 2)->default(12.50);
            $table->decimal('extra_km_rate', 8, 2)->default(12.50);
            $table->decimal('base_fare_min', 8, 2)->default(800.00);
            $table->string('image_url')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_types');
    }
};
