<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationLine extends Model
{
    protected $table = 'quotation_lines';

    protected $fillable = [
        'quotation_id',
        'product_id',
        'barcode',
        'item_code',
        'name',
        'variant',
        'description',
        'quantity',
        'unit',
        'category_name',
        'cost',
        'unit_price',
        'sell_price',
        'discount_percent',
        'discount_amount',
        'line_amount',
        'vat',
        'vat_amount',
        'net_amount',
        'grand_total_amount',
        'created_by',
    ];

    public function header()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
