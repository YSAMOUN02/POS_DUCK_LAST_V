<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_headers', function (Blueprint $table) {
            $table->decimal('deposit_amount', 15, 6)->default(0)->after('factor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_headers', function (Blueprint $table) {
            $table->dropColumn('deposit_amount');
        });
    }
};
