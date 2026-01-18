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
        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->char('state', 2);
            $table->char('country', 2)->default('BR');
            $table->decimal('lat', 10, 8)->nullable()->comment('Center');
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('timezone', 50)->default('America/Sao_Paulo');
            $table->integer('population')->unsigned()->nullable();

            $table->index('state', 'idx_state');
            $table->index(['lat', 'lng'], 'idx_coords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
