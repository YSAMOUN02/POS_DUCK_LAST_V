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

    /**
     * Which warehouses the POS grid should report stock for.
     *
     * The grid used to always show one combined total across every warehouse
     * the user could reach, which stopped matching reality once a sale was
     * pinned to a single warehouse. Passing the selected warehouse narrows the
     * figures to the stock the sale will actually draw from.
     *
     * The id arrives from the browser, so it is honoured only when the user
     * genuinely holds that warehouse; anything else falls back to all of theirs
     * rather than being trusted.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function stockScopeFor($user, $requestedId = null)
    {
        $ids = $user->warehouses->pluck('id');

        if ($requestedId !== null && $requestedId !== '' && $ids->contains((int) $requestedId)) {
            return collect([(int) $requestedId]);
        }

        return $ids;
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
