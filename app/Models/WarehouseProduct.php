<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WarehouseProduct extends Model
{
    protected $table = 'warehouse_product'; // your pivot table
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'bin_id',
        'original_quantity',
        'quantity',
        'track_lot',
        'lot',
        'expire',
        'cost',
        'control_exp',
        'created_at',
        'updated_at',
    ];
    public $timestamps = true; // optional but recommended

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
        // ADD THIS — resolves "undefined relationship [product]"
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function bin()
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

}
