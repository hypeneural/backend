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
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('family_id')->nullable();
            $table->string('title', 200);
            $table->date('date')->nullable();
            $table->enum('status', ['draft', 'planned', 'in_progress', 'completed'])->default('draft');
            $table->enum('visibility', ['private', 'collaborators', 'family'])->default('private');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_user');
            $table->index('family_id', 'idx_family');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('family_id')->references('id')->on('families')->onDelete('set null');
        });

        Schema::create('plan_collaborators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->uuid('user_id');
            $table->enum('role', ['owner', 'editor', 'viewer']);
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->uuid('invited_by');

            $table->unique(['plan_id', 'user_id'], 'idx_plan_user');

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users');
        });

        Schema::create('plan_experiences', function (Blueprint $table) {
            $table->uuid('plan_id');
            $table->uuid('experience_id');
            $table->smallInteger('order')->unsigned();
            $table->enum('time_slot', ['morning', 'afternoon', 'evening'])->nullable();
            $table->text('notes')->nullable();

            $table->primary(['plan_id', 'experience_id']);

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_experiences');
        Schema::dropIfExists('plan_collaborators');
        Schema::dropIfExists('plans');
    }
};
