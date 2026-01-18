<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * experience_search is a READ MODEL - denormalized for ultra-fast reads
     * Updated via UpdateExperienceSearchJob when experience/place/review changes
     */
    public function up(): void
    {
        Schema::create('experience_search', function (Blueprint $table) {
            $table->uuid('experience_id')->primary();

            // Basic data (to avoid JOINs)
            $table->string('title', 200);
            $table->string('mission_title', 200);
            $table->string('cover_image', 500);

            // IDs for filters
            $table->uuid('category_id');
            $table->uuid('city_id');

            // Coordinates (resolved: experience override or place lat/lng)
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);

            // Ready filters (no JSON)
            $table->enum('price_level', ['free', 'moderate', 'top']);
            $table->enum('duration_bucket', ['quick', 'half', 'full'])->comment('<1h, 1-3h, 3h+');

            // Bitmasks for fast filtering
            $table->tinyInteger('age_tags_mask')->unsigned()->comment('baby=1, toddler=2, kid=4, teen=8, all=16');
            $table->tinyInteger('weather_mask')->unsigned()->comment('sun=1, rain=2, any=4');
            $table->smallInteger('practical_mask')->unsigned()->default(0)->comment('parking=1, bathroom=2, food=4, stroller=8, etc');

            // Counters
            $table->integer('saves_count')->unsigned()->default(0);
            $table->integer('reviews_count')->unsigned()->default(0);
            $table->decimal('average_rating', 2, 1)->nullable();
            $table->float('trending_score')->default(0);

            // Full-text search
            $table->text('search_text')->comment('title + mission + summary + place.name + neighborhood');

            // Status
            $table->enum('status', ['draft', 'published', 'paused', 'archived', 'flagged']);

            // Timestamps
            $table->timestamp('updated_at');

            $table->index(['city_id', 'status', 'trending_score'], 'idx_city_trending');
            $table->index(['category_id', 'status'], 'idx_category');
            $table->index(['lat', 'lng'], 'idx_coords');
            $table->index(['price_level', 'status'], 'idx_price');
            $table->fullText('search_text', 'idx_fulltext');

            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experience_search');
    }
};
