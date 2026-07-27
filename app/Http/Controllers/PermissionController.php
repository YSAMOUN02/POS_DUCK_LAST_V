<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Display order for the section-by-section checkbox UI — deliberately not
     * alphabetical so "Reports" (every report, individually grantable) always
     * renders as the last card regardless of what sections exist.
     */
    private const SECTION_ORDER = [
        'pos_sale',
        'quotation',
        'customer',
        'product',
        'category',
        'warehouse',
        'purchasing',
        'vendor',
        'exchange_rate',
        'user',
        'company_profile',
        'report',
    ];

    /**
     * Grouped by section for the section-by-section checkbox UI in the
     * Add/Edit User modals: { section: [ {id,section,action,key,label}, ... ] }
     */
    public function permissionListData(Request $request)
    {
        // ->toBase() converts out of Eloquent\Collection: its groupBy() still
        // returns an Eloquent\Collection wrapping the section groups, whose
        // ->except() is overridden to expect *model primary keys*, not array
        // keys — calling it with section-name strings throws "getKey() does
        // not exist on Collection" since the items here are groups, not models.
        $grouped = Permission::orderBy('action')->get()->groupBy('section')->toBase();

        // Re-key in SECTION_ORDER order (falling back to alphabetical for any
        // section not explicitly listed, so a newly-added section still shows
        // up instead of silently vanishing).
        $ordered = collect(self::SECTION_ORDER)
            ->filter(fn ($section) => $grouped->has($section))
            ->mapWithKeys(fn ($section) => [$section => $grouped[$section]]);

        $remaining = $grouped->except($ordered->keys()->all())->sortKeys();

        return response()->json($ordered->merge($remaining));
    }
}
