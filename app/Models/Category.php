<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Optional: relation to POS items
    public function posItems()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
