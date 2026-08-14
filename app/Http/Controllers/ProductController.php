<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
class ProductController extends Controller
{
    public $warehouse_id = 1;
    /**
     * 🔍 Search products (LOT-aware stock)
     */
    public function search(Request $request)
    {
        $allowed = ['name', 'code', 'barcode'];
        $field = in_array($request->query('field'), $allowed)
            ? $request->query('field')
            : 'name';

        $query = $request->query('query', '');

        $products = Product::with(['warehouses' => function ($q) {
            $q->where('warehouse_id', $this->warehouse_id);
        }])
            ->where('status', 1)
            ->where($field, 'like', "%{$query}%")
            ->get();

        // ✅ LOT-aware stock sum
        $products->each(function ($product) {
            $product->total_stock = $product->warehouses->sum(fn($wh) => $wh->pivot->quantity ?? 0);
        });

        // Sort: in-stock first, then name
        $products = $products->sort(function ($a, $b) {
            if ($a->total_stock == 0 && $b->total_stock > 0) return 1;
            if ($a->total_stock > 0 && $b->total_stock == 0) return -1;
            return strcmp($a->name, $b->name);
        })->values();

        return response()->json($products);
    }


    public function list_search(Request $request)
    {
        $limit = $request->query('limit', 30);

        $query = Product::query()->with('category'); // eager load category

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%$s%")
                    ->orWhere('name', 'like', "%$s%")
                    ->orWhere('variant', 'like', "%$s%")
                    ->orWhere('description', 'like', "%$s%")
                    ->orWhereHas('category', function ($q2) use ($s) {
                        $q2->where('name', 'like', "%$s%");
                    });
            });
        }

        // Filter by category (frontend type = category_id)
        if ($request->filled('type')) {
            $query->where('category_id', $request->type);
        }

        // Filter by status
        if ($request->filled('status') != '') {
            if ($request->filled('status') && is_numeric($request->status)) {
                $query->where('status', $request->status);
            }
        }


        // Filter by track_stock
        if ($request->filled('track_stock')) {
            $query->where('track_stock', $request->track_stock);
        }

        // Sorting
        $sortableColumns = [
            'id',
            'code',
            'name',
            'variant',
            'sell_price',
            'cost',
            'vat',
            'discount_percent',
            'last_purchase_price',
            'min_stock',
            'max_stock',
            'status'
        ];

        if ($request->filled('sort_by') && in_array($request->sort_by, $sortableColumns)) {
            $dir = $request->query('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->sort_by, $dir);
        } else {
            $query->orderBy('id', 'desc');
        }

        // Return paginated products including category
        $products = $query->paginate($limit);

        // Optional: map to include only fields you want + category name
        $products->getCollection()->transform(function ($product) {
            return [
                'id' => $product->id,
                'code' => $product->code,
                'bar_code' => $product->bar_code,
                'name' => $product->name,
                'variant' => $product->variant,
                'description' => $product->description,
                'sell_price' => $product->sell_price,
                'image' => $product->image,
                'cost' => $product->cost,
                'vat' => $product->vat,
                'discount_percent' => $product->discount_percent,
                'last_purchase_price' => $product->last_purchase_price,
                'category_id' => $product->category_id,
                'min_stock' => $product->min_stock,
                'max_stock' => $product->max_stock,
                'track_stock' => $product->track_stock,
                'allow_discount' => $product->allow_discount,
                'allow_return' => $product->allow_return,
                'category_name' => $product->category_name,
                'status' => $product->status,
                'unit' => $product->unit,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name
                ] : null,
            ];
        });

        return response()->json($products);
    }

    public function store(Request $request)
    {



        $data = $request->validate([
            'code' => 'required|unique:product,code',
            'name' => 'required',
            'sell_price' => 'numeric',
            'cost' => 'numeric',
            'vat' => 'numeric',
            'discount_percent' => 'numeric',
            'type' => 'required|in:product,service,expence',
            'image' => 'nullable|image|max:2048',
        ]);

        // Add extra fields
        $data['bar_code'] = $request->input('bar_code');
        $data['variant'] = $request->input('variant');
        $data['description'] = $request->input('description');
        $data['min_stock'] = $request->input('min_stock', 0);
        $data['max_stock'] = $request->input('max_stock', 0);
        $data['category_id'] = $request->input('category_id');
        $data['category_name'] = $request->input('category_name');
        $data['unit'] = $request->input('unit');
        $data['status'] = $request->has('status');
        $data['allow_discount'] = $request->has('allow_discount');
        $data['allow_return'] = $request->has('allow_return');
        $data['track_stock'] = $request->has('track_stock');

        // Upload image
        if ($request->hasFile('image')) {
            $data['image'] = $this->uploadFileToPublic($request, 'image', $request->name);
        }
         $data['created_by'] = Auth::user()->username ?? 'System';
        Product::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Product added successfully'
        ]);
    }




    public function update(Request $request, $id)
    {
        // 1️⃣ Find the product
        $product = Product::findOrFail($id);

        // 2️⃣ Validate required fields
        $request->validate([
            'code' => 'required|string',
            'name' => 'required|string',
        ]);

        // Flipping track_stock on a product that already has stock recorded
        // would desync warehouse_product from what the POS/reports assume —
        // block it instead of silently letting the toggle through.
        $requestedTrackStock = $request->track_stock ? 1 : 0;
        if ($requestedTrackStock != $product->track_stock) {
            $hasStock = DB::table('warehouse_product')
                ->where('product_id', $product->id)
                ->sum('quantity') > 0;

            if ($hasStock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change Track Stock: this product already has stock recorded in a warehouse.',
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            /* ==========================
            HANDLE IMAGE UPLOAD
            ========================== */
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $folder = 'assets/startic_img';
                $filename = time() . '-' . ($request->code ?? 'product') . '.' . $file->getClientOriginalExtension();

                $file->move(public_path($folder), $filename);

                $product->image = $filename;
            }

            /* ==========================
            UPDATE PRODUCT
            ========================== */
          $product->update([
    'bar_code'         => $request->barcode ?? '',
    'code'             => $request->code,
    'name'             => $request->name,
    'variant'          => $request->variant ?? '',
    'description'      => $request->description ?? '',
    'min_stock'        => $request->min_stock ?? 0,
    'max_stock'        => $request->max_stock ?? 0,
    'cost'             => $request->cost ?? 0,
    'sell_price'       => $request->sell_price ?? 0,
    'vat'              => $request->vat ?? 0,
    'discount_percent' => $request->discount ?? 0,
    'category_id'      => $request->category_id ?? null,
    'category_name'    => $request->category_name ?? '',
    'unit'             => $request->unit ?? '',

    // no type here ✅

    'track_stock'      => $request->track_stock ? 1 : 0,
    'allow_discount'   => $request->allow_discount ? 1 : 0,
    'allow_return'     => $request->allow_return ? 1 : 0,
    'status'           => $request->status ? 1 : 0,
]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'product' => $product
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error($e);

            return response()->json([
                'success' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

/**
 * Next free code in a prefixed series, e.g. FG-0023 -> FG-0024.
 *
 * Only a suggestion: the field stays editable, and store() still enforces
 * uniqueness, so if two people open the form at once the second save is
 * rejected rather than silently colliding.
 *
 * Width comes from the widest existing number, so FG-0009 rolls to FG-0010
 * rather than FG-010.
 */
public function nextCode(Request $request)
{
    $prefix = trim((string) $request->query('prefix', 'FG-'));

    if ($prefix === '') {
        return response()->json(['prefix' => '', 'code' => '']);
    }

    $max = 0;
    $width = 4;

    foreach (Product::where('code', 'like', $prefix . '%')->pluck('code') as $code) {
        // Only trailing digits directly after the prefix count — a code like
        // FG-RAW01 is not part of the numeric series.
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $code, $m)) {
            $max   = max($max, (int) $m[1]);
            $width = max($width, strlen($m[1]));
        }
    }

    return response()->json([
        'prefix' => $prefix,
        'code'   => $prefix . str_pad((string) ($max + 1), $width, '0', STR_PAD_LEFT),
    ]);
}

public function searchByCategory(Request $request)
    {
        $query = trim($request->input('query', ''));
        $field = $request->input('field', 'name');

        // Narrowed to the sale's warehouse when the POS has one selected.
        $warehouse_ids = Warehouse::stockScopeFor(Auth::user(), $request->input('warehouse_id'));

        // ✅ must match the DB column AND what the frontend <select> sends
        $allowedFields = ['name', 'description', 'code', 'bar_code'];
        if (!in_array($field, $allowedFields)) {
            $field = 'name';
        }

        $sql = Product::with(['warehouses' => function ($q) use ($warehouse_ids) {
            $q->whereIn('warehouse_id', $warehouse_ids);
        }]);

        // Was admin+supervisor exempt here but admin-only on the grid, so a
        // supervisor's search returned expense items the grid never showed.
        $sql->sellableForCurrentUser();

        $sql->where('status', 1);

        $sql->when($query !== '', function ($q) use ($field, $query) {
            if ($field === 'bar_code') {
                // exact match → guarantees single result → auto add-to-cart works
                $q->where('bar_code', $query);
            } else {
                $q->where($field, 'LIKE', "%{$query}%");
            }
        });

        $products = $sql->limit(41)->get();

        $products->each(function ($product) {
            $product->total_stock = $product->warehouses->sum(function ($wh) {
                return $wh->pivot->quantity ?? 0;
            });
        });

        $products = $products->sortBy([
            fn($a, $b) => ($b->total_stock > 0) <=> ($a->total_stock > 0),
            fn($a, $b) => strnatcasecmp($a->name ?? '', $b->name ?? ''),
        ])->values();

        return response()->json($products);
    }

/**
     * Small cached JPEG thumbnail for Excel embedding.
     * Original 2MB photos → ~5-10KB thumbs. Cached in storage so
     * repeat exports are instant.
     */

    public function exportProducts(Request $request): StreamedResponse
    {
        $query = Product::query()->with('category');
        $withImages = $request->input('images', '1') === '1';
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%$s%")
                    ->orWhere('name', 'like', "%$s%")
                    ->orWhere('bar_code', 'like', "%$s%")
                    ->orWhere('variant', 'like', "%$s%")
                    ->orWhere('description', 'like', "%$s%");
            });
        }
        if ($request->filled('type'))   $query->where('category_id', $request->type);
        if ($request->filled('status') && is_numeric($request->status)) {
            $query->where('status', $request->status);
        }

        $products = $query->orderBy('category_name')->orderBy('name')->get();

        // total stock per product (all warehouses, summed across lots)
        $stocks = DB::table('warehouse_product')
            ->whereIn('product_id', $products->pluck('id'))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->pluck('qty', 'product_id');

        // ── aggregate ──
        $total = count($products);
        $active = $products->where('status', 1)->count();
        $tracked = $products->where('track_stock', 1)->count();
        $stockValue = 0; $stockUnits = 0;
        $byCategory = [];
        foreach ($products as $p) {
            $qty = (float) ($stocks[$p->id] ?? 0);
            $stockUnits += $qty;
            $stockValue += $qty * (float) ($p->cost ?: 0);

            $ck = $p->category_name ?: '(uncategorised)';
            $byCategory[$ck] = $byCategory[$ck] ?? ['count' => 0, 'qty' => 0, 'value' => 0];
            $byCategory[$ck]['count']++;
            $byCategory[$ck]['qty'] += $qty;
            $byCategory[$ck]['value'] += $qty * (float) ($p->cost ?: 0);
        }
        uasort($byCategory, fn($a, $b) => $b['count'] <=> $a['count']);

        $BAR = 'FF0F172A'; $INK = 'FF1E293B'; $CARD = 'FFF8FAFC'; $LINE = 'FFE2E8F0';
        $CYAN = 'FF0891B2'; $GREEN = 'FF059669'; $AMBER = 'FFD97706'; $VIOLET = 'FF7C3AED';
        $BLUE = 'FF2563EB'; $SUBTXT = 'FF64748B';
        $usdFmt = '"$"#,##0.00;[Red]-"$"#,##0.00';

        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Khmer OS Siemreap')->setSize(10);

        /* ================= SHEET 1 — SUMMARY ================= */
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Summary');
        $sh->setShowGridlines(false);
        foreach (['A' => 26, 'B' => 12, 'C' => 12, 'D' => 15, 'E' => 3, 'F' => 13, 'G' => 13,
                  'H' => 13, 'I' => 13, 'J' => 13, 'K' => 13, 'L' => 13, 'M' => 13, 'N' => 13] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }

        $sh->mergeCells('A1:N1');
        $sh->setCellValue('A1', 'PRODUCT REPORT');
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BAR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sh->getRowDimension(1)->setRowHeight(38);

        $sh->mergeCells('A2:N2');
        $sh->setCellValue('A2',
            'Search: ' . ($request->search ?: 'All')
            . '     ·     Status: ' . ($request->status === '1' ? 'Active' : ($request->status === '0' ? 'Inactive' : 'All'))
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
            ['PRODUCTS',       $total,      '#,##0',    $BLUE,   'A', 'B'],
            ['ACTIVE',         $active,     '#,##0',    $GREEN,  'C', 'D'],
            ['TRACK STOCK',    $tracked,    '#,##0',    $CYAN,   'F', 'G'],
            ['CATEGORIES',     count($byCategory), '#,##0', $AMBER, 'H', 'I'],
            ['STOCK UNITS',    $stockUnits, '#,##0.##', $VIOLET, 'K', 'L'],
            ['STOCK VALUE',    $stockValue, $usdFmt,    $GREEN,  'M', 'N'],
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

        // BY CATEGORY table (A:D)
        $sh->mergeCells('A7:D7');
        $sh->setCellValue('A7', 'BY CATEGORY');
        $sh->getStyle('A7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $INK]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['left' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['argb' => $CYAN]]],
        ]);
        $sh->getRowDimension(7)->setRowHeight(20);

        $r = 8;
        foreach (['A' => 'Category', 'B' => 'Products', 'C' => 'Stock Qty', 'D' => 'Stock Value'] as $col => $txt) {
            $sh->setCellValue("{$col}{$r}", $txt);
        }
        $sh->getStyle("A{$r}:D{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $SUBTXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $CARD]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $LINE]]],
        ]);
        $r++;
        $catStart = $r;
        foreach ($byCategory as $cname => $c) {
            $sh->setCellValue("A{$r}", $cname);
            $sh->setCellValue("B{$r}", $c['count']);
            $sh->setCellValue("C{$r}", round($c['qty'], 2));
            $sh->setCellValue("D{$r}", round($c['value'], 2));
            $sh->getStyle("C{$r}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sh->getStyle("D{$r}")->getNumberFormat()->setFormatCode($usdFmt);
            if (($r - $catStart) % 2 === 1) {
                $sh->getStyle("A{$r}:D{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($CARD);
            }
            $r++;
        }
        $catEnd = $r - 1;

        // charts
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

        $addChart('cat_pie', 'Products by Category (%)', DataSeries::TYPE_PIECHART, null,
            $catStart - 1, $catStart, $catEnd, 'A', 'B', 'F7', 'N23', true);
        $addChart('val_bar', 'Stock Value by Category', DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            $catStart - 1, $catStart, $catEnd, 'A', 'D', 'F25', 'N41', false);

        /* ============ SHEET 2 — PRODUCT DATA (with images) ============ */
        $sh2 = $ss->createSheet();
        $sh2->setTitle('Products');
        $sh2->setShowGridlines(false);

        $cols = ['Image', 'Code', 'Barcode', 'Name', 'Variant', 'Description', 'Category',
                 'Unit', 'Stock Qty', 'Cost', 'Sell Price', 'VAT %', 'Disc %',
                 'Min', 'Max', 'Track', 'Status'];
        $sh2->fromArray($cols, null, 'A1');
        $sh2->getStyle('A1:Q1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BAR]],
        ]);
        $sh2->getRowDimension(1)->setRowHeight(20);
        $sh2->freezePane('A2');

        $r = 2;
        foreach ($products as $p) {
            $qty = (float) ($stocks[$p->id] ?? 0);

            $sh2->setCellValue("B{$r}", $p->code);
            $sh2->setCellValueExplicit("C{$r}", (string) $p->bar_code,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);   // keep leading zeros
            $sh2->setCellValue("D{$r}", $p->name);
            $sh2->setCellValue("E{$r}", $p->variant);
            $sh2->setCellValue("F{$r}", $p->description);
            $sh2->setCellValue("G{$r}", $p->category_name);
            $sh2->setCellValue("H{$r}", $p->unit);
            $sh2->setCellValue("I{$r}", $qty);
            $sh2->setCellValue("J{$r}", (float) $p->cost);
            $sh2->setCellValue("K{$r}", (float) $p->sell_price);
            $sh2->setCellValue("L{$r}", (float) $p->vat);
            $sh2->setCellValue("M{$r}", (float) $p->discount_percent);
            $sh2->setCellValue("N{$r}", (float) $p->min_stock);
            $sh2->setCellValue("O{$r}", (float) $p->max_stock);
            $sh2->setCellValue("P{$r}", $p->track_stock ? 'Yes' : 'No');
            $sh2->setCellValue("Q{$r}", $p->status ? 'Active' : 'Inactive');

            // embedded image (skip silently if file missing)

             if ($withImages) {
                $imgPath = $p->image ? public_path('assets/startic_img/' . $p->image) : null;
                if ($imgPath && is_file($imgPath)) {
                    $thumb = $this->excelThumb($imgPath);
                    if ($thumb) {
                        try {
                            $drawing = new Drawing();
                            $drawing->setPath($thumb);
                            $drawing->setHeight(48);
                            $drawing->setCoordinates("A{$r}");
                            $drawing->setOffsetX(4);
                            $drawing->setOffsetY(3);
                            $drawing->setWorksheet($sh2);
                        } catch (\Throwable $x) {}
                    }
                }
            }
            $sh2->getRowDimension($r)->setRowHeight($withImages ? 40 : 18);
            $r++;
        }
        $end = $r - 1;

        foreach (['J', 'K'] as $c) $sh2->getStyle("{$c}2:{$c}{$end}")->getNumberFormat()->setFormatCode($usdFmt);
        $sh2->getStyle("I2:I{$end}")->getNumberFormat()->setFormatCode('#,##0.##');
        $sh2->setAutoFilter("A1:Q{$end}");
        foreach (['A' => 9, 'B' => 13, 'C' => 15, 'D' => 34, 'E' => 12, 'F' => 30, 'G' => 15,
                  'H' => 8, 'I' => 10, 'J' => 11, 'K' => 11, 'L' => 8, 'M' => 8,
                  'N' => 7, 'O' => 7, 'P' => 7, 'Q' => 10] as $c => $w) {
            $sh2->getColumnDimension($c)->setWidth($w);
        }

        $ss->setActiveSheetIndex(0);
        $name = 'products_' . now()->format('Ymd_His') . '.xlsx';
        return response()->streamDownload(function () use ($ss) {
            $writer = new XlsxWriter($ss);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, $name, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }


        private function excelThumb(string $srcPath, int $maxPx = 96): ?string
    {
        $dir = storage_path('app/excel-thumbs');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $thumb = $dir . '/' . md5($srcPath . filemtime($srcPath) . $maxPx) . '.jpg';
        if (is_file($thumb)) return $thumb;

        $info = @getimagesize($srcPath);
        if (!$info) return null;

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
            IMAGETYPE_PNG  => @imagecreatefrompng($srcPath),
            IMAGETYPE_GIF  => @imagecreatefromgif($srcPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : null,
            default        => null,
        };
        if (!$src) return null;

        [$w, $h] = $info;
        $scale = min($maxPx / $w, $maxPx / $h, 1);
        $nw = max(1, (int) ($w * $scale));
        $nh = max(1, (int) ($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        // white background (kills PNG transparency → clean in Excel)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        imagejpeg($dst, $thumb, 70);
        imagedestroy($src);
        imagedestroy($dst);

        return is_file($thumb) ? $thumb : null;
    }
}
