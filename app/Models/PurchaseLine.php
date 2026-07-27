<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseLine extends Model
{
    protected $table = 'purchase_lines';

    protected $fillable = [
        'document_no',
        'product_id',
        'barcode',
        'item_code',
        'name',
        'variant',
        'description',
        'quantity',
        'unit',
        // ✅ FIX HERE
        'lot',
        'expire_date',
        'category_name',
        'unit_cost',
        'line_amount',
        'created_by',
        'remark',
    ];
    public function header()
    {
        return $this->belongsTo(PurchaseHeader::class, 'document_no', 'no');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
