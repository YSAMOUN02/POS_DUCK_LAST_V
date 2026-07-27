<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'warehouses';
    protected $fillable = ['name', 'location', 'status', 'note', 'created_by'];
    protected $casts = ['status' => 'boolean'];

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
