<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use LogsActivity;

    protected string $activitySection = 'category';

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
