<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\InvoiceLine;
use App\Models\PosProfile;
use App\Models\Product;
use App\Models\PurchaseHeader;
use App\Models\UserWarehouse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
// add these imports at the top of PurchasingController.php
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;
class PurchasingController extends Controller
{



    public function Purchasing()
    {
        $warehouse_ids = Auth::user()->warehouses->pluck('id');


        // 1️⃣ Load products with only the selected warehouse
        $products = Product::with(['warehouses' => function ($q) use ($warehouse_ids) {
            $q->whereIn('warehouse_id', $warehouse_ids);
        }])
            ->where('track_stock', 1)
            ->where('status', 1)
            ->get();

        // 2️⃣ Sum stock per product (only from this warehouse)
        $products->each(function ($product) {
            $product->total_stock = $product->warehouses->sum(function ($wh) {
                return $wh->pivot->quantity ?? 0;
            });
        });

        // 3️⃣ Sort: in-stock first, then by name ascending
        $products = $products->sort(function ($a, $b) {
            if ($a->total_stock == 0 && $b->total_stock > 0) return 1;
            if ($a->total_stock > 0 && $b->total_stock == 0) return -1;
            return strcmp($a->name, $b->name);
        })->values();

        // 4️⃣ Group by category (limit 50 per category)
        $categories = [];
        foreach ($products as $product) {
            $category = $product->category_name ?? 'Uncategorized';
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            if (count($categories[$category]) < 50) {
                $categories[$category][] = $product;
            }
        }
            $byId = $products->keyBy('id');

        $topSellerRows = InvoiceLine::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity) as sold_qty')
            ->whereNotNull('product_id')
            // ->where('created_at', '>=', now()->subDays(90))  // ← uncomment for a rolling 90-day window
            ->groupBy('product_id')
            ->orderByDesc('sold_qty')
            ->limit(60)                // buffer; some ids drop out below
            ->get();

        $top_products = [];
        foreach ($topSellerRows as $row) {
            $p = $byId->get($row->product_id);
            if (!$p) continue;         // product inactive / filtered out for this role
            $top_products[] = $p;
            if (count($top_products) >= 30) break;
        }
        // 5️⃣ Currency
        $currency = Currency::where('code', '<>', 'USD')->get();
        $currency_default = Currency::where('is_default', 1)->first();
        $factor = $currency_default ? $currency_default->factor : 1;
        $currency_name = $currency_default ? $currency_default->code : 'USD';

        $posInfoForPrint = PosProfile::forUser(Auth::id());
        $posInfoForPrint = $posInfoForPrint ? $posInfoForPrint->toArray() : [];
        $posInfoForPrint['logo_url'] = \App\Http\Controllers\PosProfileController::logoUrl();

