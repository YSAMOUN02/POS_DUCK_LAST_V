<?php

use App\Models\Serial_No;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes serial_no the source of every transaction number.
 *
 * Two things have to be true before the app can start drawing from it:
 *
 *  1. type must be unique — two rows for 'purchase' would each hand out their
 *     own sequence and duplicate GRN numbers.
 *  2. each counter must start ABOVE whatever is already in the table it
 *     numbers. serial_no is currently empty (it sits in pos:truncate's stock
 *     group, so a stock reset wiped it), while purchase_headers still holds
 *     GRN26-0001 — without seeding, the next receipt would re-issue that number
 *     straight onto an existing document.
 */
return new class extends Migration
{
    /** type => [table, column, prefix] to read the existing high-water mark from. */
    private const SOURCES = [
        'sale_order' => ['sale_order_headers',   'document_no',    'SO'],
        'invoice'    => ['sale_invoice_headers', 'invoice_number', 'INV'],
        'quotation'  => ['quotations',           'quotation_no',   'QUOT'],
        'purchase'   => ['purchase_headers',     'no',             'GRN'],
        'transfer'   => ['item_ledger_entries',  'document_no',    'TO'],
        'adjustment' => ['item_ledger_entries',  'document_no',    'ADJ'],
        'expense'    => ['expenses',             'expense_code',   'EXP'],
    ];

    public function up(): void
    {
        Schema::table('serial_no', function (Blueprint $table) {
            $table->unique('type');
        });

        $year = now()->format('y');

        foreach (Serial_No::TYPES as $type => [$prefix, $width]) {
            $highest = 0;

            if (isset(self::SOURCES[$type])) {
                [$table, $column, $srcPrefix] = self::SOURCES[$type];

                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    $like = $srcPrefix . $year . '-%';

                    // Compare on the numeric suffix, not the string: ordering
                    // '...-9' against '...-10' lexically would pick the wrong row.
                    $highest = (int) DB::table($table)
                        ->where($column, 'like', $like)
                        ->selectRaw('MAX(CAST(SUBSTRING_INDEX(' . $column . ', "-", -1) AS UNSIGNED)) as n')
                        ->value('n');
                }
            }

            Serial_No::updateOrCreate(
                ['type' => $type],
                [
                    'prefix'          => $prefix,
                    'current_no'      => $highest,
                    'last_reset_date' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('serial_no', function (Blueprint $table) {
            $table->dropUnique(['type']);
        });

        Serial_No::whereIn('type', array_keys(Serial_No::TYPES))->delete();
    }
};
