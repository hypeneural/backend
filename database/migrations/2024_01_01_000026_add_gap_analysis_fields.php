<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing fields identified in Gap Analysis:
 * - users.onboarding_completed
 * - users.primary_city_id
 * - user_stats.longest_streak
 * - user_stats.total_memories
 * - push_subscriptions FCM fields
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. Users table - onboarding and city
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('onboarding_completed')->default(false)->after('is_verified');
            $table->foreignUuid('primary_city_id')->nullable()->after('onboarding_completed')
                ->constrained('cities')->nullOnDelete();
        });

        // 2. User stats - additional gamification fields
        Schema::table('user_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('longest_streak')->default(0)->after('streak_days');
            $table->unsignedInteger('total_memories')->default(0)->after('total_plans');
        });

        // 3. Push subscriptions - FCM fields
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('fcm_token', 500)->nullable()->after('auth');
            $table->enum('device_type', ['ios', 'android', 'web'])->default('web')->after('device_id');
            $table->string('app_version', 20)->nullable()->after('device_type');
        });

        // 4. Cities fulltext index for search
        Schema::table('cities', function (Blueprint $table) {
            $table->fullText('name', 'idx_cities_search');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_city_id']);
            $table->dropColumn(['onboarding_completed', 'primary_city_id']);
        });

        Schema::table('user_stats', function (Blueprint $table) {
            $table->dropColumn(['longest_streak', 'total_memories']);
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'device_type', 'app_version']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropFullText('idx_cities_search');
        });
    }
};
