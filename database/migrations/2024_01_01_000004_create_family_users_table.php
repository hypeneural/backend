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
        Schema::create('family_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->uuid('user_id');
            $table->enum('role', ['owner', 'admin', 'member'])->default('member');
            $table->enum('status', ['active', 'invited', 'left'])->default('active');
            $table->json('permissions')->nullable()->comment('Override permissions');
            $table->string('nickname', 50)->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->uuid('invited_by')->nullable();

            $table->unique(['family_id', 'user_id'], 'idx_family_user');
            $table->index(['user_id', 'status'], 'idx_user_families');

            $table->foreign('family_id')->references('id')->on('families')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_users');
    }
};
