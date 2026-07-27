<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use LogsActivity;

    protected string $activitySection = 'customer';

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
