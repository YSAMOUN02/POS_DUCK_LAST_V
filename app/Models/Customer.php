<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

protected $fillable = [
    'customer_code',
    'name',
    'phone',
    'email',
    'address1',
    'address2',
    'contact_name',
    'contact_phone',
    'city',
    'country',
    'type',
    'discount_percent',
    'point',
    'status',
    'created_by', 
];

}
