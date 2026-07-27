<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesVisibilityByRole;
use App\Models\ItemLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemLedgerEntryController extends Controller
{
    use ScopesVisibilityByRole;

   public function index(Request $request)
{
    $query = ItemLedgerEntry::query();
  

    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('document_no', 'like', "%{$search}%")
                ->orWhere('source_no', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('category_name', 'like', "%{$search}%");
        });
    }
    // 🔒 admin = all, supervisor = own warehouses, cashier = own documents.
    $this->scopeVisibilityByWarehouseColumn($query);

    if ($request->filled('lot')) {
        $query->where('lot', 'like', "%{$request->lot}%");
    }

    if ($request->filled('warehouse')) {
        $query->where('warehouse_name', 'like', "%{$request->warehouse}%");
    }

    if ($request->filled('type')) {
        $query->where('document_type', $request->type);
    }

    if ($request->filled('from')) {
        $query->whereDate('posting_date', '>=', $request->from);
    }

    if ($request->filled('to')) {
        $query->whereDate('posting_date', '<=', $request->to);
    }

    $perPage = $request->integer('per_page', 50);

    return $query
        ->orderByDesc('posting_date')
        ->orderByDesc('id')
        ->paginate($perPage);
}

}
