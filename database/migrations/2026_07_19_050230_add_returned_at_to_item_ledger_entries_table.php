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
            // marks a sale's negative (deduction) entry as already returned,
            // so Sale Return can never process the same lot-deduction twice
            $table->timestamp('returned_at')->nullable()->after('remaining_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('returned_at');
        });
    }
};
