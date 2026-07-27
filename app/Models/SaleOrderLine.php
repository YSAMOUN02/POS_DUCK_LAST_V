<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleOrderLine extends Model
{
    protected $table = 'sale_order_lines';

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'barcode',
        'document_no',
        'item_code',
        'name',
        'variant',
        'description',
        'quantity',
        'quantity_shiped',
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
    ];

    public function header()
    {
        return $this->belongsTo(SaleOrderHeader::class, 'sale_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
