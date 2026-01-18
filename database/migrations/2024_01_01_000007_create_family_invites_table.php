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
        Schema::create('family_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->nullable();
            $table->uuid('plan_id')->nullable();
            $table->enum('type', ['family', 'plan']);
            $table->string('code', 20)->comment('BORA-ABC123 (user-friendly)');
            $table->string('token_hash', 255)->comment('SHA256 for security');
            $table->smallInteger('max_uses')->unsigned()->default(1);
            $table->smallInteger('uses_count')->unsigned()->default(0);
            $table->timestamp('expires_at');
            $table->uuid('created_by');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('code', 'idx_code');
            $table->index('token_hash', 'idx_token_hash');
            $table->index('family_id', 'idx_family');
            $table->index('expires_at', 'idx_expires');

            $table->foreign('family_id')->references('id')->on('families')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_invites');
    }
};
