<?php

namespace App\Http\Controllers;

use App\Models\ItemLedgerEntry;
use App\Models\SaleOrderHeader;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function api_get_item_ledger_entry_by_year($year)
    {
        try {

            if ($year === 'all') {
                $data = ItemLedgerEntry::orderByDesc('posting_date')->get();
            } else {
                $year = (int) $year;

                if (!in_array($year, [1, 3, 5, 10])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid year. Use 1, 3, 5, 10, or all.'
                    ], 400);
                }

                $data = ItemLedgerEntry::whereDate('posting_date', '>=', now()->subYears($year))
                    ->orderByDesc('posting_date')
                    ->get();
            }

            return response()->json($data);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
        public function api_get_sale_order_by_year($year)
        {
            $query = SaleOrderHeader::with('lines');
        
            if ($year !== 'all') {
        
                $year = (int) $year;
        
                if (!in_array($year, [1, 3, 5, 10])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid year. Use 1, 3, 5, 10, or all.'
                    ], 400);
                }
        
                $query->whereDate('posting_date', '>=', now()->subYears($year));
            }
        
            $saleOrders = $query
                ->orderByDesc('posting_date')
                ->get();
        
            $data = $saleOrders->map(function ($saleOrder) {
        
                $lines = $saleOrder->lines->map(function ($line) {
        
                    $quantity = (float) ($line->quantity ?? 0);
                    $sellPrice = (float) ($line->sell_price ?? 0);
                    $discountAmount = (float) ($line->discount_amount ?? 0);
                    $vatAmount = (float) ($line->vat_amount ?? 0);
        
                    $subTotal = $quantity * $sellPrice;
                    $grandTotal = ($subTotal - $discountAmount) + $vatAmount;
        
                    return [
                        'id' => $line->id,
                        'item_code' => $line->item_code ?? '',
                        'name' => $line->name ?? '',
                        'quantity' => $quantity,
                        'sell_price' => $sellPrice,
                        'sub_total' => $subTotal,
                        'discount_amount' => $discountAmount,
                        'vat_amount' => $vatAmount,
                        'grand_total_amount' => $line->grand_total_amount ?? $grandTotal,
                    ];
                });
        
                return [
                    'header' => [
                        'id' => $saleOrder->id,
                        'document_no' => $saleOrder->document_no,
                        'source_no' => $saleOrder->source_no,
                        'contact_name' => $saleOrder->contact_name,
                        'posting_date' => $saleOrder->posting_date,
                        'status' => $saleOrder->status,
                        'grand_total' => $saleOrder->grand_total,
                    ],
                    'lines' => $lines
                ];
            });
        
            return response()->json($data);
        }
}
