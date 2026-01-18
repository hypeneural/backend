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
        Schema::create('family_preferences', function (Blueprint $table) {
            $table->uuid('family_id')->primary();
            $table->smallInteger('max_distance_km')->unsigned()->default(50);
            $table->enum('default_price', ['free', 'moderate', 'top'])->nullable();
            $table->json('avoid')->nullable()->comment('Array of strings');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('family_id')->references('id')->on('families')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_preferences');
    }
};
