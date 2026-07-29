<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restores cost on stock rows that a transfer created without one.
 *
 * Stock on Hand values inventory as warehouse_product.quantity * .cost. The
 * receiving side of a transfer inserted its row without carrying the cost
 * across, so transferred stock sat at cost 0 and reported a value of nothing —
 * a warehouse could hold 46 units and show $0.00.
 *
 * The transfer code now copies the cost. This repairs rows already written.
 * The ledger kept the real figure the whole time (every Transfer Receipt row
 * carries its unit_cost), so the value is recovered from there, falling back to
 * the product's own cost when no ledger row survives.
 *
 * Only rows with no cost are touched — an existing cost is whatever the stock
 * is already being reported at and must not be overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('warehouse_product')
            ->where(fn($q) => $q->whereNull('cost')->orWhere('cost', 0))
            ->where('quantity', '<>', 0)
            ->get(['id', 'product_id', 'warehouse_id', 'lot']);

        foreach ($rows as $row) {
            // Prefer a cost posted against this exact lot, newest first.
            $cost = DB::table('item_ledger_entries')
                ->where('product_id', $row->product_id)
                ->when(
                    $row->lot !== null && $row->lot !== '',
                    fn($q) => $q->where('lot', $row->lot),
                    fn($q) => $q->whereNull('lot')
                )
                ->where('unit_cost', '>', 0)
                ->orderByDesc('id')
                ->value('unit_cost');

            // Then any cost for the product at all, then the product record.
            $cost = $cost
                ?: DB::table('item_ledger_entries')
                    ->where('product_id', $row->product_id)
                    ->where('unit_cost', '>', 0)
                    ->orderByDesc('id')
                    ->value('unit_cost')
                ?: DB::table('product')->where('id', $row->product_id)->value('cost');

            if ($cost > 0) {
                DB::table('warehouse_product')->where('id', $row->id)->update([
                    'cost'       => $cost,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Not reversible: which rows were previously 0 is not recorded anywhere, and
     * blanking costs again would only restore the bug.
     */
    public function down(): void
    {
        //
    }
};
