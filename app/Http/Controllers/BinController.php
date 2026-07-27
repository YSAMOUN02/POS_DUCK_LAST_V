<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BinController extends Controller
{
    public function index(Request $request)
    {
        $query = Bin::query()->orderBy('name');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        return response()->json($query->get(['id', 'warehouse_id', 'name']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'name'         => 'required|string|max:100',
        ]);

        try {
            $bin = Bin::create([
                'warehouse_id' => $data['warehouse_id'],
                'name'         => $data['name'],
                'created_by'   => Auth::user()->username ?? 'System',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bin created successfully',
                'bin'     => $bin,
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'This bin already exists in the selected warehouse',
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $bin = Bin::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        try {
            $bin->update(['name' => $data['name']]);

            return response()->json([
                'success' => true,
                'message' => 'Bin updated successfully',
                'bin'     => $bin,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'This bin name already exists in the warehouse',
            ], 422);
        }
    }

    public function destroy($id)
    {
        $bin = Bin::findOrFail($id);

        if (\App\Models\WarehouseProduct::where('bin_id', $bin->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a bin that still holds stock',
            ], 422);
        }

        $bin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bin deleted successfully',
        ]);
    }
}
