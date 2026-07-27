<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesVisibilityByRole;
use App\Models\SaleOrderHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\InvoiceHeader;
use App\Models\ItemLedgerEntry;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Chart\Layout;

class SaleOrderController extends Controller
{
    use ScopesVisibilityByRole;


/**
 * The list's filter set, shared with markAllDelivered() so a bulk action can
 * never touch a different set of rows than the one on screen.
 */
private function applySaleOrderFilters(Request $request, $query)
{
    // Admin filter by user
    if ($request->filled('user_id') && $request->user_id != '') {
        $query->where('created_user_id', $request->user_id);
    }

    // Search customer
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('contact_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    // Search document no
    if ($request->filled('search_document')) {
        $search_document = $request->search_document;
        $query->where('document_no', 'like', '%' . $search_document . '%');
    }

    // Status filter
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // Payment status filter
    if ($request->filled('payment_status')) {
        $query->where('payment_status', $request->payment_status);
    }

    // Delivery status filter
    if ($request->filled('delivery_status')) {
        $query->where('delivery_status', $request->delivery_status);
    }

    // From posting date
    if ($request->filled('from_posting_date')) {
        $query->whereDate('posting_date', '>=', $request->from_posting_date);
    }

    // To posting date
    if ($request->filled('to_posting_date')) {
        $query->whereDate('posting_date', '<=', $request->to_posting_date);
    }

    return $query;
}

public function getSaleOrders(Request $request)
{
    $query = SaleOrderHeader::query();

    // 🔒 admin = all, supervisor = own warehouses, cashier = own documents.
    $this->scopeVisibilityViaLedger($query, 'sale_order_headers.document_no', 'ile.source_no');

    $this->applySaleOrderFilters($request, $query);

    /*
    |--------------------------------------------------------------------------
    | Sort Priority
    |--------------------------------------------------------------------------
    | Active / pending document first
    | Completed / Returned / Cancelled go bottom
    | Then newest document no first
    |--------------------------------------------------------------------------
    */
    $query->orderByRaw("
        CASE
            WHEN status IN ('Completed', 'Returned', 'Cancelled') THEN 2
            ELSE 1
        END ASC
    ");

    // newest document first
    $query->orderByDesc('document_no');

    return response()->json(
        $query->paginate(100)
    );
}
    public function getSaleOrderLines($id)
    {
        $saleOrder = SaleOrderHeader::with('lines')->find($id);

        if (!$saleOrder) {
            return response()->json([], 404);
        }

        // The list query is scoped; this per-id read was not, so iterating ids
        // exposed every order's lines, prices and customer details.
        $this->authorizeDocumentAccess($saleOrder);

        $lines = $saleOrder->lines->map(function ($line) {
            $quantity = (float) ($line->quantity ?? 0);
            $quantity_shiped = (float) ($line->quantity_shiped ?? 0);
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
                'quantity_shiped' => $quantity_shiped,
                'unit' => $line->unit ?? '',
                'sell_price' => $sellPrice,
                'sub_total' => $subTotal,
                'discount_amount' => $discountAmount,
                'vat_amount' => $vatAmount,
                'grand_total_amount' => $line->grand_total_amount ?? $grandTotal,
            ];
        });

        return response()->json([
            'header' => [
                'id' => $saleOrder->id,
                'document_no' => $saleOrder->document_no,
                'source_no' => $saleOrder->source_no,
                'contact_name' => $saleOrder->contact_name,
                'phone' => $saleOrder->phone,
                'address' => $saleOrder->address,

                'posting_date' => $saleOrder->posting_date,
                'delivery_date' => $saleOrder->delivery_date,
                'order_date' => $saleOrder->order_date,
                'status' => $saleOrder->status,
                'payment_status' => $saleOrder->payment_status,

                'customer_type' => $saleOrder->customer_type,
                'payment_method' => $saleOrder->payment_method,

                'delivery_status' => $saleOrder->delivery_status,
                'delivery_info' => $saleOrder->delivery_info,
                'driver_name' => $saleOrder->driver_name,
                'driver_phone' => $saleOrder->driver_phone,

                'total_amount' => $saleOrder->total_amount,
                'vat_amount' => $saleOrder->vat_amount,
                'discount_amount' => $saleOrder->discount_amount,
                'paid_amount' => $saleOrder->paid_amount,
                'balance_amount' => $saleOrder->balance_amount,
                'grand_total' => $saleOrder->grand_total,
                'factor' => $saleOrder->factor,
                'currency_name' => $saleOrder->currency_name,
                'remarks' => $saleOrder->remarks,
                'created_by' => $saleOrder->created_by,
            ],
            'lines' => $lines
        ]);
    }

    // Warehouse-facing "where to pull each item from" doc — grouped by
    // product+bin+lot since one product can be fulfilled from more than one
    // bin/lot on the same order. Keyed off source_no (the order's own
    // document_no) rather than the invoice number so it still finds
    // everything even for orders invoiced across multiple partial payments.
    // JSON (not a Blade view) — the frontend builds the printable HTML
    // itself and prints it via the browser's own print dialog.
    public function pickingListData($id)
    {
        $saleOrder = SaleOrderHeader::find($id);
        abort_unless($saleOrder, 404);
        $this->authorizeDocumentAccess($saleOrder);

        $rows = ItemLedgerEntry::where('document_type', 'Sales Invoice')
            ->where('source_no', $saleOrder->document_no)
            ->orderBy('name')
            ->orderBy('bin_name')
            ->get(['name', 'item_code', 'barcode', 'unit', 'warehouse_name', 'bin_name', 'lot', 'expire_date', 'quantity']);

        return response()->json([
            'header' => [
                'document_no' => $saleOrder->document_no,
                'contact_name' => $saleOrder->contact_name,
                'phone' => $saleOrder->phone,
                'address' => $saleOrder->address,
                'created_by' => $saleOrder->created_by,
            ],
            'rows' => $rows,
        ]);
    }

    public function updateStatus(Request $request)
    {
        try {
            $request->validate([
                'sale_order_id' => 'required|integer|exists:sale_order_headers,id',
                'status' => 'required|string|in:Quotation,Ordered,Deposit,Completed,Cancelled,Returned',
            ]);

            $saleOrder = SaleOrderHeader::findOrFail($request->sale_order_id);
            // id comes from the request — without this a cashier could cancel or
            // mark returned any order in the system.
            $this->authorizeDocumentAccess($saleOrder);

            $updateData = [
                'status' => $request->status,
                'updated_by' => Auth::user()->username ?? 'System',
            ];

            // Cancelled and Returned both close the order out, so nothing is
            // still collectable on it. Without zeroing the balance the list kept
            // showing an outstanding amount against an order nobody will ever
            // pay — and it counted toward receivables.
            if ($request->status === 'Cancelled') {
                $updateData['payment_status'] = 'N/A';
                $updateData['delivery_status'] = 'N/A';
                $updateData['balance_amount'] = 0;
            }

            if ($request->status === 'Returned') {
                $updateData['payment_status'] = 'Refunded';
                $updateData['delivery_status'] = 'Returned';
                $updateData['balance_amount'] = 0;
            }

            $saleOrder->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Sale order status updated successfully',
                'status' => $saleOrder->status,
                'payment_status' => $saleOrder->payment_status,
                'delivery_status' => $saleOrder->delivery_status,
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
                'message' => 'Sale order not found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateDeliveryStatus(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:sale_order_headers,id'],
            'delivery_status' => [
                'required',
                Rule::in([
                    'Pending',
                    'Processing',
                    'Shipped',
                    'Delivered',
                    'Cancelled',
                    'Returned',
                    'N/A',
                ]),
            ],
        ]);

