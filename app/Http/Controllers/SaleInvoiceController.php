<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesVisibilityByRole;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleInvoiceController extends Controller
{
    use ScopesVisibilityByRole;

    public function salesReport(Request $request)
    {
        $query = InvoiceHeader::with(['lines', 'customer'])->withCount('lines');

        // 🔒 admin = all, supervisor = own warehouses, cashier = own documents.
        $this->scopeVisibilityViaLedger($query, 'sale_invoice_headers.invoice_number', 'ile.document_no');

        // -----------------------------
        // Filters
        // -----------------------------
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('invoice_date', [$request->from_date, $request->to_date]);
        }
        if ($request->filled('invoice_paymentMethod')) {
            $query->where('payment_method', $request->invoice_paymentMethod);
        }
        if ($request->filled('document_no')) {
            $search = trim($request->document_no);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('source_no', 'like', "%{$search}%");
            });
        }
        if ($request->filled('customer_filter')) {
            $query->where('customer_id', $request->customer_filter);
        }
        if ($request->filled('ProductSearchInput') || $request->filled('category_filter')) {
            $query->whereHas('lines', function ($q) use ($request) {
                if ($request->filled('ProductSearchInput')) {
                    $search = $request->ProductSearchInput;
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%");
                }
                if ($request->filled('category_filter')) {
                    $q->where('category_name', $request->category_filter);
                }
            });
        }

        // -----------------------------
        // Sorting
        // -----------------------------
        $allowedSorts = [
            'invoice_number' => 'invoice_number',
            'customer_name' => 'customer_id',
            'invoice_date' => 'invoice_date',
            'due_date' => 'due_date',
            'status' => 'status',
            'invoice_total_amount' => 'total_amount',
            'invoice_vat_amount' => 'vat_amount',
            'invoice_discount_amount' => 'discount_amount',
            'return_amount' => 'return_amount',
            'lines_count' => 'lines_count',
            'created_at' => 'created_at',
        ];

        $sortColumn = $allowedSorts[$request->sort_column] ?? 'id';
        $sortDirection = $request->sort_direction === 'desc' ? 'desc' : 'asc';

        if ($request->filled('sort_column') && isset($allowedSorts[$request->sort_column])) {
            $query->orderBy($allowedSorts[$request->sort_column], $sortDirection)
                ->orderBy('id', 'desc');
        } else {
            $query->orderBy('invoice_number', 'desc');
        }

        // -----------------------------
        // Pagination (manual)
        // -----------------------------
        $limit = $request->sale_view_limit;
        $page = (int) ($request->page ?? 1);

        $collection = $query->get(); // GET ALL first (no OFFSET)

        if ($limit == 'All') {
            $data = [
                'data' => $collection,
                'current_page' => 1,
                'last_page' => 1,
                'total' => $collection->count(),
            ];
        } else {
            $limit = (int) $limit;

            // slice collection manually
            $paginated = $collection->slice(($page - 1) * $limit, $limit)->values();

            $data = [
                'data' => $paginated,
                'current_page' => $page,
                'last_page' => ceil($collection->count() / $limit),
                'total' => $collection->count(),
            ];
        }

        return response()->json($data);
    }









    public function getCategories()
    {
        $categories = InvoiceLine::whereNotNull('category_name')
            ->distinct()
            ->orderBy('category_name')
            ->pluck('category_name');

        return response()->json($categories);
    }
    public function getPaymentMethods()
    {
        $methods = InvoiceHeader::whereNotNull('payment_method') // or whatever column you use
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method');

        return response()->json($methods);
    }
    public function searchCustomers(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('contact_name', 'like', '%' . $search . '%')
                    ->orWhere('contact_phone', 'like', '%' . $search . '%')
                    ->orWhere('address1', 'like', '%' . $search . '%')
                    ->orWhere('customer_code', 'like', '%' . $search . '%');
            });
        }

        $customers = $query
            ->latest()
            ->limit(20)
            ->get(['id', 'name', 'phone', 'customer_code']);

        return response()->json($customers);
    }
    public function searchProducts(Request $request)
    {
        $query = InvoiceLine::query();

        if ($request->filled('search')) {
            $search = strtolower($request->search);

            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(item_code) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$search}%"]);
        }

        // pluck only the name or item_code, get distinct, limit 20
        $products = $query->select('name', 'item_code')
            ->distinct()
            ->latest()
            ->limit(20)
            ->get()
            ->pluck('name'); // or pluck('item_code') if you prefer

        return response()->json($products);
    }
}
