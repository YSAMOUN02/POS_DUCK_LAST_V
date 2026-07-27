<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts every MyISAM table to InnoDB.
 *
 * MyISAM silently ignores transactions. Because sale_order_headers,
 * sale_invoice_headers/lines and item_ledger_entries were MyISAM, a rejected
 * sale — insufficient stock, missing product, any thrown error — still left the
 * header rows committed while the InnoDB tables (sale_order_lines,
 * warehouse_product) correctly rolled back. That is the source of the
 * "sale order header with no lines" records: DB::transaction() had no effect on
 * half the tables in the sale flow.
 *
 * MyISAM also has no row-level locking, so lockForUpdate() was a no-op there —
 * concurrent sales could interleave on the ledger.
 *
 * Converting also makes the existing foreign keys enforceable, which MyISAM
 * accepts syntactically but never applies.
 */
return new class extends Migration
{
    /** Tables observed as MyISAM; guarded by a live engine check so it's safe to re-run. */
    private const TABLES = [
        'sale_order_headers',
        'sale_invoice_headers',
        'sale_invoice_lines',
        'item_ledger_entries',
        'pos_profiles',
        'personal_access_tokens',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if ($this->engineOf($table) === 'InnoDB') {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
        }
    }

    /**
     * Deliberately empty. Going back to MyISAM would re-introduce the
     * data-integrity bug this migration exists to fix, and no application code
     * depends on the old engine.
     */
    public function down(): void
    {
        // no-op on purpose — see above
    }

    private function engineOf(string $table): ?string
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->value('ENGINE');
    }
};
