<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The live purchase_headers table predates location_id/location_name being
// added to 2026_04_03_100552_create_purchase_headers_table (that migration
// shows as "Ran" but the columns were never actually applied to this DB).
// Used by the manual Purchase Order flow (PurchasingController, PurchaseCart).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_headers', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('posting_date');
            }
            if (!Schema::hasColumn('purchase_headers', 'location_name')) {
                $table->text('location_name')->nullable()->after('location_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_headers', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_headers', 'location_id')) {
                $table->dropColumn('location_id');
            }
            if (Schema::hasColumn('purchase_headers', 'location_name')) {
                $table->dropColumn('location_name');
            }
        });
    }
};
