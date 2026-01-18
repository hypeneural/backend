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
        Schema::create('favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('family_id')->nullable();
            $table->uuid('experience_id');
            $table->uuid('list_id')->nullable();
            $table->enum('scope', ['user', 'family'])->default('user');
            $table->timestamp('saved_at')->useCurrent();

            $table->unique(['user_id', 'experience_id'], 'idx_user_exp');
            $table->index('family_id', 'idx_family');
            $table->index('list_id', 'idx_list');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('family_id')->references('id')->on('families')->onDelete('set null');
            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
            $table->foreign('list_id')->references('id')->on('favorite_lists')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
