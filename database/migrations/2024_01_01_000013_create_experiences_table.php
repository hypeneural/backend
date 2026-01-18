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
        Schema::create('experiences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->string('mission_title', 200);
            $table->text('summary');
            $table->uuid('category_id');
            $table->uuid('place_id');
            $table->uuid('city_id')->comment('Denormalized');

            // Override coordinates (optional)
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();

            // Attributes
            $table->json('badges')->nullable();
            $table->json('age_tags')->comment('["baby","kids"]');
            $table->json('vibe')->comment('{"kids":5,"adults":4,"mess":2,"tired":3}');
            $table->smallInteger('duration_min')->unsigned();
            $table->smallInteger('duration_max')->unsigned();
            $table->enum('price_level', ['free', 'moderate', 'top']);
            $table->string('price_label', 50)->nullable();
            $table->json('weather')->comment('["sun","any"]');
            $table->json('practical')->nullable();
            $table->json('tips')->nullable();

            // Images
            $table->string('cover_image', 500);
            $table->json('gallery')->nullable();

            // Counters (denormalized, updated via job)
            $table->integer('saves_count')->unsigned()->default(0);
            $table->integer('reviews_count')->unsigned()->default(0);
            $table->decimal('average_rating', 2, 1)->nullable();
            $table->float('trending_score')->default(0);

            // Lifecycle
            $table->enum('status', ['draft', 'published', 'paused', 'archived', 'flagged'])->default('published');
            $table->enum('source', ['curated', 'partner', 'user_submitted'])->default('curated');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id', 'idx_category');
            $table->index('city_id', 'idx_city');
            $table->index('place_id', 'idx_place');
            $table->index(['status', 'trending_score'], 'idx_status_trending');
            $table->index(['lat', 'lng'], 'idx_coords');
            $table->fullText(['title', 'mission_title', 'summary'], 'idx_search');

            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('place_id')->references('id')->on('places');
            $table->foreign('city_id')->references('id')->on('cities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
