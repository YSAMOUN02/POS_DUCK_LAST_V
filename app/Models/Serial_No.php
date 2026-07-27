<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Serial_No extends Model
{
   protected $table = 'serial_no'; // 👈 important (not default plural)

    protected $fillable = [
        'prefix',
        'type',
        'current_no',
        'last_reset_date',
    ];
  protected $casts = [
        'last_reset_date' => 'date',
    ];
}
