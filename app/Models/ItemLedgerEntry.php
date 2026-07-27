<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ItemLedgerEntry extends Model
{


    protected $table = 'item_ledger_entries';

    protected $fillable = [
        'entry_no',
        'posting_date',

        'document_type',
        'document_no',
        'source_no',
        'source_id',
        'source_table',

        'product_id',
        'barcode',
        'item_code',
        'name',
        'variant',
        'description',
        'unit',
        'category_name',
        'type',

        'warehouse_id',
        'warehouse_name',
        'bin_id',
        'bin_name',
        'lot',
        'expire_date',

        'quantity',
        'remaining_quantity',
        'entry_type',

        'unit_cost',
        // Inventory value of the movement: |quantity| x |unit_cost|, always
        // positive. Sales value stays in line_amount / net_amount /
        // grand_total_amount — this column is the cost side only.
        'cost_amount',
        'unit_price',
        'sell_price',
        'discount_percent',
        'discount_amount',
        'vat',
        'vat_amount',
        'line_amount',
        'net_amount',
        'grand_total_amount',
        'factor',
        'currency_name',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'vendor_id',
        'vendor_name',
        'payment_method',
        'remark',
        'created_by',
        'created_user_id'
    ];

   protected $casts = [
    'posting_date'         => 'date',
    'expire_date'          => 'date',
    'returned_at'          => 'datetime',

    'quantity'             => 'decimal:6',
    'remaining_quantity'   => 'decimal:6',

    'unit_cost'            => 'decimal:6',
    'unit_price'           => 'decimal:6',
    'sell_price'           => 'decimal:6',

    'discount_percent'     => 'decimal:4',
    'discount_amount'      => 'decimal:6',

    'vat'                  => 'decimal:4',
    'vat_amount'           => 'decimal:6',

    'line_amount'          => 'decimal:6',
    'net_amount'           => 'decimal:6',
    'grand_total_amount'   => 'decimal:6',
];
// ItemLedgerEntry.php
public function product()
{
    return $this->belongsTo(Product::class, 'product_id', 'id');
}
}
