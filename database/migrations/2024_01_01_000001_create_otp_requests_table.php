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
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 20);
            $table->string('code_hash', 255)->comment('bcrypt hash of the code');
            $table->timestamp('expires_at');
            $table->tinyInteger('attempts')->unsigned()->default(0)->comment('Max 5');
            $table->timestamp('last_sent_at');
            $table->string('ip_hash', 64)->nullable()->comment('SHA256');
            $table->string('device_fingerprint', 255)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['phone', 'expires_at'], 'idx_phone_expires');
            $table->index(['ip_hash', 'last_sent_at'], 'idx_ip_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_requests');
    }
};
