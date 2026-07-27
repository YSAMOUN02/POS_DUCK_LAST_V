<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'vendors';

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'address1',
        'address2',
        'country',
        'city',
        'email',
        'phone1',
        'phone2',
        'website',
        'status',
        'created_by'
    ];
}
