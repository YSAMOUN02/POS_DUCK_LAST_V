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
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();

            // 🔗 Link to header (using document_no)
            $table->string('document_no');

            $table->unsignedBigInteger('product_id')->nullable();

            $table->string('barcode')->nullable();
            $table->string('item_code')->nullable();
            $table->string('name');
            $table->string('variant')->nullable();
            $table->text('description')->nullable();

            $table->decimal('quantity', 18, 6)->default(0);
            $table->string('unit')->nullable();
            $table->string('lot')->nullable();
            $table->date('expire_date')->nullable();
            $table->string('category_name')->nullable();

            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('line_amount', 18, 6)->default(0);

            $table->text('remark')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            // ⚡ Index for performance
            $table->index('document_no');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};
