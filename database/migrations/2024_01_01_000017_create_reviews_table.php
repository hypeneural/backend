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
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('experience_id');
            $table->uuid('user_id');
            $table->tinyInteger('rating')->unsigned()->comment('1-5');
            $table->text('comment')->nullable();
            $table->json('tags')->nullable();
            $table->enum('visibility', ['private', 'public'])->default('public');
            $table->date('visited_at')->nullable();
            $table->integer('helpful_count')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['experience_id', 'rating'], 'idx_experience');
            $table->index('user_id', 'idx_user');
            $table->index(['experience_id', 'helpful_count'], 'idx_helpful');

            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
