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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Basic info
            $table->string('customer_code')->unique()->nullable(); // CUS0001
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Address
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
             $table->string('bank_no')->nullable();
            // POS related
            $table->enum('type', ['walk_in', 'member', 'vip'])->default('walk_in');
            $table->decimal('discount_percent', 12, 2)->default(0);  //Allow pay later

            $table->integer('point')->default(0); // loyalty point

            // Status
            $table->boolean('status')->default(true);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
