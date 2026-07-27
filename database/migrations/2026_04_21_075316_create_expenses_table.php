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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('expense_code')->nullable();
            $table->string('expense_name');
            $table->decimal('qty', 18, 6)->default(1);
      
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->decimal('amount', 18, 6)->default(0);
            $table->string('payment_method')->nullable();

            $table->text('currency_name')->nullable();
            $table->decimal('factor', 15, 6)->default(1);
            $table->text('note')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