        $saleOrder = SaleOrderHeader::findOrFail($data['id']);
        $this->authorizeDocumentAccess($saleOrder);

        $saleOrder->update([
            'delivery_status' => $data['delivery_status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery status updated',
            'delivery_status' => $saleOrder->delivery_status,
        ]);
    }

    /**
     * Marks every order in the CURRENT list view as Delivered.
     *
     * Deliberately driven by the same visibility scope + filter set as
     * getSaleOrders(), so "all" always means "everything you can currently see"
     * — a cashier can only ever affect their own orders, and a supervisor only
     * their warehouses'. Orders that never ship (Cancelled / Returned, or
     * delivery_status N/A) are skipped rather than silently mislabelled as
     * delivered, and already-Delivered rows are left alone so the count
     * reported back reflects real changes.
     */
    public function markAllDelivered(Request $request)
    {
        $query = SaleOrderHeader::query();

        $this->scopeVisibilityViaLedger($query, 'sale_order_headers.document_no', 'ile.source_no');
        $this->applySaleOrderFilters($request, $query);

        $query->whereNotIn('status', ['Cancelled', 'Returned'])
            ->whereNotNull('delivery_status')
            ->whereNotIn('delivery_status', ['Delivered', 'N/A', 'Cancelled', 'Returned']);

        // Count first so the response can state exactly what changed; the
        // update is a single statement, so there is no partial-write window.
        $affected = (clone $query)->count();

        if ($affected === 0) {
            return response()->json([
                'success'  => true,
                'affected' => 0,
                'message'  => 'Nothing to update — no pending deliveries in the current view.',
            ]);
        }

        // No `updated_by` here: sale_order_headers has created_by / updated_at but
        // no updated_by column. A query-builder update() writes raw SQL (unlike an
        // Eloquent model update, which silently drops non-fillable keys — which is
        // why updateStatus() above appears to set it but never does), so including
        // it would fail with "Unknown column".
        $query->update([
            'delivery_status' => 'Delivered',
            'updated_at'      => now(),
        ]);

        return response()->json([
            'success'  => true,
            'affected' => $affected,
            'message'  => $affected . ' order(s) marked as Delivered.',
        ]);
    }




