<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_ledger_entries', function (Blueprint $table) {
            // No hard FK — mirrors how warehouse_name is already stored
            // denormalized alongside warehouse_id on this table.
            $table->unsignedBigInteger('bin_id')->nullable();
            $table->string('bin_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['bin_id', 'bin_name']);
        });
    }
};
