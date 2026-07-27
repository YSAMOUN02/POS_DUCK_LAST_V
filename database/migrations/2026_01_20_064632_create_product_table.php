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
        Schema::create('product', function (Blueprint $table) {
            $table->id(); // id


            $table->string('bar_code')->nullable(); // bar_code
            $table->string('code'); // code
            $table->string('name'); // name
            $table->string('variant')->nullable(); // variant
            $table->text('description')->nullable(); // description

            $table->integer('min_stock')->default(0); // min_stock
            $table->integer('max_stock')->default(0); // max_stock
            $table->boolean('track_stock')->default(true); // track_stock

            $table->decimal('sell_price', 10, 2)->default(0); // sell_price
            $table->decimal('cost', 10, 2)->default(0); // cost
            $table->decimal('vat', 5, 2)->default(0); // vat
            $table->decimal('discount_percent', 5, 2)->default(0); // discount_percent
            $table->decimal('last_purchase_price', 10, 2)->nullable(); // last_purchase_price

            $table->boolean('allow_discount')->default(true); // allow_discount
            $table->boolean('allow_return')->default(true); // allow_return
            $table->string('image')->nullable(); // image
            $table->string('category_id')->nullable(); // category_id (can be string or foreign key)
            $table->string('category_name')->nullable(); // category_id (can be string or foreign key)
            $table->enum('type', ['service', 'product', 'expence'])->default('product');
            $table->string('unit')->nullable(); // unit
            $table->string('Tax')->nullable(); // Tax
            $table->boolean('status')->default(1); // status
            $table->string('created_by')->nullable();
            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
