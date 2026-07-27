<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $query = Quotation::query();

        // 🔒 visibility — created by this user (quotations never touch stock/ledger)
        $query->where('created_user_id', (string) Auth::id());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('search_document')) {
            $query->where('quotation_no', 'like', '%' . $request->search_document . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('quotation_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('quotation_date', '<=', $request->to_date);
        }

        // Active quotations first, Completed/Cancelled pushed to bottom, newest first
        $query->orderByRaw("
            CASE
                WHEN status IN ('Completed', 'Cancelled') THEN 2
                ELSE 1
            END ASC
        ");
        $query->orderByDesc('quotation_no');

        return response()->json(
            $query->paginate(100)
        );
    }

    public function show($id)
    {
        $quotation = Quotation::with('lines')->find($id);

        if (!$quotation) {
            return response()->json([], 404);
        }

        $lines = $quotation->lines->map(function ($line) {
            return [
                'id' => $line->id,
                'item_code' => $line->item_code ?? '',
                'name' => $line->name ?? '',
                'quantity' => (float) ($line->quantity ?? 0),
                'unit' => $line->unit ?? '',
                'sell_price' => (float) ($line->sell_price ?? 0),
                'discount_amount' => (float) ($line->discount_amount ?? 0),
                'vat_amount' => (float) ($line->vat_amount ?? 0),
                'grand_total_amount' => (float) ($line->grand_total_amount ?? 0),
            ];
        });

        return response()->json([
            'header' => [
                'id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'customer_id' => $quotation->customer_id,
                'customer_name' => $quotation->customer_name,
                'phone' => $quotation->phone,
                'address' => $quotation->address,

                'quotation_date' => $quotation->quotation_date,
                'valid_until' => $quotation->valid_until,
                'status' => $quotation->status,

                'total_amount' => $quotation->total_amount,
                'vat_amount' => $quotation->vat_amount,
                'discount_amount' => $quotation->discount_amount,
                'grand_total' => $quotation->grand_total,
                'factor' => $quotation->factor,
                'currency_name' => $quotation->currency_name,
                'remarks' => $quotation->remarks,
                'created_by' => $quotation->created_by,
            ],
            'lines' => $lines,
        ]);
    }

    public function updateStatus(Request $request)
    {
        try {
            $request->validate([
                'quotation_id' => 'required|integer|exists:quotations,id',
                'status' => 'required|string|in:Completed,Cancelled',
            ]);

            $quotation = Quotation::findOrFail($request->quotation_id);
            $quotation->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Quotation status updated successfully',
                'status' => $quotation->status,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
