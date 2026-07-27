<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resets transactional data back to a testing baseline while leaving master
 * data (users, products, warehouses, vendors, customers, permissions,
 * currencies, POS print profiles) untouched — so the app stays usable
 * immediately after a reset without re-seeding anything.
 *
 * Truncation runs with FOREIGN_KEY_CHECKS off inside a try/finally, because
 * several of these tables are FK targets and MySQL refuses TRUNCATE on a
 * referenced table even when the referencing table is emptied in the same run.
 */
class TruncateData extends Command
{
    protected $signature = 'pos:truncate
        {--baseline    : Stock only — the client baseline (empty stock, all history kept)}
        {--fresh       : Every transactional group — full clean slate, master data kept}
        {--groups=     : Comma-separated groups (see --list)}
        {--list        : Show every group and its tables, then exit}
        {--backup      : mysqldump the database before truncating}
        {--force       : Skip the confirmation prompt}';

    protected $description = 'Truncate transactional tables back to a testing baseline (master data is never touched)';

    /**
     * Group => tables. Order within a group does not matter (FK checks are
     * disabled), but grouping mirrors how the app writes data so a group can
     * be reset without leaving half a transaction behind.
     */
    private const GROUPS = [
        // serial_no is NOT here: it holds the document-number counters, not
        // stock. Truncating it alongside warehouse_product reset every sequence
        // while the documents they numbered stayed put, so the next sale would
        // re-issue SO26-0001 on top of an existing order.
        'stock'     => ['warehouse_product'],
        'ledger'    => ['item_ledger_entries'],
        'sales'     => ['sale_order_headers', 'sale_order_lines', 'sale_invoice_headers', 'sale_invoice_lines'],
        'purchases' => ['purchase_headers', 'purchase_lines'],
        'quotes'    => ['quotations', 'quotation_lines'],
        'expenses'  => ['expenses'],
        'queues'    => ['table_queues'],
        'system'    => ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'],
    ];

    /** Master data — never truncated by this command, listed so --list can say so out loud. */
    private const PROTECTED_TABLES = [
        'users', 'user_warehouse', 'warehouses', 'product', 'categories',
        'currencies', 'vendors', 'customers', 'pos_profiles', 'bins',
        'permissions', 'permission_user', 'migrations',
        'password_reset_tokens', 'personal_access_tokens',
        'serial_no',
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->showGroups();
            return self::SUCCESS;
        }

        $groups = $this->resolveGroups();
        if ($groups === null) {
            return self::FAILURE;
        }

        $tables = $this->existingTables($groups);
        if ($tables === []) {
            $this->warn('Nothing to truncate — no matching tables exist.');
            return self::SUCCESS;
        }

        $database = DB::connection()->getDatabaseName();
        $this->newLine();
        $this->line("  Database: <fg=yellow>{$database}</>");
        $this->line('  Groups:   <fg=yellow>' . implode(', ', $groups) . '</>');
        $this->newLine();
        $this->table(
            ['Table', 'Rows to delete'],
            array_map(fn($t) => [$t, number_format(DB::table($t)->count())], $tables)
        );

        if (!$this->option('force') && !$this->confirm("Truncate these " . count($tables) . " table(s) in '{$database}'?", false)) {
            $this->line('Aborted — nothing was changed.');
            return self::SUCCESS;
        }

        if ($this->option('backup') && !$this->backup($database)) {
            return self::FAILURE;
        }

        $this->truncate($tables);

        $this->newLine();
        $this->info('Done — ' . count($tables) . ' table(s) truncated. Master data untouched.');
        return self::SUCCESS;
    }

    /** @return string[]|null Selected group names, or null when the input was invalid. */
    private function resolveGroups(): ?array
    {
        if ($this->option('fresh')) {
            // Everything except 'system' — clearing sessions would log the
            // operator out mid-test, which is rarely what "fresh data" means.
            return array_values(array_diff(array_keys(self::GROUPS), ['system']));
        }

        if ($this->option('baseline')) {
            return ['stock'];
        }

        $raw = trim((string) $this->option('groups'));
        if ($raw === '') {
            $this->error('Pick what to reset: --baseline, --fresh, or --groups=stock,sales');
            $this->line('Run <fg=yellow>php artisan pos:truncate --list</> to see every group.');
            return null;
        }

        $groups = array_filter(array_map('trim', explode(',', $raw)));
        $unknown = array_diff($groups, array_keys(self::GROUPS));
        if ($unknown !== []) {
            $this->error('Unknown group(s): ' . implode(', ', $unknown));
            $this->line('Valid groups: ' . implode(', ', array_keys(self::GROUPS)));
            return null;
        }

        return array_values(array_unique($groups));
    }

    /**
     * Flattens the selected groups to tables that actually exist, so a group
     * stays usable on databases where an optional table was never created.
     *
     * @param  string[]  $groups
     * @return string[]
     */
    private function existingTables(array $groups): array
    {
        $tables = [];
        foreach ($groups as $group) {
            foreach (self::GROUPS[$group] as $table) {
                if (Schema::hasTable($table)) {
                    $tables[] = $table;
                } else {
                    $this->line("  <fg=gray>skipping {$table} — table does not exist</>");
                }
            }
        }

        return array_values(array_unique($tables));
    }

    /** @param string[] $tables */
    private function truncate(array $tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line("  <fg=green>✓</> {$table}");
            }
        } finally {
            // Always restore FK enforcement, even if one table blows up —
            // leaving it off would silently allow orphan rows afterwards.
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function backup(string $database): bool
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $this->error("Could not create backup directory: {$dir}");
            return false;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $database . '_' . now()->format('Y-m-d_His') . '.sql';
        $config = config('database.connections.' . config('database.default'));

        $command = sprintf(
            '%s --host=%s --port=%s --user=%s %s %s > %s',
            escapeshellarg($this->mysqldumpPath()),
            escapeshellarg($config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            $config['password'] !== '' ? '--password=' . escapeshellarg($config['password']) : '',
            escapeshellarg($database),
            escapeshellarg($path)
        );

        $this->line('  Backing up…');
        exec($command . ' 2>&1', $output, $exit);

        if ($exit !== 0 || !is_file($path) || filesize($path) === 0) {
            $this->error('Backup failed — nothing was truncated.');
            $this->line('  ' . implode(PHP_EOL . '  ', $output));
            return false;
        }

        $this->info('  Backup: ' . $path . ' (' . number_format(filesize($path) / 1024) . ' KB)');
        return true;
    }

    /** Falls back to bare "mysqldump" when the XAMPP path isn't present (non-Windows, custom installs). */
    private function mysqldumpPath(): string
    {
        $xampp = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        return is_file($xampp) ? $xampp : 'mysqldump';
    }

    private function showGroups(): void
    {
        $this->newLine();
        $this->line('  <fg=yellow>Groups</> — combine with --groups=a,b');
        $this->newLine();
        foreach (self::GROUPS as $group => $tables) {
            $this->line(sprintf('  <fg=green>%-10s</> %s', $group, implode(', ', $tables)));
        }
        $this->newLine();
        $this->line('  <fg=yellow>Shortcuts</>');
        $this->line('  <fg=green>--baseline</> stock only — client baseline (empty stock, history kept)');
        $this->line('  <fg=green>--fresh</>    every group except system (full clean slate)');
        $this->newLine();
        $this->line('  <fg=yellow>Never truncated</> (master data)');
        $this->line('  <fg=gray>' . implode(', ', self::PROTECTED_TABLES) . '</>');
        $this->newLine();
    }
}
