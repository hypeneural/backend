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
        Schema::create('share_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['experience', 'plan', 'family', 'invite']);
            $table->uuid('target_id');
            $table->string('code', 20);
            $table->uuid('created_by');
            $table->timestamp('expires_at')->nullable();
            $table->integer('clicks_count')->unsigned()->default(0);
            $table->string('utm_source', 50)->nullable();
            $table->string('utm_campaign', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('code', 'idx_code');
            $table->index(['type', 'target_id'], 'idx_type_target');
            $table->index('created_by', 'idx_user');

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referrer_user_id')->comment('Who referred');
            $table->uuid('referred_user_id')->comment('Who was referred');
            $table->uuid('share_link_id')->nullable();
            $table->enum('status', ['pending', 'qualified', 'fraud', 'rewarded'])->default('pending');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('referred_user_id', 'idx_referred');
            $table->index(['referrer_user_id', 'status'], 'idx_referrer');

            $table->foreign('referrer_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referred_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('share_link_id')->references('id')->on('share_links')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('share_links');
    }
};
