<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separates inventory value from sales value on the ledger.
 *
 * Until now a stock movement had nowhere of its own to record what it was
 * worth, so a goods receipt wrote its cost into the SALES columns as a
 * negative (line_amount / net_amount / grand_total_amount = -cost) and the
 * reports flipped the sign back. cost_amount gives inventory its own column:
 * quantity x unit_cost, always positive, on every movement.
 *
 * The sales columns are left exactly as they are — GainCostController still
 * derives purchase spend from the negative line_amount, so clearing them here
 * would break every cost report in the same change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_ledger_entries', function (Blueprint $table) {
            $table->decimal('cost_amount', 18, 6)->default(0)->after('unit_cost');
        });

        // Backfill from what each row already carries, so historical movements
        // are valued the same way new ones will be.
        DB::table('item_ledger_entries')->update([
            'cost_amount' => DB::raw('ROUND(ABS(quantity) * ABS(unit_cost), 6)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('item_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('cost_amount');
        });
    }
};
