<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable memory for OpenStreetMap answers.
 *
 * The HTTP cache in OsmGeoService keeps a route for a while but disappears on a
 * cache flush, after which the same customer query would hit Nominatim/OSRM
 * again. These tables keep the measured route and the geocoded place, so the
 * first query pays for the API call and every later query is a single indexed
 * read. Rows expire, so a changed road or a corrected place is re-measured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_route_cache', function (Blueprint $table) {
            $table->id();
            $table->string('route_key', 64)->unique();
            $table->string('from_name')->nullable();
            $table->string('to_name')->nullable();
            $table->decimal('from_lat', 10, 7)->nullable();
            $table->decimal('from_lng', 10, 7)->nullable();
            $table->decimal('to_lat', 10, 7)->nullable();
            $table->decimal('to_lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_km');
            $table->unsignedInteger('duration_min');
            $table->decimal('base_toll', 8, 2)->default(0);
            $table->string('engine', 20)->default('osrm');   // osrm
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('resolved_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('geo_place_cache', function (Blueprint $table) {
            $table->id();
            $table->string('query_key', 64)->unique();
            $table->string('query');
            $table->string('name');
            $table->string('state')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('display_name', 512)->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('resolved_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_place_cache');
        Schema::dropIfExists('geo_route_cache');
    }
};
