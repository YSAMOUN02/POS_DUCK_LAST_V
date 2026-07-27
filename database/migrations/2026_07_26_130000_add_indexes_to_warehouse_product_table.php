<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// warehouse_product is looked up by product_id and warehouse_id on every stock
// read (POS product grid, stock reports, adjustments), but the create migration
// only indexed bin_id. Databases restored from the older production dump got
// these indexes implicitly via foreign keys; a fresh migrate did not, so stock
// lookups there were full scans. Adds them explicitly for both cases.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_product', function (Blueprint $table) {
            foreach (['product_id', 'warehouse_id'] as $column) {
                if (!$this->hasIndexOn($column)) {
                    $table->index($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_product', function (Blueprint $table) {
            foreach (['product_id', 'warehouse_id'] as $column) {
                if ($this->hasIndexNamed("warehouse_product_{$column}_index")) {
                    $table->dropIndex("warehouse_product_{$column}_index");
                }
            }
        });
    }

    /**
     * True when any index already leads with this column — covers the
     * FK-generated *_foreign indexes present on dump-restored databases, so we
     * don't stack a redundant duplicate on top of them.
     */
    private function hasIndexOn(string $column): bool
    {
        return collect(Schema::getIndexes('warehouse_product'))
            ->contains(fn(array $index) => ($index['columns'][0] ?? null) === $column);
    }

    private function hasIndexNamed(string $name): bool
    {
        return collect(Schema::getIndexes('warehouse_product'))
            ->contains(fn(array $index) => $index['name'] === $name);
    }
};
