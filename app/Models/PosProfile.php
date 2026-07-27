<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PosProfile extends Model
{
    use LogsActivity;

    protected string $activitySection = 'company_profile';

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
    // app/Models/User.php
// app/Models/PosProfile.php

public function user()
{
    return $this->belongsTo(User::class);
}

}
