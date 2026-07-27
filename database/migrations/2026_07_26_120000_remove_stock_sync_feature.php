<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Removes the stock-sync-from-ERP feature (App\Services\StockSyncService and
// friends, deleted from the codebase) and everything it created: its tables,
// the warehouses.last_stock_entry watermark column, and the
// purchasing.stock_sync permission row (permission_user rows cascade-delete
// with it).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('key', 'purchasing.stock_sync')->delete();

        Schema::dropIfExists('stock_sync_runs');
        Schema::dropIfExists('GetStock');

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'last_stock_entry')) {
                $table->dropColumn('last_stock_entry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'last_stock_entry')) {
                $table->string('last_stock_entry')->nullable();
            }
        });

        Schema::create('GetStock', function (Blueprint $t) {
            $t->id();
            $t->string('LocationCode');
            $t->unsignedBigInteger('EntryNo')->index();
            $t->string('ItemNo');
            $t->string('ItemName')->nullable();
            $t->string('ItemDescription2')->nullable();
            $t->decimal('RemainingQuantity', 18, 6)->default(0);
            $t->string('LotNo')->nullable();
            $t->decimal('Quantity', 18, 6)->default(0);
            $t->date('LotExpiry')->nullable();
            $t->string('ItemUnit')->nullable();
            $t->string('ItemCategoryCode')->nullable();
            $t->string('VariantCode')->nullable();
            $t->string('DocumentNo')->nullable();
            $t->string('SourceNo')->nullable();
            $t->date('PostingDate')->nullable();
            $t->decimal('ItemUnitPrice', 18, 6)->default(0);
            $t->decimal('ItemUnitCost', 18, 6)->default(0);
            $t->decimal('ItemLastCost', 18, 6)->default(0);
            $t->decimal('ItemMinStock', 18, 6)->default(0);
            $t->decimal('ItemMaxStock', 18, 6)->default(0);
            $t->boolean('ItemAllowDisc')->default(false);
            $t->boolean('ItemBlocked')->default(false);
        });

        Schema::create('stock_sync_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('status')->default('queued');
            $t->integer('total')->default(0);
            $t->integer('done')->default(0);
            $t->integer('added')->default(0);
            $t->integer('skipped')->default(0);
            $t->json('skipped_items')->nullable();
            $t->text('message')->nullable();
            $t->timestamps();
        });
    }
};
