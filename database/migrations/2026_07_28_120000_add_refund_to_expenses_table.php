<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense refunds.
 *
 * A refund is stored as a MIRROR row with a negative amount, not as a flag on
 * the original — the same shape purchase and sale returns already use here. It
 * means every report that sums expenses nets the refund automatically, with no
 * report changes at all, and a partial refund is just a smaller mirror.
 *
 * refunded_from_id makes the relationship explicit so a refund can be found,
 * limited, and shown against its original rather than inferred from the sign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('refunded_from_id')->nullable()->after('id');
            $table->string('refund_reason')->nullable()->after('note');

            $table->index('refunded_from_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['refunded_from_id']);
            $table->dropColumn(['refunded_from_id', 'refund_reason']);
        });
    }
};
