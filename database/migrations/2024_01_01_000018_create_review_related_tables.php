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
        Schema::create('review_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('review_id');
            $table->string('url', 500);
            $table->tinyInteger('order')->unsigned()->default(0);

            $table->index('review_id', 'idx_review');

            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade');
        });

        Schema::create('review_helpful', function (Blueprint $table) {
            $table->uuid('review_id');
            $table->uuid('user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['review_id', 'user_id']);

            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('review_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('review_id');
            $table->uuid('user_id');
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index('review_id', 'idx_review');

            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_comments');
        Schema::dropIfExists('review_helpful');
        Schema::dropIfExists('review_photos');
    }
};
