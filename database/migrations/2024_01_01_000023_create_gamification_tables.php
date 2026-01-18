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
        Schema::create('user_stats', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->integer('xp')->unsigned()->default(0);
            $table->tinyInteger('level')->unsigned()->default(1);
            $table->smallInteger('streak_days')->unsigned()->default(0);
            $table->timestamp('last_action_at')->nullable();
            $table->integer('total_saves')->unsigned()->default(0);
            $table->integer('total_reviews')->unsigned()->default(0);
            $table->integer('total_plans')->unsigned()->default(0);
            $table->integer('total_referrals')->unsigned()->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->string('badge_slug', 50)->comment('explorer, reviewer, social, etc');
            $table->timestamp('earned_at')->useCurrent();

            $table->primary(['user_id', 'badge_slug']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('user_stats');
    }
};
