<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableQueue extends Model
{

    protected $table = 'table_queues';
    protected $fillable = [
        'queue_date',
        'last_number',
    ];
}
