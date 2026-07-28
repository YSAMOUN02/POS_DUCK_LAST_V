<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'warehouses';
    protected $fillable = ['name', 'location', 'type', 'status', 'note', 'created_by'];
    protected $casts = ['status' => 'boolean'];

    /**
     * Warehouses usable on one side of the business.
     *
     * 'both' always qualifies — a warehouse is only excluded when it has been
     * deliberately narrowed to the other side, so existing setups are unaffected.
     *
     * @param  string  $side  'sale' or 'purchase'
     */
    public function scopeUsableFor($query, string $side)
    {
        return $query->whereIn('type', ['both', $side]);
    }

   public function products()
{
    return $this->belongsToMany(Product::class, 'warehouse_product')
                ->withPivot(['quantity', 'track_lot', 'lot', 'expire', 'control_exp', 'bin_id'])
                ->withTimestamps();
}

    public function bins()
    {
        return $this->hasMany(Bin::class, 'warehouse_id');
    }

}
