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
        Schema::create('purchase_headers', function (Blueprint $table) {
            $table->id();
            $table->string('no')->unique();
            $table->unsignedBigInteger('vendor_id')->nullable();

            // ✅ changed here
            $table->date('posting_date');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->text('location_name')->nullable();
            $table->text('currency_name')->nullable();
            $table->decimal('factor', 15, 6)->default(1);

            $table->string('payment_method')->nullable();
            $table->text('remark')->nullable();

            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_headers');
    }
};
