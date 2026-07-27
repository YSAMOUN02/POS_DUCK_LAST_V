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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no')->nullable();

            $table->string('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();

            $table->date('quotation_date')->nullable();
            $table->date('valid_until')->nullable();

            $table->decimal('total_amount', 18, 6)->default(0);
            $table->decimal('discount_percent', 12, 4)->default(0);
            $table->decimal('discount_amount', 18, 6)->default(0);
            $table->decimal('vat_amount', 18, 6)->default(0);
            $table->decimal('grand_total', 18, 6)->default(0);

            $table->string('currency_name')->nullable();
            $table->decimal('factor', 15, 6)->default(1);

            $table->enum('status', ['Quotation', 'Completed', 'Cancelled'])->default('Quotation');

            $table->text('remarks')->nullable();

            $table->string('created_by')->nullable();
            $table->string('created_user_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
