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
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 255)->comment('SHA256');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('rotated_from_id')->nullable()->comment('Previous token');
            $table->string('device_name', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id', 'idx_user');
            $table->unique('token_hash', 'idx_token_hash');
            $table->index('expires_at', 'idx_expires');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('rotated_from_id')->references('id')->on('refresh_tokens')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
