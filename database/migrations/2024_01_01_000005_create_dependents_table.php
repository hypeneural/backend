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
        Schema::create('dependents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id');
            $table->string('name', 100);
            $table->string('avatar', 50)->nullable()->comment('Emoji');
            $table->date('birth_date')->nullable();
            $table->enum('age_group', ['baby', 'toddler', 'kid', 'teen'])->nullable();
            $table->json('restrictions')->nullable()->comment('Allergies, needs');
            $table->json('interests')->nullable()->comment('Favorite category IDs');
            $table->uuid('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->index('family_id', 'idx_family');

            $table->foreign('family_id')->references('id')->on('families')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dependents');
    }
};
