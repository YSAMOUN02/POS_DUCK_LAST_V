<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PurchaseHeader extends Model
{
    use LogsActivity;

    protected string $activitySection = 'purchasing';

    protected $table = 'purchase_headers';

    protected $fillable = [
        'no',
        'vendor_id',
        'posting_date',
        'factor',
        'deposit_amount',
        'currency_name',
        'payment_method',
        'created_by',
        // PurchaseHeader
        'location_id',
        'location_name',
        'remark',
        'created_user_id',
    ];
    public function lines()
    {
        return $this->hasMany(PurchaseLine::class, 'document_no', 'no');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }
}
