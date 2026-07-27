<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use LogsActivity;

    protected string $activitySection = 'user';

    protected $fillable = ['section', 'action', 'key', 'label'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'permission_user');
    }
}