    public function exportSalesExcel(Request $request): StreamedResponse
    {
        $from     = $request->input('from_date') ?: now()->startOfMonth()->toDateString();
        $to       = $request->input('to_date') ?: now()->toDateString();
        $docNo    = trim((string) $request->input('document', ''));
        $pay      = $request->input('payment') ?: null;
        $custId   = $request->input('customer_id') ?: null;
        $prodId   = $request->input('product_id') ?: null;
        $category = $request->input('category') ?: null;

        $headerQuery = InvoiceHeader::query()
            ->whereBetween('invoice_date', [$from, $to])
            ->when($docNo, fn($q) => $q->where('invoice_number', 'like', "%{$docNo}%"))
            ->when($pay, fn($q) => $q->where('payment_method', $pay))
            ->when($custId, fn($q) => $q->where('customer_id', $custId));

        // 🔒 admin = all, supervisor = own warehouses, cashier = own documents.
        $this->scopeVisibilityViaLedger($headerQuery, 'sale_invoice_headers.invoice_number', 'ile.document_no');

        if ($prodId || $category) {
            $headerQuery->whereHas('lines', function ($q) use ($prodId, $category) {
                if ($prodId)   $q->where('product_id', $prodId);
                if ($category) $q->where('category_name', $category);
            });
        }

        $headers = $headerQuery->with(['lines' => function ($q) use ($prodId, $category) {
            if ($prodId)   $q->where('product_id', $prodId);
            if ($category) $q->where('category_name', $category);
        }])->orderBy('invoice_date')->orderBy('invoice_number')->get();

        $khr = fn($usd, $factor) => round(((float) $usd * (float) ($factor ?: 4100)) / 100) * 100;

        // ---- aggregate ----
        $sub = 0;
        $disc = 0;
        $vat = 0;
        $grand = 0;
        $units = 0;
        $byPay = [];
        $byProduct = [];
        $byCategory = [];
        foreach ($headers as $h) {
            foreach ($h->lines as $l) {
                $sub += (float) $l->line_amount;
                $disc += (float) $l->discount_amount;
                $vat += (float) $l->vat_amount;
                $grand += (float) $l->grand_total_amount;
                $units += (float) $l->quantity;

                $pk = $l->name ?: 'Unknown';
                $byProduct[$pk] = $byProduct[$pk] ?? ['qty' => 0, 'unit' => $l->unit ?: '', 'amt' => 0];
                $byProduct[$pk]['qty'] += (float) $l->quantity;
                $byProduct[$pk]['amt'] += (float) $l->grand_total_amount;

                $ck = $l->category_name ?: '(uncategorised)';
                $byCategory[$ck] = $byCategory[$ck] ?? ['qty' => 0, 'amt' => 0];
                $byCategory[$ck]['qty'] += (float) $l->quantity;
                $byCategory[$ck]['amt'] += (float) $l->grand_total_amount;
            }
            $pm = $h->payment_method ?: 'Unknown';
            $byPay[$pm] = ($byPay[$pm] ?? 0) + (float) $h->lines->sum('grand_total_amount');
        }
        uasort($byProduct, fn($a, $b) => $b['amt'] <=> $a['amt']);
        uasort($byCategory, fn($a, $b) => $b['amt'] <=> $a['amt']);
        $topProducts = array_slice($byProduct, 0, 10, true);

        $BAR = 'FF0F172A';
        $INK = 'FF1E293B';
        $CARD = 'FFF8FAFC';
        $LINE = 'FFE2E8F0';
        $CYAN = 'FF0891B2';
        $GREEN = 'FF059669';
        $AMBER = 'FFD97706';
        $VIOLET = 'FF7C3AED';
        $ROSE = 'FFE11D48';
        $BLUE = 'FF2563EB';
        $SUBTXT = 'FF64748B';
        $usdFmt = '"$"#,##0.00;[Red]-"$"#,##0.00';
        $khrFmt = '#,##0"៛";[Red]-#,##0"៛"';

        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Khmer OS Siemreap')->setSize(10);

        /* ================= SHEET 1 — SUMMARY (dashboard) ================= */
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Summary');
        $sh->setShowGridlines(false);

        // column widths: A-D tables zone, E gutter, F-N charts zone
        foreach (
            [
                'A' => 26,
                'B' => 12,
                'C' => 9,
                'D' => 15,
                'E' => 3,
                'F' => 13,
                'G' => 13,
                'H' => 13,
                'I' => 13,
                'J' => 13,
                'K' => 13,
                'L' => 13,
                'M' => 13,
                'N' => 13
            ] as $c => $w
        ) {
            $sh->getColumnDimension($c)->setWidth($w);
        }

        // ---- title band ----
        $sh->mergeCells('A1:N1');
        $sh->setCellValue('A1', 'SALES REPORT');
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BAR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sh->getRowDimension(1)->setRowHeight(38);

        $sh->mergeCells('A2:N2');
        $sh->setCellValue(
            'A2',
            Carbon::parse($from)->format('d M Y') . '  –  ' . Carbon::parse($to)->format('d M Y')
                . '     ·     Payment: ' . ($pay ?: 'All')
                . '     ·     Category: ' . ($category ?: 'All')
                . '     ·     By ' . (Auth::user()->username ?? 'System')
                . '  at ' . now()->format('d M Y H:i')
        );
        $sh->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['argb' => 'FFCBD5E1']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $INK]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sh->getRowDimension(2)->setRowHeight(20);

