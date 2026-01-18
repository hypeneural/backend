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
        Schema::create('experience_metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->uuid('experience_id');
            $table->date('date');
            $table->integer('saves')->unsigned()->default(0);
            $table->integer('unsaves')->unsigned()->default(0);
            $table->integer('views')->unsigned()->default(0);
            $table->integer('shares')->unsigned()->default(0);
            $table->integer('clicks')->unsigned()->default(0);
            $table->integer('reviews')->unsigned()->default(0);
            $table->integer('plan_adds')->unsigned()->default(0);

            $table->unique(['experience_id', 'date'], 'idx_exp_date');
            $table->index('date', 'idx_date');

            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
        });

        Schema::create('city_trending', function (Blueprint $table) {
            $table->uuid('city_id');
            $table->uuid('experience_id');
            $table->tinyInteger('position')->unsigned();
            $table->float('score');
            $table->timestamp('calculated_at');

            $table->primary(['city_id', 'position']);
            $table->index('calculated_at', 'idx_calculated');

            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_trending');
        Schema::dropIfExists('experience_metrics_daily');
    }
};
