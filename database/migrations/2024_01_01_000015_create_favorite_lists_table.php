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
        Schema::create('favorite_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('name', 100);
            $table->string('emoji', 10)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('family_id', 'idx_family');
            $table->index('user_id', 'idx_user');

            $table->foreign('family_id')->references('id')->on('families')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_lists');
    }
};
