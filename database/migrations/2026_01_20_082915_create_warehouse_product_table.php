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
        Schema::create('warehouse_product', function (Blueprint $table) {
            $table->id();

            // Link to the correct tables
   $table->unsignedBigInteger('product_id');
$table->unsignedBigInteger('warehouse_id');

            // Quantity
            $table->integer('original_quantity')->default(0);

            $table->decimal('quantity', 18, 6)->default(0);
            // Lot and expiration
            $table->boolean('track_lot')->default(1);
            $table->string('lot')->nullable();
            $table->date('expire')->nullable();
            $table->decimal('cost', 18, 6)->default(0);
            // Control flag: 1 = active, 0 = expired / blocked
            $table->boolean('control_exp')->default(1);
            $table->string('created_by')->nullable();
            $table->timestamps();

            // Make sure each product-warehouse combo is unique

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_product');
    }
};
