<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restrict a warehouse to one side of the business.
 *
 * 'sale'     — sellable from the POS, never a purchase destination
 * 'purchase' — goods can be received into it, never sold from
 * 'both'     — unrestricted (the default, and what every existing row becomes,
 *              so nothing changes behaviour until a warehouse is deliberately
 *              narrowed)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->enum('type', ['both', 'sale', 'purchase'])
                ->default('both')
                ->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
