<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use LogsActivity;

    protected string $activitySection = 'purchasing';

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
