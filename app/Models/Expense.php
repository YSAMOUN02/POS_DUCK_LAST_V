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
}
