<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SaleOrderHeader extends Model
{
    use LogsActivity;

    protected string $activitySection = 'pos_sale';

    protected $table = 'sale_order_headers';
    protected $fillable = [
        'document_no',

        'customer_id',
        'contact_name',
        'phone',
        'address',

        'posting_date',
        'delivery_date',
        'order_date',



        'total_amount',
        'vat_amount',
        'discount_percent',
        'discount_amount',
        'grand_total',

        'deposit_amount',
        'paid_amount',
        'balance_amount',

        'status',
        'payment_status',

        // Delivery
        'delivery_status',
        'delivery_info',
        'driver_name',
        'driver_phone',

        'customer_type',
        'payment_method',
        'currency_name',
        'factor',

        'remarks',
        'return_remarks',
        'location_id',
        'location',
        'created_by',
        'created_user_id'
    ];
    public function lines()
    {
        return $this->hasMany(SaleOrderLine::class, 'sale_order_id', 'id');
    }
    public function invoices()
    {
        return $this->hasMany(InvoiceHeader::class, 'sale_order_id');
    }
    public function ledgerEntries()
{
    return $this->hasMany(ItemLedgerEntry::class, 'source_no', 'document_no');
}
    public function user()
{
    return $this->belongsTo(User::class, 'created_user_id', 'id');
}
}
