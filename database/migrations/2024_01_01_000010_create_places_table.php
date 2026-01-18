<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('address', 500)->nullable();
            $table->uuid('city_id');
            $table->string('neighborhood', 100)->nullable();
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->string('google_place_id', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('city_id', 'idx_city');
            $table->index(['lat', 'lng'], 'idx_coords');
            $table->fullText(['name', 'neighborhood'], 'idx_search');

            $table->foreign('city_id')->references('id')->on('cities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
