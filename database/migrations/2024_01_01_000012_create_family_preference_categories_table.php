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
        // Create join table for family preference categories first
        Schema::create('family_preference_categories', function (Blueprint $table) {
            $table->uuid('family_id');
            $table->uuid('category_id');
            $table->tinyInteger('weight')->unsigned()->default(1)->comment('1-10');

            $table->primary(['family_id', 'category_id']);

            $table->foreign('family_id')->references('id')->on('families')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_preference_categories');
    }
};
