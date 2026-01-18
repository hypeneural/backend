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
        Schema::create('family_invite_uses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invite_id');
            $table->uuid('user_id');
            $table->timestamp('used_at')->useCurrent();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->index('invite_id', 'idx_invite');

            $table->foreign('invite_id')->references('id')->on('family_invites')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_invite_uses');
    }
};
