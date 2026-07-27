<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'product';
    protected $appends = ['stock'];
    // Mass assignable fields
    protected $fillable = [
        'category_id',
        'warehouse_id',
        'type',
        'bar_code',
        'code',
        'name',
        'variant',
        'description',
        'min_stock',
        'max_stock',
        'track_stock',
        'sell_price',
        'cost',
        'vat',
        'discount_percent',
        'last_purchase_price',
        'allow_discount',
        'allow_return',
        'image',
         'category_name',
        'unit',
        'Tax',
        'status',
        'created_by',
    ];

    // Cast types for proper handling
    protected $casts = [
        'track_stock' => 'boolean',
        'allow_discount' => 'boolean',
        'allow_return' => 'boolean',
        'status' => 'boolean',
        'sell_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'vat' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
    ];



    public function category()
    {
        return $this->belongsTo(Category::class);
    }


    public function warehouses()
    {
        return $this->belongsToMany(
            Warehouse::class,    // Related model
            'warehouse_product'  // Pivot table name (exact table name!)
        )
        ->withPivot('quantity')
        ->withTimestamps();
    }
    public function getStockAttribute()
{
    return $this->warehouses->sum(function ($warehouse) {
        return $warehouse->pivot->quantity ?? 0;
    });
}

}
