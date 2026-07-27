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
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();

            $table->string('barcode')->nullable();
            $table->string('item_code')->nullable();
            $table->string('name')->nullable();
            $table->string('variant')->nullable();
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->string('category_name')->nullable();

            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('cost', 18, 6)->default(0);
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->decimal('sell_price', 18, 6)->default(0);

            $table->decimal('discount_percent', 12, 4)->default(0);
            $table->decimal('discount_amount', 18, 6)->default(0);
            $table->decimal('line_amount', 18, 6)->default(0);
            $table->decimal('vat', 12, 4)->default(0);
            $table->decimal('vat_amount', 18, 6)->default(0);
            $table->decimal('net_amount', 18, 6)->default(0);
            $table->decimal('grand_total_amount', 18, 6)->default(0);

            $table->string('created_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
    }
};
