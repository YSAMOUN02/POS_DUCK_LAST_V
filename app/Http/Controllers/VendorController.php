<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        // 🔥 Get last vendor code
        $last = Vendor::whereNotNull('code')
            ->orderByDesc('id')
            ->value('code');

        // Default start
        $nextNumber = 1;

        if ($last && preg_match('/V(\d+)/', $last, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        // Format: V0001
        $code = 'V' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        Vendor::create([
            'code' => $code,
            'name' => $request->name,
            'contact_person' => $request->contact_person,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'country' => $request->country,
            'city' => $request->city,
            'email' => $request->email,
            'phone1' => $request->phone1,
            'phone2' => $request->phone2,
            'website' => $request->website,
            'status' => $request->status ? 1 : 0,
        ]);

        return response()->json([
            'success' => true,
            'code' => $code
        ]);
    }
    public function list(Request $request)
    {
        $query = Vendor::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone1', 'like', "%{$search}%")
                    ->orWhere('phone2', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('website', 'like', "%{$search}%");
            });
        }

        // Only filter when `active` was actually sent. The previous `== 0` branch
        // matched a MISSING parameter too (null == 0 is true in PHP), so any
        // caller that omitted it silently got inactive vendors only — an empty
        // list, since every vendor is active.
        if ($request->filled('active')) {
            $query->where('status', (int) $request->active === 1 ? 1 : 0);
        }

        $vendors = $query->orderByDesc('id')->paginate(30);

        return response()->json([
            'data' => $vendors
        ]);
    }


    public function show($id)
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json([
                'message' => 'Vendor not found'
            ], 404);
        }

        return response()->json($vendor);
    }

    public function update(Request $request, $id)
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json([
                'message' => 'Vendor not found'
            ], 404);
        }

        $request->validate([
            'code' => 'required|unique:vendors,code,' . $id,
            'name' => 'required',
        ]);

        $vendor->update([
            'code' => $request->code,
            'name' => $request->name,
            'contact_person' => $request->contact_person,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'country' => $request->country,
            'city' => $request->city,
            'email' => $request->email,
            'phone1' => $request->phone1,
            'phone2' => $request->phone2,
            'website' => $request->website,
            'status' => $request->has('status') ? 1 : 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor updated successfully'
        ]);
    }
public function search(Request $request)
{
    $query = trim($request->q);

    $vendors = Vendor::query()
        ->when($query, function ($q) use ($query) {
            $q->where(function ($sub) use ($query) {
                $sub->where('code', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            });
        })
        ->limit(10)
        // phone1/address1 so a picker can fill contact details, not just the
        // name — the purchase preview blanked them without these.
        ->get(['id', 'code', 'name', 'phone1', 'address1']);

    return response()->json($vendors);
}
}
