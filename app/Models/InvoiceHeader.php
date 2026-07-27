<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceHeader extends Model
{
    use HasFactory;

    protected $table = 'sale_invoice_headers';


    protected $fillable = [
        'sale_order_id',
        'invoice_number',
         'source_no',
        'customer_id',
        'contact_name',
        'phone',
        'address',
        'invoice_date',
        'total_amount',
        'vat_amount',
        'discount_percent',
        'discount_amount',
        'grand_total',
        'customer_type',
        'payment_method',
        'currency_name',
        'factor',
        'remarks',
        'location_id',
        'location',
        'created_by',
          'created_user_id'
    ];
protected $casts = [
    'invoice_date'      => 'date',
    'total_amount'      => 'decimal:6',
    'vat_amount'        => 'decimal:6',
    'discount_percent'  => 'decimal:4',
    'discount_amount'   => 'decimal:6',
    'grand_total'       => 'decimal:6',
    'factor'            => 'decimal:6',
];

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class, 'sale_invoice_id');
    }

    /**
     * Customer relationship (if you have customers table)
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
