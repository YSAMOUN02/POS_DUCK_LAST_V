<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
public function latest(Request $request)
{
    $query = Expense::query();

    if ($request->filled('search')) {

        $search = $request->search;

        $query->where(function ($q) use ($search) {

            $q->where('expense_code', 'like', "%{$search}%")
              ->orWhere('expense_name', 'like', "%{$search}%")
              ->orWhere('note', 'like', "%{$search}%");
        });
    }

    if ($request->filled('from_date')) {
        $query->whereDate(
            'expense_date',
            '>=',
            $request->from_date
        );
    }

    if ($request->filled('to_date')) {
        $query->whereDate(
            'expense_date',
            '<=',
            $request->to_date
        );
    }

    $allowedSorts = [
        'expense_date',
        'expense_code',
        'expense_name',
        'unit_price',
        'amount',
    ];

    $sortColumn = in_array(
        $request->sort_column,
        $allowedSorts
    )
        ? $request->sort_column
        : 'expense_date';

    $sortDirection =
        $request->sort_direction === 'asc'
        ? 'asc'
        : 'desc';

    $query->orderBy($sortColumn, $sortDirection);

    $limit = $request->limit == "All"
        ? 100000
        : (int) $request->limit;

    $expenses = $query->paginate($limit);

    return response()->json([
        'status' => true,
        'data' => $expenses,
    ]);
}
}
