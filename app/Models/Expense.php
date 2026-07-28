<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'expenses';

    protected $fillable = [
        'expense_date',
        'product_id',
        'expense_code',
        'expense_name',
        'qty',
        'unit_price',
        'amount',
        'factor',
        'currency_name',
        'payment_method',
        'note',
        'status',
        'created_by',
        'refunded_from_id',
        'refund_reason',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'qty' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'amount' => 'decimal:6',
        'status' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** The expense this row refunds, when it is a refund mirror. */
    public function refundedFrom()
    {
        return $this->belongsTo(self::class, 'refunded_from_id');
    }

    /** Refund mirrors written against this expense. */
    public function refunds()
    {
        return $this->hasMany(self::class, 'refunded_from_id');
    }

    /** A refund carries a negative amount; the original is always positive. */
    public function isRefund(): bool
    {
        return $this->refunded_from_id !== null;
    }

    /**
     * How much of this expense has NOT yet been refunded.
     *
     * Refund amounts are stored negative, so summing them and adding gives what
     * is left — and a second refund can never take the total below zero.
     */
    public function refundableAmount(): float
    {
        if ($this->isRefund()) {
            return 0.0;
        }

        $alreadyRefunded = (float) $this->refunds()->sum('amount'); // negative
        return max(0.0, round((float) $this->amount + $alreadyRefunded, 6));
    }
}