        // ---- KPI cards row (6 cards, 2 cols each: A-B, C-D, F-G, H-I, K-L, M-N) ----
        $cards = [
            ['INVOICES',    count($headers), '#,##0',   $BLUE,   'A', 'B'],
            ['UNITS SOLD',  $units,          '#,##0.##', $VIOLET, 'C', 'D'],
            ['SUB TOTAL',   $sub,            $usdFmt,   $CYAN,   'F', 'G'],
            ['DISCOUNT',    -$disc,          $usdFmt,   $ROSE,   'H', 'I'],
            ['VAT',         $vat,            $usdFmt,   $AMBER,  'K', 'L'],
            ['GRAND TOTAL', $grand,          $usdFmt,   $GREEN,  'M', 'N'],
        ];
        $sh->getRowDimension(4)->setRowHeight(16);
        $sh->getRowDimension(5)->setRowHeight(26);
        foreach ($cards as [$label, $value, $fmt, $accent, $c1, $c2]) {
            $sh->mergeCells("{$c1}4:{$c2}4");
            $sh->mergeCells("{$c1}5:{$c2}5");
            $sh->setCellValue("{$c1}4", $label);
            $sh->setCellValue("{$c1}5", $value);
            $sh->getStyle("{$c1}4:{$c2}5")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $CARD]],
                'borders' => [
                    'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $LINE]],
                    'top'     => ['borderStyle' => Border::BORDER_THICK, 'color' => ['argb' => $accent]],
                ],
            ]);
            $sh->getStyle("{$c1}4")->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['argb' => $SUBTXT]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sh->getStyle("{$c1}5")->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => $INK]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sh->getStyle("{$c1}5")->getNumberFormat()->setFormatCode($fmt);
        }

        // ---- section header helper ----
        $section = function (int $row, string $title, string $lastCol) use ($sh, $INK, $CYAN) {
            $sh->mergeCells("A{$row}:{$lastCol}{$row}");
            $sh->setCellValue("A{$row}", $title);
            $sh->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $INK]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                'borders' => ['left' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['argb' => $CYAN]]],
            ]);
            $sh->getRowDimension($row)->setRowHeight(20);
        };
        $tableHead = function (int $row, array $cols) use ($sh, $CARD, $SUBTXT, $LINE) {
            foreach ($cols as $col => $text) {
                $sh->setCellValue("{$col}{$row}", $text);
            }
            $first = array_key_first($cols);
            $last = array_key_last($cols);
            $sh->getStyle("{$first}{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $SUBTXT]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $CARD]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $LINE]]],
            ]);
        };

        // ---- By Payment table (A:B) ----
        $r = 7;
        $section($r, 'BY PAYMENT METHOD', 'B');
        $r++;
        $tableHead($r, ['A' => 'Payment', 'B' => 'Amount']);
        $r++;
        $payStart = $r;
        foreach ($byPay as $pm => $amt) {
            $sh->setCellValue("A{$r}", $pm);
            $sh->setCellValue("B{$r}", round($amt, 2));
            $sh->setCellValue("C{$r}", $grand > 0 ? $amt / $grand : 0);
            $sh->getStyle("B{$r}")->getNumberFormat()->setFormatCode($usdFmt);
            $sh->getStyle("C{$r}")->getNumberFormat()->setFormatCode('0.0%');
            $sh->getStyle("C{$r}")->getFont()->setColor(
                (new \PhpOffice\PhpSpreadsheet\Style\Color($SUBTXT))
            );
            $r++;
        }
        $payEnd = $r - 1;

        // ---- By Category table (A:D) ----
        $r += 1;
        $section($r, 'BY CATEGORY', 'D');
        $r++;
        $tableHead($r, ['A' => 'Category', 'B' => 'Qty', 'C' => '', 'D' => 'Amount']);
        $r++;
        $catStart = $r;
        foreach ($byCategory as $cname => $c) {
            $sh->setCellValue("A{$r}", $cname);
            $sh->setCellValue("B{$r}", round($c['qty'], 2));
            $sh->setCellValue("D{$r}", round($c['amt'], 2));
            $sh->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sh->getStyle("D{$r}")->getNumberFormat()->setFormatCode($usdFmt);
            $r++;
        }
        $catEnd = $r - 1;

        // ---- Top 10 Products table (A:D) ----
        $r += 1;
        $section($r, 'TOP 10 PRODUCTS', 'D');
        $r++;
        $tableHead($r, ['A' => 'Product', 'B' => 'Qty', 'C' => 'UOM', 'D' => 'Amount']);
        $r++;
        $prodStart = $r;
        foreach ($topProducts as $pname => $p) {
            $sh->setCellValue("A{$r}", $pname);
            $sh->setCellValue("B{$r}", round($p['qty'], 2));
            $sh->setCellValue("C{$r}", $p['unit']);
            $sh->setCellValue("D{$r}", round($p['amt'], 2));
            $sh->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sh->getStyle("D{$r}")->getNumberFormat()->setFormatCode($usdFmt);
            $r++;
        }
        $prodEnd = $r - 1;

        // zebra on all three tables
        foreach ([[$payStart, $payEnd, 'C'], [$catStart, $catEnd, 'D'], [$prodStart, $prodEnd, 'D']] as [$a, $b, $lc]) {
            for ($i = $a; $i <= $b; $i++) {
                if (($i - $a) % 2 === 1) {
                    $sh->getStyle("A{$i}:{$lc}{$i}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($CARD);
                }
            }
        }

        // ---- charts (right zone F..N) ----
        $mkFills = function (int $count) {
            $palette = ['0891B2', '059669', 'D97706', '7C3AED', 'E11D48', '2563EB', 'DB2777', '65A30D'];
            $out = [];
            for ($i = 0; $i < $count; $i++) $out[] = $palette[$i % count($palette)];
            return $out;
        };
        $addChart = function (
            string $name,
            string $title,
            string $type,
            ?string $grouping,
            int $lblRow,
            int $s,
            int $e,
            string $catCol,
            string $valCol,
            string $tl,
            string $br,
            bool $pct
        ) use ($sh, $mkFills) {
            $count  = $e - $s + 1;
            if ($count < 1) return;
            $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Summary!\${$valCol}\${$lblRow}", null, 1)];
            $cats   = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Summary!\${$catCol}\${$s}:\${$catCol}\${$e}", null, $count)];
            $vals   = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Summary!\${$valCol}\${$s}:\${$valCol}\${$e}", null, $count);
            if (method_exists($vals, 'setFillColor')) {
                try {
                    $vals->setFillColor($type === DataSeries::TYPE_PIECHART ? $mkFills($count) : $mkFills(1)[0]);
                } catch (\Throwable $x) { /* old lib — Excel default colors */
                }
            }
            $series = new DataSeries($type, $grouping, range(0, 0), $labels, $cats, [$vals]);
            if ($type === DataSeries::TYPE_BARCHART) $series->setPlotDirection(DataSeries::DIRECTION_COL);

            $layout = new Layout();
            if ($pct) {
                $layout->setShowPercent(true);
            } else {
                $layout->setShowVal(true);
            }

            $chart = new Chart(
                $name,
                new Title($title),
                new Legend(Legend::POSITION_RIGHT, null, false),
                new PlotArea($layout, [$series])
            );
            $chart->setTopLeftPosition($tl);
            $chart->setBottomRightPosition($br);
            $sh->addChart($chart);
        };

        $addChart(
            'pay_pie',
            'Sales by Payment (%)',
            DataSeries::TYPE_PIECHART,
            null,
            $payStart - 1,
            $payStart,
            $payEnd,
            'A',
            'B',
            'F7',
            'N21',
            true
        );
        $addChart(
            'cat_bar',
            'Revenue by Category',
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            $catStart - 1,
            $catStart,
            $catEnd,
            'A',
            'D',
            'F23',
            'N37',
            false
        );
        $addChart(
            'prod_bar',
            'Top 10 Products (Revenue)',
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            $prodStart - 1,
            $prodStart,
            $prodEnd,
            'A',
            'D',
            'F39',
            'N55',
            false
        );
        $addChart(
            'prod_qty_bar',
            'Top 10 Products (Qty)',
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            $prodStart - 1,
            $prodStart,
            $prodEnd,
            'A',
            'B',
            'F57',
            'N73',
            false
        );

        /* ============ SHEETS 2 & 3 — SALES DATA (USD & KHR) ============ */
        $buildDataSheet = function (Worksheet $sh2, bool $isKhr) use ($headers, $khr, $BAR, $INK, $usdFmt, $khrFmt) {
            $curLabel = $isKhr ? 'KHR ៛' : 'USD $';
            $fmt      = $isKhr ? $khrFmt : $usdFmt;

            $cols = [
                'Date',
                'Invoice No',
                'Customer',
                'Payment',
                'Currency',
                'Rate',
                'Item Code',
                'Product',
                'Category',
                'Qty',
                'UOM',
                "Price ({$curLabel})",
                "Disc ({$curLabel})",
                "VAT ({$curLabel})",
                "Net ({$curLabel})",
                "Grand ({$curLabel})"
            ];
            $sh2->fromArray($cols, null, 'A1');
            $sh2->getStyle('A1:P1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BAR]],
            ]);
            $sh2->getRowDimension(1)->setRowHeight(20);
            $sh2->freezePane('A2');

            $data = [];
            foreach ($headers as $h) {
                $factor = (float) ($h->factor ?: 4100);
                foreach ($h->lines as $l) {
                    $conv = fn($v) => $isKhr ? $khr($v, $factor) : (float) $v;
                    $data[] = [
                        Carbon::parse($h->invoice_date)->format('Y-m-d'),
                        $h->invoice_number,
                        $h->contact_name ?: 'General',
                        $h->payment_method,
                        $h->currency_name ?: '៛',
                        $factor,
                        $l->item_code,
                        $l->name,
                        $l->category_name,
                        (float) $l->quantity,
                        $l->unit,
                        $conv($l->sell_price),
                        $conv($l->discount_amount),
                        $conv($l->vat_amount),
                        $conv($l->net_amount),
                        $conv($l->grand_total_amount),
                    ];
                }
            }
            if ($data) $sh2->fromArray($data, null, 'A2');
            $end = $data ? count($data) + 1 : 2;

            $tr = $end + 1;
            $sh2->setCellValue("A{$tr}", 'TOTAL');
            foreach (['J', 'M', 'N', 'O', 'P'] as $c) $sh2->setCellValue("{$c}{$tr}", "=SUM({$c}2:{$c}{$end})");
            $sh2->getStyle("A{$tr}:P{$tr}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $INK]],
            ]);
            foreach (['L', 'M', 'N', 'O', 'P'] as $c) $sh2->getStyle("{$c}2:{$c}{$tr}")->getNumberFormat()->setFormatCode($fmt);
            $sh2->getStyle("J2:J{$tr}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sh2->getStyle("F2:F{$end}")->getNumberFormat()->setFormatCode('#,##0');
            $sh2->setAutoFilter("A1:P{$end}");
            foreach (['A' => 12, 'B' => 16, 'C' => 20, 'D' => 11, 'E' => 9, 'F' => 8, 'G' => 13, 'H' => 26, 'I' => 15, 'J' => 8, 'K' => 8, 'L' => 13, 'M' => 12, 'N' => 11, 'O' => 14, 'P' => 15] as $c => $w) {
                $sh2->getColumnDimension($c)->setWidth($w);
            }
            $sh2->setShowGridlines(false);
        };

        $usdSheet = $ss->createSheet();
        $usdSheet->setTitle('Sales Data USD');
        $buildDataSheet($usdSheet, false);
        $khrSheet = $ss->createSheet();
        $khrSheet->setTitle('Sales Data KHR');
        $buildDataSheet($khrSheet, true);

        $ss->setActiveSheetIndex(0);
        $name = "sales-report_{$from}_to_{$to}.xlsx";
        return response()->streamDownload(function () use ($ss) {
            $writer = new XlsxWriter($ss);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