        return view('backend.purchasing', compact('categories', 'currency', 'factor', 'currency_name', 'top_products', 'posInfoForPrint'));
    }

    public function search(Request $request)
    {
        // 👈 Use input() instead of $request->query
        $query = $request->input('query', '');
        $field = $request->input('field', 'name');

        $warehouse_ids = Auth::user()->warehouses->pluck('id');

        // Optional: whitelist allowed fields to prevent SQL injection
        $allowedFields = ['name', 'description', 'code', 'barcode'];
        if (!in_array($field, $allowedFields)) {
            $field = 'name';
        }

        $products = Product::with(['warehouses' => function ($q) use ($warehouse_ids) {
            $q->whereIn('warehouse_id', $warehouse_ids);
        }])
            ->where('status', 1)
            ->where('track_stock', 1)
            ->where('type', '!=', 'expence')
            // ✅ Dynamic search based on field selected
            ->when($query !== '', function ($q) use ($field, $query) {
                $q->where($field, 'LIKE', "%{$query}%");
            })

            ->limit(41)
            ->get();
        // 2️⃣ Sum stock per product (only from this warehouse)
        $products->each(function ($product) {
            $product->total_stock = $product->warehouses->sum(function ($wh) {
                return $wh->pivot->quantity ?? 0;
            });
        });

        // 3️⃣ Sort: in-stock first, then by name ascending
        $products = $products->sort(function ($a, $b) {
            if ($a->total_stock == 0 && $b->total_stock > 0) return 1;
            if ($a->total_stock > 0 && $b->total_stock == 0) return -1;
            return strcmp($a->name, $b->name);
        })->values();

        return response()->json($products);
    }

    public function fetchPurchaseDoc(Request $request)
    {
        $no = trim((string) $request->input('no', ''));
        if ($no === '') return response()->json(['error' => 'Missing document no'], 422);

        $doc = PurchaseHeader::with([
            'vendor:id,name,phone1,contact_person',
            'lines:id,document_no,product_id,item_code,barcode,name,variant,description,quantity,unit,lot,expire_date,category_name,unit_cost,line_amount,remark',
        ])->where('no', $no)->first();

        if (!$doc) return response()->json(['error' => 'Document not found'], 404);
        return response()->json($doc);
    }
    public function fetchPurchaseLines(Request $request)
    {
        // line-level conditions — applied to BOTH whereHas and the eager-load
        $lineFilter = function ($l) use ($request) {
            if ($request->filled('product_filter')) {
                $p = $request->product_filter;
                $l->where(fn($w) => $w->where('name', 'like', "%{$p}%")
                    ->orWhere('item_code', 'like', "%{$p}%")
                    ->orWhere('barcode', 'like', "%{$p}%"));
            }
            if ($request->filled('variant_filter')) $l->where('variant', 'like', "%{$request->variant_filter}%");
            if ($request->filled('lot_filter'))     $l->where('lot', 'like', "%{$request->lot_filter}%");
            if ($request->filled('category_filter')) $l->where('category_name', $request->category_filter);
            if ($request->filled('doc_filter'))     $l->where('document_no', 'like', "%{$request->doc_filter}%");

            if ($request->boolean('returns_only'))  $l->where('quantity', '<', 0);
            return $l;
        };

        $hasLineFilter = $request->filled('product_filter')
            || $request->filled('variant_filter')
            || $request->filled('lot_filter')
            || $request->filled('category_filter')
            || $request->boolean('returns_only')
              || $request->filled('doc_filter');

        $query = PurchaseHeader::with([
            'vendor:id,name,phone1,contact_person',
            'lines' => function ($l) use ($lineFilter, $hasLineFilter) {
                $l->select([
                    'id',
                    'document_no',
                    'product_id',
                    'item_code',
                    'barcode',
                    'name',
                    'variant',
                    'quantity',
                    'unit',
                    'lot',
                    'expire_date',
                    'category_name',
                    'unit_cost',
                    'line_amount',
                    'remark',
                ]);
                if ($hasLineFilter) $lineFilter($l);
            },
        ])->select(['id', 'no', 'vendor_id', 'posting_date', 'currency_name', 'factor', 'payment_method', 'created_by', 'remark']);

        if ($request->filled('from_date')) $query->whereDate('posting_date', '>=', $request->from_date);
        if ($request->filled('to_date'))   $query->whereDate('posting_date', '<=', $request->to_date);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('no', 'like', "%{$search}%")
                    ->orWhere('created_by', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn($v) => $v->where('name', 'like', "%{$search}%")
                        ->orWhere('phone1', 'like', "%{$search}%")
                        ->orWhere('phone2', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('lines', fn($l) => $l->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('lot', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('vendor_filter')) $query->where('vendor_id', $request->vendor_filter);
        if ($hasLineFilter) $query->whereHas('lines', fn($l) => $lineFilter($l));

        $headers = $query->orderByDesc('posting_date')->orderByDesc('id')->get();

        // flatten to one row per line, carrying the header fields
        $flat = [];
        foreach ($headers as $h) {
            $factor = (float) ($h->factor ?: 1);
            foreach ($h->lines as $ln) {
                $flat[] = [
                    'line_id'       => $ln->id,
                    'document_no'   => $h->no,
                    'posting_date'  => $h->posting_date ? Carbon::parse($h->posting_date)->format('Y-m-d') : '',
                    'vendor_name'   => optional($h->vendor)->name ?? '',
                    'vendor_phone'  => optional($h->vendor)->phone1 ?? '',
                    'item_code'     => $ln->item_code ?? '',
                    'barcode'       => $ln->barcode ?? '',
                    'name'          => $ln->name ?? '',
                    'variant'       => $ln->variant ?? '',
                    'lot'           => $ln->lot ?? '',
                    'expire_date'   => $ln->expire_date ? Carbon::parse($ln->expire_date)->format('Y-m-d') : '',
                    'quantity'      => (float) $ln->quantity,
                    'unit'          => $ln->unit ?? '',
                    'unit_cost'     => (float) $ln->unit_cost,
                    'line_amount'   => (float) $ln->line_amount,
                    'category_name' => $ln->category_name ?? '',
                    'remark'        => $ln->remark ?? '',
                    'created_by'    => $h->created_by ?? '',
                    'currency_name' => $h->currency_name ?? '',
                    'factor'        => $factor,
                ];
            }
        }

        $page  = max(1, (int) $request->input('page', 1));
        $limit = (int) $request->input('limit', 100);
        if ($limit < 1) $limit = 100;
        $total = count($flat);
        $slice = array_slice($flat, ($page - 1) * $limit, $limit);

        return response()->json([
            'data'         => $slice,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $limit)),
            'total'        => $total,
        ]);
    }
    /* ============================================================

    |  PREMIUM EXCEL EXPORT  ·  Purchasing list (honours filters)
 |  Requires: composer require phpoffice/phpspreadsheet
 * ============================================================ */
    /* ============================================================
 |  PREMIUM EXCEL EXPORT  ·  Purchasing list
 |  One sheet per currency found in the data (USD + KHR + any others)
 |  Base values are USD; foreign sheets convert by each row's `factor`.
 * ============================================================ */
    public function exportPurchase(Request $request): StreamedResponse
    {
        // ── same line-filter closure as fetchPurchase (keeps export == screen) ──
        $lineFilter = function ($l) use ($request) {
            if ($request->filled('product_filter')) {
                $p = $request->product_filter;
                $l->where(fn($w) => $w->where('name', 'like', "%{$p}%")
                    ->orWhere('item_code', 'like', "%{$p}%")
                    ->orWhere('barcode', 'like', "%{$p}%"));
            }
            if ($request->filled('lot_filter'))      $l->where('lot', 'like', "%{$request->lot_filter}%");
            if ($request->filled('category_filter')) $l->where('category_name', $request->category_filter);
            if ($request->filled('expire_from'))     $l->whereDate('expire_date', '>=', $request->expire_from);
            if ($request->filled('expire_to'))       $l->whereDate('expire_date', '<=', $request->expire_to);
            if ($request->boolean('returns_only'))   $l->where('quantity', '<', 0);
            return $l;
        };

        $hasLineFilter = $request->filled('product_filter')
            || $request->filled('lot_filter')
            || $request->filled('category_filter')
            || $request->filled('expire_from')
            || $request->filled('expire_to')
            || $request->boolean('returns_only');

        $query = PurchaseHeader::with([
            'vendor:id,name,phone1,contact_person',
            'lines' => function ($l) use ($lineFilter, $hasLineFilter) {
                $l->select([
                    'id', 'document_no', 'product_id', 'item_code', 'barcode', 'name',
                    'variant', 'quantity', 'unit', 'lot', 'expire_date', 'category_name',
                    'unit_cost', 'line_amount', 'remark',
                ]);
                if ($hasLineFilter) $lineFilter($l);
            },
        ])->select([
            'id', 'no', 'vendor_id', 'posting_date', 'currency_name', 'factor',
            'payment_method', 'location_id', 'location_name', 'created_by', 'remark',
        ]);

        if ($request->filled('from_date')) $query->whereDate('posting_date', '>=', $request->from_date);
        if ($request->filled('to_date'))   $query->whereDate('posting_date', '<=', $request->to_date);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('no', 'like', "%{$search}%")
                    ->orWhere('created_by', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn($v) => $v->where('name', 'like', "%{$search}%")
                        ->orWhere('phone1', 'like', "%{$search}%")
                        ->orWhere('phone2', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('lines', fn($l) => $l->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('lot', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('vendor_filter')) $query->where('vendor_id', $request->vendor_filter);
        if ($hasLineFilter) $query->whereHas('lines', fn($l) => $lineFilter($l));

        $purchases = $query->orderByDesc('posting_date')->orderByDesc('id')->get();

        // ── warehouse per document: header.location, fallback → ledger ──
        $missing = $purchases->filter(fn($p) => blank($p->location))->pluck('no');
        $ledgerWh = $missing->isEmpty() ? collect() :
            DB::table('item_ledger_entries')
                ->whereIn('document_no', $missing)
                ->where('document_type', 'Purchase')
                ->groupBy('document_no')
                ->selectRaw('document_no, MIN(warehouse_name) as wh')
                ->pluck('wh', 'document_no');
        $whOf = fn($p) => $p->location ?: ($ledgerWh[$p->no] ?? '(unknown)');

        $khr = fn($usd, $factor) => round(((float) $usd * (float) ($factor ?: 4100)) / 100) * 100;

        // ── aggregate ──
        $docs = count($purchases);
        $units = 0; $totalCost = 0;
        $byWh = []; $byVendor = []; $byCategory = []; $byProduct = [];
        foreach ($purchases as $p) {
            $wh = $whOf($p);
            $vn = optional($p->vendor)->name ?: 'Unknown';
            foreach ($p->lines as $l) {
                $qty = (float) $l->quantity;
                $amt = $l->line_amount !== null ? (float) $l->line_amount : $qty * (float) $l->unit_cost;
                $units += $qty;
                $totalCost += $amt;

                $byWh[$wh] = ($byWh[$wh] ?? 0) + $amt;
                $byVendor[$vn] = ($byVendor[$vn] ?? 0) + $amt;

                $ck = $l->category_name ?: '(uncategorised)';
                $byCategory[$ck] = $byCategory[$ck] ?? ['qty' => 0, 'amt' => 0];
                $byCategory[$ck]['qty'] += $qty;
                $byCategory[$ck]['amt'] += $amt;

                $pk = $l->name ?: 'Unknown';
                $byProduct[$pk] = $byProduct[$pk] ?? ['qty' => 0, 'unit' => $l->unit ?: '', 'amt' => 0];
                $byProduct[$pk]['qty'] += $qty;
                $byProduct[$pk]['amt'] += $amt;
            }
        }
        arsort($byWh);
        arsort($byVendor);
        uasort($byCategory, fn($a, $b) => $b['amt'] <=> $a['amt']);
        uasort($byProduct, fn($a, $b) => $b['amt'] <=> $a['amt']);
        $byVendor = array_slice($byVendor, 0, 10, true);
        $topProducts = array_slice($byProduct, 0, 10, true);

        $BAR = 'FF0F172A'; $INK = 'FF1E293B'; $CARD = 'FFF8FAFC'; $LINE = 'FFE2E8F0';
        $CYAN = 'FF0891B2'; $GREEN = 'FF059669'; $AMBER = 'FFD97706'; $VIOLET = 'FF7C3AED';
        $BLUE = 'FF2563EB'; $SUBTXT = 'FF64748B';
        $usdFmt = '"$"#,##0.00;[Red]-"$"#,##0.00';
        $khrFmt = '#,##0"៛";[Red]-#,##0"៛"';

        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Khmer OS Siemreap')->setSize(10);

        /* ================= SHEET 1 — SUMMARY ================= */
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Summary');
        $sh->setShowGridlines(false);
        foreach (['A' => 26, 'B' => 12, 'C' => 9, 'D' => 15, 'E' => 3, 'F' => 13, 'G' => 13,
                  'H' => 13, 'I' => 13, 'J' => 13, 'K' => 13, 'L' => 13, 'M' => 13, 'N' => 13] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }

        $sh->mergeCells('A1:N1');
        $sh->setCellValue('A1', 'PURCHASING REPORT');
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BAR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sh->getRowDimension(1)->setRowHeight(38);

        $sh->mergeCells('A2:N2');
        $sh->setCellValue('A2',
            ($request->from_date ?: '—') . '  –  ' . ($request->to_date ?: '—')
            . '     ·     Vendor: ' . ($request->vendor_filter ? 'Filtered' : 'All')
            . '     ·     Category: ' . ($request->category_filter ?: 'All')
            . '     ·     By ' . (Auth::user()->username ?? 'System')
            . '  at ' . now()->format('d M Y H:i'));
        $sh->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['argb' => 'FFCBD5E1']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $INK]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sh->getRowDimension(2)->setRowHeight(20);

        // KPI cards
        $cards = [
            ['DOCUMENTS (GRN)', $docs,       '#,##0',    $BLUE,   'A', 'B'],
            ['UNITS RECEIVED',  $units,      '#,##0.##', $VIOLET, 'C', 'D'],
            ['WAREHOUSES',      count($byWh), '#,##0',   $CYAN,   'F', 'G'],
            ['VENDORS',         count($byVendor), '#,##0', $AMBER, 'H', 'I'],
            ['TOTAL COST (KHR)', $khr($totalCost, 4100), $khrFmt, $GREEN, 'K', 'L'],
            ['TOTAL COST (USD)', $totalCost, $usdFmt,    $GREEN,  'M', 'N'],
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
            foreach ($cols as $col => $text) $sh->setCellValue("{$col}{$row}", $text);
            $first = array_key_first($cols); $last = array_key_last($cols);
            $sh->getStyle("{$first}{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $SUBTXT]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $CARD]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $LINE]]],
            ]);
        };

        // BY WAREHOUSE (A:C — amount + % share)
        $r = 7;
        $section($r, 'BY WAREHOUSE', 'C'); $r++;
        $tableHead($r, ['A' => 'Warehouse', 'B' => 'Cost', 'C' => '%']); $r++;
        $whStart = $r;
        foreach ($byWh as $w => $amt) {
            $sh->setCellValue("A{$r}", $w);
            $sh->setCellValue("B{$r}", round($amt, 2));
            $sh->setCellValue("C{$r}", $totalCost > 0 ? $amt / $totalCost : 0);
            $sh->getStyle("B{$r}")->getNumberFormat()->setFormatCode($usdFmt);
            $sh->getStyle("C{$r}")->getNumberFormat()->setFormatCode('0.0%');
            $r++;
        }
        $whEnd = $r - 1;

        // BY VENDOR (top 10)
        $r += 1;
        $section($r, 'TOP VENDORS', 'B'); $r++;
        $tableHead($r, ['A' => 'Vendor', 'B' => 'Cost']); $r++;
        $venStart = $r;
        foreach ($byVendor as $v => $amt) {
            $sh->setCellValue("A{$r}", $v);
            $sh->setCellValue("B{$r}", round($amt, 2));
            $sh->getStyle("B{$r}")->getNumberFormat()->setFormatCode($usdFmt);
            $r++;
        }
        $venEnd = $r - 1;

        // BY CATEGORY
        $r += 1;
        $section($r, 'BY CATEGORY', 'D'); $r++;
        $tableHead($r, ['A' => 'Category', 'B' => 'Qty', 'C' => '', 'D' => 'Cost']); $r++;
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

        // TOP 10 PRODUCTS
        $r += 1;
        $section($r, 'TOP 10 PRODUCTS (BY COST)', 'D'); $r++;
        $tableHead($r, ['A' => 'Product', 'B' => 'Qty', 'C' => 'UOM', 'D' => 'Cost']); $r++;
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

        // zebra
        foreach ([[$whStart, $whEnd, 'C'], [$venStart, $venEnd, 'B'], [$catStart, $catEnd, 'D'], [$prodStart, $prodEnd, 'D']] as [$a, $b, $lc]) {
            for ($i = $a; $i <= $b; $i++) {
                if (($i - $a) % 2 === 1) {
                    $sh->getStyle("A{$i}:{$lc}{$i}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($CARD);
                }
            }
        }

        // ── charts (F..N) ──
        $mkFills = function (int $count) {
            $palette = ['0891B2', '059669', 'D97706', '7C3AED', 'E11D48', '2563EB', 'DB2777', '65A30D'];
            $out = [];
            for ($i = 0; $i < $count; $i++) $out[] = $palette[$i % count($palette)];
            return $out;
        };
        $addChart = function (string $name, string $title, string $type, ?string $grouping,
                              int $lblRow, int $s, int $e, string $catCol, string $valCol,
                              string $tl, string $br, bool $pct) use ($sh, $mkFills) {
            $count = $e - $s + 1;
            if ($count < 1) return;
            $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Summary!\${$valCol}\${$lblRow}", null, 1)];
            $cats   = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Summary!\${$catCol}\${$s}:\${$catCol}\${$e}", null, $count)];
            $vals   = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Summary!\${$valCol}\${$s}:\${$valCol}\${$e}", null, $count);
            if (method_exists($vals, 'setFillColor')) {
                try {
                    $vals->setFillColor($type === DataSeries::TYPE_PIECHART ? $mkFills($count) : $mkFills(1)[0]);
                } catch (\Throwable $x) {}
            }
            $series = new DataSeries($type, $grouping, range(0, 0), $labels, $cats, [$vals]);
            if ($type === DataSeries::TYPE_BARCHART) $series->setPlotDirection(DataSeries::DIRECTION_COL);
            $layout = new Layout();
            $pct ? $layout->setShowPercent(true) : $layout->setShowVal(true);
            $chart = new Chart($name, new Title($title), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea($layout, [$series]));
            $chart->setTopLeftPosition($tl);
            $chart->setBottomRightPosition($br);
            $sh->addChart($chart);
        };

        $addChart('wh_pie',   'Cost by Warehouse (%)',   DataSeries::TYPE_PIECHART, null,
            $whStart - 1, $whStart, $whEnd, 'A', 'B', 'F7',  'N21', true);
        $addChart('ven_bar',  'Top Vendors (Cost)',      DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            $venStart - 1, $venStart, $venEnd, 'A', 'B', 'F23', 'N37', false);
        $addChart('cat_bar',  'Cost by Category',        DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            $catStart - 1, $catStart, $catEnd, 'A', 'D', 'F39', 'N53', false);
        $addChart('prod_bar', 'Top 10 Products (Cost)',  DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            $prodStart - 1, $prodStart, $prodEnd, 'A', 'D', 'F55', 'N71', false);

        /* ============ SHEETS 2 & 3 — PURCHASE DATA (USD & KHR) ============ */
        $buildDataSheet = function (Worksheet $sh2, bool $isKhr) use ($purchases, $whOf, $khr, $BAR, $INK, $usdFmt, $khrFmt) {
            $curLabel = $isKhr ? 'KHR ៛' : 'USD $';
            $fmt = $isKhr ? $khrFmt : $usdFmt;

            $cols = ['Date', 'Document', 'Vendor', 'Warehouse', 'Currency', 'Rate',
                     'Item Code', 'Product', 'Category', 'Lot', 'Expire', 'Qty', 'UOM',
                     "Unit Cost ({$curLabel})", "Line Total ({$curLabel})", 'Created By'];
            $sh2->fromArray($cols, null, 'A1');
            $sh2->getStyle('A1:P1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BAR]],
            ]);
            $sh2->getRowDimension(1)->setRowHeight(20);
            $sh2->freezePane('A2');

            $data = [];
            foreach ($purchases as $p) {
                $factor = (float) ($p->factor ?: 4100);
                $wh = $whOf($p);
                foreach ($p->lines as $l) {
                    $qty  = (float) $l->quantity;
                    $cost = (float) $l->unit_cost;
                    $tot  = $l->line_amount !== null ? (float) $l->line_amount : $qty * $cost;
                    $conv = fn($v) => $isKhr ? $khr($v, $factor) : (float) $v;
                    $data[] = [
                        Carbon::parse($p->posting_date)->format('Y-m-d'),
                        $p->no,
                        optional($p->vendor)->name ?: 'Vendor',
                        $wh,
                        $p->currency_name ?: '៛',
                        $factor,
                        $l->item_code,
                        $l->name,
                        $l->category_name,
                        $l->lot ?: '',
                        $l->expire_date ? Carbon::parse($l->expire_date)->format('Y-m-d') : '',
                        $qty,
                        $l->unit,
                        $conv($cost),
                        $conv($tot),
                        $p->created_by,
                    ];
                }
            }
            if ($data) $sh2->fromArray($data, null, 'A2');
            $end = $data ? count($data) + 1 : 2;

            $tr = $end + 1;
            $sh2->setCellValue("A{$tr}", 'TOTAL');
            foreach (['L', 'O'] as $c) $sh2->setCellValue("{$c}{$tr}", "=SUM({$c}2:{$c}{$end})");
            $sh2->getStyle("A{$tr}:P{$tr}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $INK]],
            ]);
            foreach (['N', 'O'] as $c) $sh2->getStyle("{$c}2:{$c}{$tr}")->getNumberFormat()->setFormatCode($fmt);
            $sh2->getStyle("L2:L{$tr}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sh2->getStyle("F2:F{$end}")->getNumberFormat()->setFormatCode('#,##0');
            $sh2->setAutoFilter("A1:P{$end}");
            foreach (['A' => 12, 'B' => 15, 'C' => 24, 'D' => 15, 'E' => 9, 'F' => 8, 'G' => 13,
                      'H' => 30, 'I' => 14, 'J' => 13, 'K' => 11, 'L' => 8, 'M' => 8,
                      'N' => 14, 'O' => 15, 'P' => 12] as $c => $w) {
                $sh2->getColumnDimension($c)->setWidth($w);
            }
            $sh2->setShowGridlines(false);
        };

        $usdSheet = $ss->createSheet(); $usdSheet->setTitle('Purchase Data USD'); $buildDataSheet($usdSheet, false);
        $khrSheet = $ss->createSheet(); $khrSheet->setTitle('Purchase Data KHR'); $buildDataSheet($khrSheet, true);

        $ss->setActiveSheetIndex(0);
        $name = 'purchasing_' . now()->format('Ymd_His') . '.xlsx';
        return response()->streamDownload(function () use ($ss) {
            $writer = new XlsxWriter($ss);
            $writer->setIncludeCharts(true);      // ← without this, no charts in the file
            $writer->save('php://output');
        }, $name, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
