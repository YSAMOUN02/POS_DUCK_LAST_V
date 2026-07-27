<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $table = 'quotations';

    protected $fillable = [
        'quotation_no',

        'customer_id',
        'customer_name',
        'phone',
        'address',

        'quotation_date',
        'valid_until',

        'total_amount',
        'discount_percent',
        'discount_amount',
        'vat_amount',
        'grand_total',

        'currency_name',
        'factor',

        'status',

        'remarks',
        'created_by',
        'created_user_id',
    ];

    public function lines()
    {
        return $this->hasMany(QuotationLine::class, 'quotation_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_user_id', 'id');
    }
}
