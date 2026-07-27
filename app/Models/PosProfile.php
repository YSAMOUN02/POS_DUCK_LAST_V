<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosProfile extends Model
{
    protected $table = 'pos_profiles';

    protected $fillable = [
        'user_report',
        'company',
        'description',
        'address1',
        'address2',
        'phone1',
        'phone2',
        'social',
        'email',
        'telegram',
        'seller',
        'customer_name',
    ];
    /**
     * Profile to show on printed documents for a user.
     *
     * There's one company, not one profile per user: a user with their own
     * saved row gets it, anyone else falls back to the shared house profile
     * (user_report = 0). Without the fallback, any user who never saved a
     * profile printed an invoice with a blank letterhead — no company name,
     * no address, no phone.
     */
    public static function forUser($userId): ?self
    {
        return static::where('user_report', $userId)->first()
            ?? static::where('user_report', '0')->first()
            ?? static::first();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
