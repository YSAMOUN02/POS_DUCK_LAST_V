<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';
        // Allow mass assignment on these fields
    protected $fillable = ['code', 'factor','name'];
}
