<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Device sessions for:
     * - "Logout from all devices"
     * - Device tracking
     * - Push token management
     * - Abuse detection
     */
    public function up(): void
    {
        // Create device_sessions table (enhanced from refresh_tokens)
        Schema::create('device_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('device_id', 100)->nullable()->comment('Unique device identifier');
            $table->string('device_name', 100)->nullable()->comment('iPhone de João');
            $table->enum('device_type', ['ios', 'android', 'web'])->default('web');
            $table->string('os_version', 50)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('push_token', 255)->nullable()->comment('FCM token');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_active_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_active']);
            $table->index(['device_id']);
            $table->index(['push_token']);
            $table->index(['last_active_at']);
        });

        // Add columns to refresh_tokens to link with device_sessions
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->uuid('device_session_id')->nullable()->after('user_id');
            $table->foreign('device_session_id')->references('id')->on('device_sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropForeign(['device_session_id']);
            $table->dropColumn('device_session_id');
        });

        Schema::dropIfExists('device_sessions');
    }
};
