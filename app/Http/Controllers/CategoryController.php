<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
     // Return all categories for select
    public function getCategories(Request $request)
    {
        // Optional: you can filter active categories only
        $categories = Category::query()
            ->when($request->filled('active'), function ($q) use ($request) {
                $q->where('status', $request->active); // assuming 'status' column
            })
            ->orderBy('name') // sort alphabetically
            ->get(['id', 'name']); // only return fields needed

        return response()->json($categories);
    }

    // Full list for the Manage Categories tool (name, description, status, product count)
    public function index(Request $request)
    {
        $categories = Category::query()
            ->withCount('posItems')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'boolean',
        ]);

        try {
            $category = Category::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'status'      => $data['status'] ?? true,
                'created_by'  => Auth::user()->username ?? 'System',
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Category created successfully',
                'category' => $category,
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A category with this name already exists',
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'boolean',
        ]);

        try {
            $category->update([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'status'      => $data['status'] ?? $category->status,
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Category updated successfully',
                'category' => $category,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'A category with this name already exists',
            ], 422);
        }
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        if (Product::where('category_id', $category->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a category that still has products assigned to it',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}
