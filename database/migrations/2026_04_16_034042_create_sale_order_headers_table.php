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
        Schema::create('sale_order_headers', function (Blueprint $table) {
            $table->id();
            $table->string('document_no')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();

            $table->date('posting_date');
            $table->date('order_date')->nullable(); // add
            $table->date('delivery_date')->nullable(); // add

            // Amount
            $table->decimal('total_amount', 18, 6)->default(0);
            $table->decimal('vat_amount', 18, 6)->default(0);
            $table->decimal('discount_percent', 12, 4)->default(0); // optional upgrade
            $table->decimal('discount_amount', 18, 6)->default(0);
            $table->decimal('grand_total', 18, 6)->default(0);

            // Payment tracking
            $table->decimal('deposit_amount', 18, 6)->default(0);
            $table->decimal('paid_amount', 18, 6)->default(0);
            $table->decimal('balance_amount', 18, 6)->default(0);

            $table->enum('status', [
                'Draft',
                'Quotation',
                'Ordered',
                'Deposit',
                'Completed',
                'Cancelled',
                'Returned'
            ])->default('Draft');

            $table->enum('payment_status', [
                'Unpaid',
                'Partial',
                'Paid',
                'Refunded',
                'N/A'
            ])->default('Unpaid');

            $table->enum('delivery_status', [
                'Pending',
                'Processing',
                'Shipped',
                'Delivered',
                'Cancelled',
                'Returned',
                'N/A'
            ])->nullable();
            $table->string('delivery_info')->nullable(); // Nham24 / Grab / Own Driver
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();



            // Extra
            $table->text('customer_type')->nullable();
            $table->text('payment_method')->nullable();

            $table->text('currency_name')->nullable();
            $table->decimal('factor', 15, 6)->default(1);

            $table->text('remarks')->nullable();
               $table->text('return_remarks')->nullable();
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
        Schema::dropIfExists('sale_order_headers');
    }
};
