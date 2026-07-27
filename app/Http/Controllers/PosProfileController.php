<?php

namespace App\Http\Controllers;

use App\Models\PosProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosProfileController extends Controller
{
    // There's one company, not one profile per user — a user with their own
    // saved row sees that, but anyone else (any user granted company_profile.*
    // who never saved their own) falls back to the shared one instead of
    // seeing blank fields. PosProfile::forUser applies the same rule to the
    // printed documents, which used to skip the fallback entirely.
    private function resolveProfile(): ?PosProfile
    {
        return PosProfile::forUser(Auth::id());
    }

    public function show()
    {
        $profile = $this->resolveProfile();
        $data = $profile ? $profile->toArray() : [];
        $data['logo_url'] = self::logoUrl();

        return response()->json($data);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company'       => 'required|string|max:255',
            'description'   => 'nullable|string',
            'address1'      => 'nullable|string|max:255',
            'address2'      => 'nullable|string|max:255',
            'phone1'        => 'nullable|string|max:50',
            'phone2'        => 'nullable|string|max:50',
            'social'        => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'telegram'      => 'nullable|string|max:255',
            'seller'        => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
        ]);

        // customer_name is NOT NULL in the DB (has a default) — don't overwrite
        // it with null when the field is left blank in the form.
        if (empty($data['customer_name'])) {
            unset($data['customer_name']);
        }

        // Update the shared profile in place if one already exists, rather
        // than forking off a second row keyed to whoever happens to be
        // editing — otherwise every non-owning editor would silently create
        // their own separate (and now out-of-sync) company profile.
        $target = $this->resolveProfile();
        if ($target) {
            $target->update($data);
            $profile = $target;
        } else {
            $profile = PosProfile::create($data + ['user_report' => Auth::id()]);
        }

        $profileData = $profile->toArray();
        $profileData['logo_url'] = self::logoUrl();

        return response()->json([
            'success' => true,
            'message' => 'Company profile saved successfully',
            'profile' => $profileData,
        ]);
    }

    /**
     * One shared logo for every pos_profile. Always stored as
     * "company_logo.<ext>" in public/assets/logo so every print template
     * (thermal receipt + the A4 forms) can find it at a single fixed path
     * without needing a DB column per profile row.
     */
    public function uploadLogo(Request $request)
    {
        abort_unless(Auth::user()->hasPermission('company_profile.edit'), 403, 'You do not have permission to change the company logo.');

        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
        ]);

        foreach (glob(public_path('assets/logo/company_logo.*')) ?: [] as $old) {
            @unlink($old);
        }

        $file = $request->file('logo');
        $filename = 'company_logo.' . $file->getClientOriginalExtension();
        $file->move(public_path('assets/logo'), $filename);

        return response()->json([
            'success'  => true,
            'message'  => 'Logo updated successfully',
            'logo_url' => self::logoUrl(),
        ]);
    }

    /**
     * Clears the shared company logo, for businesses that simply don't have one.
     *
     * logoUrl() already returns null when no company_logo.* file exists, and
     * every print template treats a null logo as "print without it", so removing
     * the file is all that's needed — no placeholder image and no DB column.
     */
    public function removeLogo()
    {
        abort_unless(Auth::user()->hasPermission('company_profile.edit'), 403, 'You do not have permission to change the company logo.');

        $removed = 0;
        foreach (glob(public_path('assets/logo/company_logo.*')) ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => $removed > 0 ? 'Logo removed.' : 'There was no logo to remove.',
            'removed'  => $removed,
            'logo_url' => self::logoUrl(),   // null once the file is gone
        ]);
    }

    public static function logoUrl(): ?string
    {
        $files = glob(public_path('assets/logo/company_logo.*')) ?: [];
        if (empty($files)) {
            return null;
        }

        $path = $files[0];
        return asset('assets/logo/' . basename($path)) . '?v=' . filemtime($path);
    }
}
