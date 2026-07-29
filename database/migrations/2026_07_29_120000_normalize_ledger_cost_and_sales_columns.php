<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Puts existing ledger rows onto the signed cost convention.
 *
 * cost_amount was written as an absolute value on every movement, with the
 * direction carried separately by entry_type, and inventory movements also
 * wrote their cost into the SALES columns so the reports could recover a
 * figure from them. That mixed inventory value into revenue columns and left
 * a purchase and a purchase return indistinguishable by value alone.
 *
 * The convention now is:
 *
 *   unit_cost    always positive — a unit cost has no direction
 *   cost_amount  signed inventory movement: stock in is positive
 *                (purchase, transfer receipt, sale return, adjustment up),
 *                stock out is negative (sale, purchase return, transfer
 *                shipment, adjustment down). Summing it gives real movement,
 *                and the two legs of a transfer cancel to zero.
 *   line_amount / net_amount / grand_total_amount
 *                sales value only. Zero on every inventory movement.
 *
 * Without this, rows written before the change keep the old signs and any
 * report spanning both periods silently mixes the two conventions.
 */
return new class extends Migration
{
    /** Movements that carry no sales value at all. */
    private array $inventoryDocTypes = [
        'Purchase',
        'Purchase Return',
        'Adjustment',
        'Transfer Shipment',
        'Transfer Receipt',
    ];

    public function up(): void
    {
        // entry_type is the one field that has always recorded direction
        // reliably, so the sign is rebuilt from it rather than from the
        // document type or the existing (unsigned) value.
        DB::table('item_ledger_entries')->update([
            'unit_cost'   => DB::raw('ABS(unit_cost)'),
            'cost_amount' => DB::raw(
                "ROUND(ABS(quantity) * ABS(unit_cost), 6)
                 * CASE WHEN entry_type = 'positive' THEN 1 ELSE -1 END"
            ),
        ]);

        DB::table('item_ledger_entries')
            ->whereIn('document_type', $this->inventoryDocTypes)
            ->update([
                'line_amount'        => 0,
                'net_amount'         => 0,
                'grand_total_amount' => 0,
            ]);
    }

    /**
     * Best effort only.
     *
     * The sales columns on inventory rows are cleared by up() and the values
     * that were there cannot be recovered, so this rebuilds them from cost the
     * way the old code derived them rather than restoring the originals.
     */
    public function down(): void
    {
        DB::table('item_ledger_entries')->update([
            'cost_amount' => DB::raw('ABS(cost_amount)'),
        ]);

        // Old convention: receipts carried negative sales value, returns positive.
        DB::table('item_ledger_entries')
            ->where('document_type', 'Purchase')
            ->update([
                'line_amount'        => DB::raw('-ABS(cost_amount)'),
                'net_amount'         => DB::raw('-ABS(cost_amount)'),
                'grand_total_amount' => DB::raw('-ABS(cost_amount)'),
            ]);

        DB::table('item_ledger_entries')
            ->where('document_type', 'Purchase Return')
            ->update([
                'line_amount'        => DB::raw('ABS(cost_amount)'),
                'net_amount'         => DB::raw('ABS(cost_amount)'),
                'grand_total_amount' => DB::raw('ABS(cost_amount)'),
            ]);
    }
};
