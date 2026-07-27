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
        Schema::create('pos_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('user_report')->unique();

            $table->string('company');
            $table->text('description')->nullable();

            $table->string('address1')->nullable();
            $table->string('address2')->nullable();

            $table->string('phone1')->nullable();
            $table->string('phone2')->nullable();

            $table->string('social')->nullable();
            $table->string('email')->nullable();

            $table->string('telegram')->nullable();
            $table->string('seller')->nullable();

            $table->string('customer_name')
                ->default('អតិថិជនទូទៅ');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_profiles');
    }
};
