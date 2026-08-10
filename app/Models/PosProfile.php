<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Profile of the person who created a document, by username.
     *
     * A printed document should carry the letterhead of whoever raised it, not
     * of whoever happens to be printing it — otherwise every reprint rebrands
     * the document to the current user. Purchase-side headers record the
     * creator as a username string (created_by) rather than a user id, so the
     * username is resolved here.
     *
     * Falls back to the signed-in user when the creator cannot be resolved,
     * which keeps older rows and 'NA'/'system' entries printing something
     * sensible instead of nothing.
     */
    public static function forCreatorUsername(?string $username): ?self
    {
        $username = trim((string) $username);

        if ($username !== '' && !in_array(strtolower($username), ['na', 'system'], true)) {
            $userId = User::where('username', $username)->value('id');
            if ($userId) {
                return static::forUser($userId);
            }
        }

        return static::forUser(Auth::id());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
