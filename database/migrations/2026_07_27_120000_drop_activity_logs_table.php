<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the Change Log feature's table. The model, trait, controller, route
 * and UI were removed with it, so nothing reads or writes this table any more.
 *
 * down() recreates the original structure from
 * 2026_07_20_044806_create_activity_logs_table (deleted in the same change) so
 * the migration stays reversible, but the rows themselves are not recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_logs');
    }

    public function down(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 150)->nullable();
            $table->string('action', 20);
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('section', 50)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('section');
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
