<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasApiTokens;
    use LogsActivity;

    protected string $activitySection = 'user';
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',   // ✅ ADD THIS
        'email',
        'password',
        'role',
        'status',
        'created_by',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    /**
     * Admins are super-users and always bypass permission checks —
     * no permission rows are ever seeded for them.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        return $this->permissions()->where('key', $key)->exists();
    }
    public function posProfile()
    {
        return $this->hasOne(PosProfile::class);
    }

    public function saleOrderHeaders()
    {
        return $this->hasMany(SaleOrderHeader::class, 'created_user_id', 'id');
    }
}
