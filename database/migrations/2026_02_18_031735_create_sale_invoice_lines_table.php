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
        Schema::create('sale_invoice_lines', function (Blueprint $table) {
            $table->id();

            // References
            $table->unsignedBigInteger('sale_invoice_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('document_no')->nullable();
            // Item snapshot
            $table->string('barcode')->nullable();
            $table->string('item_code');
            $table->string('name');
            $table->string('variant')->nullable();
            $table->longText('description')->nullable();

            // Quantity
            $table->decimal('quantity', 18, 6)->default(0);
            $table->string('unit')->nullable();
            $table->string('category_name')->nullable();

            // Pricing snapshot
            $table->decimal('cost', 18, 6)->default(0);
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->decimal('sell_price', 18, 6)->default(0);

            // Discount
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->decimal('discount_amount', 18, 6)->default(0);

            // Totals
            $table->decimal('line_amount', 18, 6)->default(0);
            $table->decimal('vat', 5, 4)->default(0);
            $table->decimal('vat_amount', 18, 6)->default(0);
            $table->decimal('net_amount', 18, 6)->default(0);
            $table->decimal('grand_total_amount', 18, 6)->default(0);
            $table->string('created_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('sale_invoice_id')
                ->references('id')
                ->on('sale_invoice_headers')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('product') // change to products if your table is plural
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_invoice_lines');
    }
};
