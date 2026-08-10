<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    protected $table = 'product';

    /**
     * Items allowed to appear in the POS sales grid and its search.
     *
     * Goods and services only — 'expence' items belong to the expense flow,
     * not to a sale. Admins keep the unrestricted view they have always had.
     *
     * This rule previously existed in three copies with three different role
     * tests, so a supervisor saw expense items when searching but not when
     * browsing, and could add one to a sale from the search results. One
     * definition now, used by every caller.
     */
    public function scopeSellableForCurrentUser($query)
    {
        if (Auth::user()?->role === 'admin') {
            return $query;
        }

        return $query->whereIn('type', ['product', 'service']);
    }
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
