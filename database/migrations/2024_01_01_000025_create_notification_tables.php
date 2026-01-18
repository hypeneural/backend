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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('type', [
                'experience_nearby',
                'family_invite',
                'memory_reaction',
                'memory_comment',
                'plan_reminder',
                'plan_invite',
                'review_reply',
                'trending',
                'system',
                'referral_reward'
            ]);
            $table->string('title', 200);
            $table->text('body');
            $table->string('image_url', 500)->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'is_read', 'created_at'], 'idx_user_unread');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->json('types');
            $table->json('quiet_hours')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('endpoint');
            $table->string('p256dh', 500);
            $table->string('auth', 500);
            $table->string('device_id', 255)->nullable();
            $table->string('browser', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id', 'idx_user');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notifications');
    }
};
