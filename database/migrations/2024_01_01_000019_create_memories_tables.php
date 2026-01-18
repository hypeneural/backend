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
        Schema::create('memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('family_id')->nullable();
            $table->uuid('plan_id')->nullable();
            $table->uuid('experience_id')->nullable();
            $table->string('image_url', 500);
            $table->string('thumbnail_url', 500);
            $table->string('caption', 500)->nullable();
            $table->enum('visibility', ['private', 'family', 'collaborators', 'public'])->default('family');
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'idx_user');
            $table->index(['family_id', 'visibility'], 'idx_family');
            $table->index('experience_id', 'idx_experience');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('family_id')->references('id')->on('families')->onDelete('set null');
            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('set null');
        });

        Schema::create('memory_reactions', function (Blueprint $table) {
            $table->uuid('memory_id');
            $table->uuid('user_id');
            $table->string('emoji', 10);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['memory_id', 'user_id']);

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('memory_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('memory_id');
            $table->uuid('user_id');
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index('memory_id', 'idx_memory');

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_comments');
        Schema::dropIfExists('memory_reactions');
        Schema::dropIfExists('memories');
    }
};
