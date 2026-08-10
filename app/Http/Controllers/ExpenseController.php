<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    // Sum of refunds per original, fetched in one query rather than per row,
    // so the list can hide the Refund button on anything already settled
    // instead of only finding out when the POST comes back 422.
    $expenses = $query->withSum('refunds as refunded_total', 'amount')->paginate($limit);

    $expenses->getCollection()->transform(function ($e) {
        // Refund amounts are stored negative, so adding gives what is left.
        $left = $e->refunded_from_id !== null
            ? 0.0
            : max(0.0, round((float) $e->amount + (float) ($e->refunded_total ?? 0), 6));

        $e->refundable_amount = $left;
        $e->is_refundable = $left > 0 && $e->refunded_from_id === null;

        return $e;
    });

    return response()->json([
        'status' => true,
        'data' => $expenses,
    ]);
}

    /**
     * Refund an expense — money paid out that came back.
     *
     * Written as a MIRROR row with a negative amount rather than a flag on the
     * original, matching how purchase and sale returns already work here: every
     * report that sums expenses nets it automatically, and a partial refund is
     * simply a smaller mirror. The original row is never edited, so what was
     * originally paid stays on the record.
     */
    public function refund(Request $request)
    {
        abort_unless(Auth::user()->hasPermission('expense.refund'), 403);

        $data = $request->validate([
            'expense_id' => 'required|integer|exists:expenses,id',
            'amount'     => 'nullable|numeric|gt:0',
            'reason'     => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($data) {
            // Locked so two refunds submitted at once cannot each see the full
            // amount as still refundable and together exceed it.
            $original = Expense::whereKey($data['expense_id'])->lockForUpdate()->first();

            if (! $original) {
                return response()->json(['status' => false, 'message' => 'Expense not found.'], 404);
            }

            if ($original->isRefund()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'That row is itself a refund and cannot be refunded again.',
                ], 422);
            }

            $remaining = $original->refundableAmount();

            if ($remaining <= 0) {
                return response()->json([
                    'status'  => false,
                    'message' => 'This expense has already been fully refunded.',
                ], 422);
            }

            // Default to refunding the whole outstanding amount.
            $amount = isset($data['amount']) ? round((float) $data['amount'], 6) : $remaining;

            if ($amount > $remaining) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Refund exceeds the outstanding amount (' . rtrim(rtrim(number_format($remaining, 6, '.', ''), '0'), '.') . ' left).',
                ], 422);
            }

            $refund = Expense::create([
                'refunded_from_id' => $original->id,
                'expense_date'     => now()->toDateString(),
                'product_id'       => $original->product_id,
                // Same code as the original, so the pair reads as one document —
                // the convention purchase returns already use.
                'expense_code'     => $original->expense_code,
                'expense_name'     => $original->expense_name,
                'qty'              => 0,
                'unit_price'       => 0,
                'amount'           => -1 * $amount,
                'currency_name'    => $original->currency_name,
                'factor'           => $original->factor,
                'payment_method'   => $original->payment_method,
                'note'             => $original->note,
                'refund_reason'    => $data['reason'] ?? null,
                'status'           => 1,
                'created_by'       => Auth::user()->name ?? 'System',
            ]);

            return response()->json([
                'status'    => true,
                'message'   => 'Expense refunded.',
                'refund'    => $refund,
                'remaining' => $original->fresh()->refundableAmount(),
            ]);
        });
    }
}
