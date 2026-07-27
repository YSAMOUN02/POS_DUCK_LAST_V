<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ItemLedgerEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use App\Models\Expense;
use App\Models\InvoiceLine;

/**
 * Gain & Cost dashboard — built on the single ItemLedgerEntry table.
 *
 * Each ledger row is one stock movement:
 *   entry_type = 'negative'  -> goods OUT  = a SALE
 *   entry_type = 'positive'  -> goods IN   = a PURCHASE / receipt
 *
 * A sale row already carries BOTH the sale price AND the cost of the exact lot
 * that was sold (`unit_cost`). So profit is computed straight off the row:
 *
 *     line gain = net_amount  −  unit_cost × |quantity|
 *
 * e.g. duck 1kg: net 8000 − lot cost 7000 = 1000.  COGS therefore comes from the
 * sale line itself (the lot you bought), NOT from a separate purchase table, which
 * is what lets you buy last month and still see this month's profit cleanly.
 *
 *   Net Revenue   = Σ sale net_amount
 *   COGS          = Σ (unit_cost × |qty|) on sale rows
 *   Gross / Gain  = Net Revenue − COGS
 *   Operating Exp = Σ expense rows (optional, see $expenseDocTypes)
 *   Net Gain      = Gross − Operating Exp
 *
 * MONEY: amounts are already stored in the base currency and are converted for
 * display using each currency's AVERAGE factor (AVG(factor) per currency_name).
 */
class GainCostController extends Controller
{
    /* ============================================================
     |  EDITABLE CONFIGURATION
     * ============================================================ */

    /** Which entry_type is a sale vs a purchase. */
    private string $saleEntryType     = 'negative';
    private string $purchaseEntryType = 'positive';

    /** document_type of returns — counted separately (units only) and excluded
     *  from the sales/purchase profit so they don't distort revenue or cost. */
    private array $saleReturnDocTypes     = ['Sale Return'];
    private array $purchaseReturnDocTypes = ['Purchase Return'];
    private array $adjustment = ['Adjustment'];
    /** document_type values treated as operating expenses (Σ subtracts from gain).
     *  [] = none (Net Gain = Gross). e.g. ['Expense','Operating Expense']. */


    /** Categories to drop from the P&L. [] = keep all. */
    private array $excludedCategories = [];

    /** Multi-currency display. Amounts are stored already converted to the base
     *  currency, so the base view shows them as-is (rate 1). ANY other currency is
     *  converted with that currency's AVERAGE factor pulled from the data
     *  (AVG(factor) GROUP BY currency_name) — so a 3rd/4th currency just works. */
    private string $baseCurrency = 'USD';

    /** Symbol + decimals per currency code (fallback: the code itself at 2 dp). */
    private array $currencySymbols = [
        'USD' => ['$', 2],
        'KHR' => ['៛', 0],
        'EUR' => ['€', 2],
        'GBP' => ['£', 2],
        'THB' => ['฿', 2],
        'JPY' => ['¥', 0],
        'CNY' => ['¥', 2],
        'VND' => ['₫', 0],
    ];

    /** Rows to drop entirely (internal stock transfers / non-trading moves). */
    private array $excludeDocNoPrefixes = ['TO'];                 // document_no starting with these
    private array $excludeSourceTables  = ['warehouse_product'];  // source_table values
    private bool  $dropZeroValueRows    = true;                   // rows with no sale / line / grand value

    private array $disp = ['code' => 'USD', 'sym' => '$', 'dec' => 2, 'rate' => 1.0];
    private array $crit = [];   // extra request filters: warehouse / created_by / product / category
    private int   $perPage = 8;


    /** Service module schema map — the ONLY place to edit if your columns differ.
     *  Drives BOTH services() (dashboard card) and xlServices() (export), so they
     *  can never drift apart. */
    private array $svc = [
        'head'    => 'sale_invoice_headers',  // header table (parallel to sale_invoice_lines)
        'prod'    => 'product',
        'fk_head' => 'sale_invoice_id',       // line #2  ✓
        'fk_prod' => 'product_id',            // line #3  ✓
        'type'    => 'type',
        'service' => 'service',
        'amt'     => 'line_amount',     // LINE #18 ✓
        'cost'    => 'cost',            // LINE #13 — per-unit cost, for Service Gain  (verify: per-unit vs extended)
        'qty'     => 'quantity',        // LINE #10 ✓
        'amt'     => 'line_amount',           // line #18 ✓
        'qty'     => 'quantity',              // line #10 ✓
        'docno'   => 'document_no',           // line #4  ✓
        'date'    => 'invoice_date',          // header #9  ✓
        'pay'     => 'payment_method',        // header #16 ✓
        'fact'    => 'factor',                // header #18 ✓ (default 1.0)
        'cust'    => 'contact_name',          // header #6  ✓ (was customer_name — wrong)
    ];
     private function serviceBase(string $from, string $to, ?string $pay)
    {
        $s         = $this->svc;
        $lineTable = (new \App\Models\InvoiceLine)->getTable();   // sale_invoice_lines

        // Include BOTH original service lines (qty/amount positive) AND their return
        // mirror lines (qty/amount negative). SUM nets them so a returned delivery
        // fee deducts from service revenue — boss wants the net, not exclusion.
        return \App\Models\InvoiceLine::query()
            ->from("$lineTable as l")
            ->join("{$s['head']} as h", 'h.id', '=', "l.{$s['fk_head']}")
            ->join("{$s['prod']} as p", 'p.id', '=', "l.{$s['fk_prod']}")
            ->where("p.{$s['type']}", $s['service'])
            ->whereBetween("h.{$s['date']}", [$from, $to])
            ->when($pay, fn($q) => $q->where("h.{$s['pay']}", $pay));
    }

    private function isBaseView(): bool
    {
        return strtoupper($this->disp['code']) === strtoupper($this->baseCurrency);
    }


    /* ============================================================
     |  PAGE
     * ============================================================ */
    public function index()
    {
        $payments = ItemLedgerEntry::query()->select('payment_method')
            ->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method');

        $warehouses = ItemLedgerEntry::query()->select('warehouse_id', 'warehouse_name')
            ->whereNotNull('warehouse_id')->distinct()->orderBy('warehouse_name')->get()
            ->map(fn($w) => ['id' => $w->warehouse_id, 'name' => $w->warehouse_name ?: ('WH #' . $w->warehouse_id)])->values();

        $categories = ItemLedgerEntry::query()->select('category_name')
            ->whereNotNull('category_name')->distinct()->orderBy('category_name')->pluck('category_name');

        $createdBys = ItemLedgerEntry::query()->select('created_by')
            ->whereNotNull('created_by')->distinct()->orderBy('created_by')->pluck('created_by');

        $products = ItemLedgerEntry::query()->where('entry_type', $this->saleEntryType)
            ->select('product_id', 'name', 'item_code')->whereNotNull('product_id')->distinct()->orderBy('name')->get()
            ->map(fn($p) => ['id' => $p->product_id, 'name' => $p->name, 'code' => $p->item_code])->values();

        $minDate = ItemLedgerEntry::min('posting_date') ?: now()->subYear()->toDateString();

        $curRows = ItemLedgerEntry::query()->select('currency_name')
            ->selectRaw('AVG(factor) as f, COUNT(*) as n')
            ->whereNotNull('currency_name')->where('currency_name', '<>', '')
            ->groupBy('currency_name')->orderByDesc('n')->get();

        $views = collect();
        $seen = [];
        $bm = $this->currencyMeta($this->baseCurrency);
        $views->push(['code' => $this->baseCurrency, 'sym' => $bm['sym'], 'dec' => $bm['dec'], 'rate' => 1.0]);
        $seen[strtoupper($this->baseCurrency)] = true;
        foreach ($curRows as $c) {
            $code = trim((string) $c->currency_name);
            $up = strtoupper($code);
            if ($code === '' || isset($seen[$up])) continue;
            $seen[$up] = true;
            $m = $this->currencyMeta($code);
            $views->push(['code' => $code, 'sym' => $m['sym'], 'dec' => $m['dec'], 'rate' => round((float) $c->f, 6)]);
        }
        $views = $views->values();

        $filters = [
            'baseCurrency' => $this->baseCurrency,
            'views'        => $views,
            'defaultView'  => $this->baseCurrency,
            'payments'     => $payments,
            'warehouses'   => $warehouses,
            'categories'   => $categories,
            'createdBys'   => $createdBys,
            'products'     => $products,
            'minDate'      => Carbon::parse($minDate)->toDateString(),
            'maxDate'      => now()->toDateString(),
            'from'         => now()->subMonths(6)->startOfMonth()->toDateString(),
            'to'           => now()->toDateString(),
        ];

        return view('backend.gain-cost', compact('filters'));
    }

    /* ============================================================
     |  CURRENCY
     * ============================================================ */
    /** Symbol + decimals for a currency code. */
    private function currencyMeta(string $code): array
    {
        [$sym, $dec] = $this->currencySymbols[strtoupper(trim($code))] ?? [trim($code) . ' ', 2];
        return ['sym' => $sym, 'dec' => (int) $dec];
    }

    /** The conversion rate base -> $code = that currency's AVERAGE factor in the data. */
    private function currencyRate(string $code): float
    {
        if (strtoupper(trim($code)) === strtoupper($this->baseCurrency)) return 1.0;
        return (float) (ItemLedgerEntry::where('currency_name', $code)->avg('factor') ?: 1.0);
    }

    private function setDisplay(Request $r): void
    {
        $code = trim((string) $r->input('view', $this->baseCurrency));
        if ($code === '') $code = $this->baseCurrency;
        if (strtoupper($code) === strtoupper($this->baseCurrency)) $code = $this->baseCurrency;
        $meta = $this->currencyMeta($code);
        $this->disp = ['code' => $code, 'sym' => $meta['sym'], 'dec' => $meta['dec'], 'rate' => $this->currencyRate($code)];
    }

    /** SQL per-row money expression -> chosen view currency (× that currency's avg rate). */
    private function conv(string $amount): string
    {
        // USD (base) view → no conversion
        if (strtoupper($this->disp['code']) === strtoupper($this->baseCurrency)) {
            return '(' . $amount . ')';
        }
        // KHR view → multiply by each row's factor column (never the average)
        return '((' . $amount . ') * factor)';
    }
    private function dispv($amount, $factor = null): float
    {
        // USD (base) view → amounts already in base, no conversion
        if (strtoupper($this->disp['code']) === strtoupper($this->baseCurrency)) {
            return (float) $amount;
        }
        // KHR view → use THIS row's real factor (never the average)
        if ($factor !== null && (float) $factor > 0) {
            return (float) $amount * (float) $factor;
        }
        // safety net: row has no factor (shouldn't happen in your 2-currency data)
        // return amount unconverted rather than crash — NO average used
        return (float) $amount;
    }
    /** Stable document identifier: document_no, else source_no, else entry_no.
     *  (Sales/purchases with a NULL document_no otherwise collapse into one
     *  un-clickable group whose detail comes back empty.) */
    private function docKey(): string
    {
        return "COALESCE(NULLIF(document_no, ''), NULLIF(source_no, ''), CAST(entry_no AS CHAR))";
    }

    private function m($v): string
    {
        $v = (float) $v;
        return ($v < 0 ? '-' : '') . $this->disp['sym'] . number_format(abs($v), $this->disp['dec']);
    }

    /* convenient money expressions on the ledger.
       Profit is computed ON THE SALE LINE from its own sale price and lot cost:
         revenue = sell_price * |qty|,  cost = unit_cost * |qty|,  gain = (sell - cost) * |qty|.
       Purchases never enter this. (If you want revenue NET of discount, swap the
       sale-price expressions for net_amount.) */
    private function eRevenue(): string
    {
        return $this->conv('COALESCE(sell_price, unit_price, 0) * ABS(quantity)');
    }
    private function eCogs(): string
    {
        return $this->conv('unit_cost * ABS(quantity)');
    }
    private function eGain(): string
    {
        return $this->conv('(COALESCE(sell_price, unit_price, 0) - unit_cost) * ABS(quantity)');
    }

    /* ============================================================
     |  SCOPES
     * ============================================================ */
    private function ledger(string $from, string $to, ?string $pay)
    {
        $q = ItemLedgerEntry::query()
            ->whereBetween('posting_date', [$from, $to])
            ->when($pay, fn($qq) => $qq->where('payment_method', $pay));
        return $this->applyCommon($q);
    }

    /** crit filters (warehouse/created_by/product/category) + transfer exclusions,
     *  WITHOUT date/payment — reused for the current-stock snapshot. */
    private function applyCommon($q)
    {
        return $q
            ->when($this->crit['warehouse']  ?? null, fn($qq, $v) => $qq->where('warehouse_id', $v))
            ->when($this->crit['created_by'] ?? null, fn($qq, $v) => $qq->where('created_by', $v))
            ->when($this->crit['product']    ?? null, fn($qq, $v) => $qq->where('product_id', $v))
            ->when($this->crit['category']   ?? null, fn($qq, $v) => $qq->where('category_name', $v))
            ->when($this->excludeSourceTables, fn($qq) => $qq->where(fn($w) => $w
                ->whereNull('source_table')->orWhereNotIn('source_table', $this->excludeSourceTables)))
            ->when($this->excludeDocNoPrefixes, fn($qq) => $qq->where(fn($w) => $w
                ->whereNull('document_no')->orWhere(function ($x) {
                    foreach ($this->excludeDocNoPrefixes as $p) $x->where('document_no', 'not like', $p . '%');
                })))
            ->when($this->dropZeroValueRows, fn($qq) => $qq->where(fn($w) => $w
                ->where('line_amount', '<>', 0)->orWhere('net_amount', '<>', 0)
                ->orWhere('grand_total_amount', '<>', 0)->orWhere('sell_price', '<>', 0)->orWhere('unit_price', '<>', 0)));
    }

    /** Current stock = purchase/receipt lots that still have quantity remaining.
     *  Snapshot: filtered by ITEM / WAREHOUSE / CATEGORY only — no date, payment, or user. */
    private function stockScope()
    {
        return ItemLedgerEntry::query()
            ->leftJoin('product as p', 'p.id', '=', 'item_ledger_entries.product_id')
            ->where('item_ledger_entries.entry_type', $this->purchaseEntryType)
            ->where('item_ledger_entries.remaining_quantity', '<>', 0)
            ->when($this->crit['warehouse'] ?? null, function ($q, $v) {
                $q->where('item_ledger_entries.warehouse_id', $v);
            })
            ->when($this->crit['category'] ?? null, function ($q, $v) {
                $q->where('p.category_name', $v);
            })
            ->when($this->crit['product'] ?? null, function ($q, $v) {
                $q->where('item_ledger_entries.product_id', $v);
            });
    }
   private function sales(string $from, string $to, ?string $pay)
    {
        return $this->ledger($from, $to, $pay)
            ->where('entry_type', $this->saleEntryType)
            ->whereNotIn('document_type', array_merge($this->saleReturnDocTypes, $this->purchaseReturnDocTypes, $this->adjustment))
            // Drop any sale that was returned. A Sale Return row carries the SAME
            // document_no as the original invoice, so if one exists for this
            // document_no the sale was reversed → exclude it from revenue & COGS.
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('item_ledger_entries as sr')
                    ->whereColumn('sr.document_no', 'item_ledger_entries.document_no')
                    ->whereIn('sr.document_type', $this->saleReturnDocTypes);
            })
            ->when($this->excludedCategories, fn($q) => $q->whereNotIn('category_name', $this->excludedCategories));
    }

       private function purchases(string $from, string $to, ?string $pay)
    {
        return $this->ledger($from, $to, $pay)
            ->whereIn('document_type', array_merge(['Purchase'], $this->purchaseReturnDocTypes));
    }

    private function expenses(string $from, string $to, ?string $pay)
    {
        return Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->where('status', 1)
            ->when($pay, fn($q) => $q->where('payment_method', $pay))
            ->when($this->crit['created_by'] ?? null, fn($q, $v) => $q->where('created_by', $v))
            ->when($this->crit['product'] ?? null, fn($q, $v) => $q->where('product_id', $v));  // ← this kills it
    }
    /** Date + the four request filters, but WITHOUT the transfer / zero-value
     *  exclusions — returns are counted in units and may carry no amount. */
    private function returnsBase(string $from, string $to, ?string $pay)
    {
        return ItemLedgerEntry::query()
            ->whereBetween('posting_date', [$from, $to])
            ->when($pay, fn($q) => $q->where('payment_method', $pay))
            ->when($this->crit['warehouse']  ?? null, fn($q, $v) => $q->where('warehouse_id', $v))
            ->when($this->crit['created_by'] ?? null, fn($q, $v) => $q->where('created_by', $v))
            ->when($this->crit['product']    ?? null, fn($q, $v) => $q->where('product_id', $v))
            ->when($this->crit['category']   ?? null, fn($q, $v) => $q->where('category_name', $v));
    }

    private function filters(Request $r): array
    {
        $from = $r->input('from') ?: now()->subMonths(6)->startOfMonth()->toDateString();
        $to   = $r->input('to') ?: now()->toDateString();
        $pay  = $r->input('payment');
        if ($pay === 'ALL' || $pay === '') $pay = null;

        $clean = fn($v) => ($v === null || $v === '' || $v === 'ALL') ? null : $v;
        $this->crit = [
            'warehouse'  => $clean($r->input('warehouse')),
            'created_by' => $clean($r->input('created_by')),
            'product'    => $clean($r->input('product_id')),
            'category'   => $clean($r->input('category')),
        ];
        return [$from, $to, $pay];
    }

    /* ============================================================
     |  SUMMARY
     * ============================================================ */
    public function summary(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);

        $now  = $this->kpis($from, $to, $pay);
        $span = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prev = $this->kpis(
            Carbon::parse($from)->subDays($span)->toDateString(),
            Carbon::parse($from)->subDay()->toDateString(),
            $pay
        );
        $hadPrev = ($prev['invoices'] > 0 || $prev['expense'] != 0);
        $delta = fn($a, $b) => (!$hadPrev || $b == 0.0) ? null : round((($a - $b) / abs($b)) * 100, 1);

        return response()->json([
            'kpis'   => $now,
            'view'   => $this->disp['code'],
            'deltas' => [
                'revenue'   => $delta($now['revenue'],   $prev['revenue']),
                'cogs'      => $delta($now['cogs'],      $prev['cogs']),
                'gross'     => $delta($now['gross'],     $prev['gross']),
                'expense'   => $delta($now['expense'],   $prev['expense']),
                'net'       => $delta($now['net'],       $prev['net']),
                'netMargin' => $delta($now['netMargin'], $prev['netMargin']),
            ],
        ]);
    }

    private function kpis(string $from, string $to, ?string $pay): array
    {
        $s = $this->sales($from, $to, $pay)->selectRaw('
            SUM(' . $this->eRevenue() . ') as revenue,
            SUM(' . $this->eCogs() . ')    as cogs,
            SUM(' . $this->conv('vat_amount') . ') as vat,
            SUM(ABS(quantity)) as qty,
            COUNT(DISTINCT document_no) as invoices
        ')->first();

        $purch = (float) ($this->purchases($from, $to, $pay)
            ->selectRaw('-SUM(' . $this->conv('line_amount') . ') as a')->value('a') ?? 0);
        $expense = (float) ($this->expenses($from, $to, $pay)
            ->selectRaw('SUM(' . $this->conv('amount') . ') as a')
            ->value('a') ?? 0);

        $revenue  = (float) ($s->revenue ?? 0);
        $cogs     = (float) ($s->cogs ?? 0);
        $gross    = $revenue - $cogs;
        $net      = $gross - $expense;
        $invoices = (int) ($s->invoices ?? 0);

        // vendors / customers dealt with + returns (units only), keyed by document_type.
       $customers = (int) $this->sales($from, $to, $pay)
            ->whereNotNull('customer_id')->distinct()->count('customer_id');
         $vendors = (int) $this->purchases($from, $to, $pay)
            ->whereNotNull('vendor_id')->distinct()->count('vendor_id');
        // returns by document_type only. Each return has a +/- pair that nets to 0,
        // so count just the physical movement side (not both lines, which doubles it):
        //   sale return  = units coming IN  (positive qty)
        //   purch return = units going OUT  (negative qty)
        $saleRet   = (float) ($this->returnsBase($from, $to, $pay)->whereIn('document_type', $this->saleReturnDocTypes)

            ->count() ?? 0);
        $purchRet  = (float) ($this->returnsBase($from, $to, $pay)->whereIn('document_type', $this->purchaseReturnDocTypes)
            ->count() ?? 0);

        return [
            'revenue'     => round($revenue, 2),
            'cogs'        => round($cogs, 2),
            'vat'         => round((float) ($s->vat ?? 0), 2),
            'qty'         => round((float) ($s->qty ?? 0), 2),
            'expense'     => round($expense, 2),
            'purch'       => round($purch, 2),
            'gross'       => round($gross, 2),
            'net'         => round($net, 2),
            'grossMargin' => $revenue ? round($gross / $revenue * 100, 2) : 0,
            'netMargin'   => $revenue ? round($net / $revenue * 100, 2) : 0,
            'invoices'    => $invoices,
            'aov'         => $invoices ? round($revenue / $invoices, 2) : 0,
            'vendors'           => $vendors,
            'customers'         => $customers,
            'saleReturnQty'     => round($saleRet, 2),
            'purchaseReturnQty' => round($purchRet, 2),
        ];
    }

    /* ============================================================
     |  TREND
     * ============================================================ */
    public function trend(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);

        $sale = $this->sales($from, $to, $pay)->groupBy('posting_date')
            ->selectRaw('posting_date as d, SUM(' . $this->eRevenue() . ') as rev, SUM(' . $this->eCogs() . ') as cogs')->get();
        $exp = $this->expenses($from, $to, $pay)
            ->groupBy('expense_date')
            ->selectRaw('expense_date as d, SUM(' . $this->conv('amount') . ') as amt')
            ->get();

        $day = [];
        foreach ($sale as $row) $day[Carbon::parse($row->d)->toDateString()] = ['rev' => (float) $row->rev, 'cogs' => (float) $row->cogs, 'exp' => 0.0];
        foreach ($exp as $row) {
            $k = Carbon::parse($row->d)->toDateString();
            $day[$k] = $day[$k] ?? ['rev' => 0.0, 'cogs' => 0.0, 'exp' => 0.0];
            $day[$k]['exp'] += (float) $row->amt;
        }

        $span  = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $byDay = $span <= 70;
        $buckets = [];
        if ($byDay) {
            for ($c = Carbon::parse($from), $e = Carbon::parse($to); $c <= $e; $c->addDay()) $buckets[$c->toDateString()] = ['rev' => 0, 'cogs' => 0, 'exp' => 0];
        } else {
            for ($c = Carbon::parse($from)->startOfMonth(), $e = Carbon::parse($to)->startOfMonth(); $c <= $e; $c->addMonth()) $buckets[$c->format('Y-m')] = ['rev' => 0, 'cogs' => 0, 'exp' => 0];
        }
        foreach ($day as $date => $v) {
            $key = $byDay ? $date : substr($date, 0, 7);
            if (!isset($buckets[$key])) continue;
            $buckets[$key]['rev']  += $v['rev'];
            $buckets[$key]['cogs'] += $v['cogs'];
            $buckets[$key]['exp']  += $v['exp'];
        }

        $out = [];
        foreach ($buckets as $key => $v) {
            $cost = $v['cogs'] + $v['exp'];
            if ($byDay) {
                $label = Carbon::parse($key)->format('j M');
                $bFrom = $bTo = $key;                                   // key is 'Y-m-d'
            } else {
                $label = Carbon::parse($key . '-01')->format('M y');
                $bFrom = $key . '-01';
                $bTo   = Carbon::parse($bFrom)->endOfMonth()->toDateString();
            }
            $out[] = [
                'label'   => $label,
                'from'    => $bFrom,                                    // ← NEW
                'to'      => $bTo,                                      // ← NEW
                'revenue' => round($v['rev'], 2),
                'cogs'    => round($v['cogs'], 2),
                'cost'    => round($cost, 2),
                'expense' => round($v['exp'], 2),
                'gain'    => round($v['rev'] - $cost, 2),
            ];
        }
        return response()->json(['byDay' => $byDay, 'series' => $out]);
    }

    /* ============================================================
     |  BREAKDOWN
     * ============================================================ */
    public function breakdown(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);

        $expense = $this->expenses($from, $to, $pay)
            ->groupBy('expense_name')
            ->selectRaw('expense_name as name, SUM(' . $this->conv('amount') . ') as value')
            ->get()
            ->map(fn($x) => [
                'name'  => $x->name ?: 'Expense',
                'value' => round((float) $x->value, 2),
            ])
            ->sortByDesc('value')
            ->values();

        $category = $this->sales($from, $to, $pay)->groupBy('category_name')
            ->selectRaw('category_name as category, SUM(' . $this->eRevenue() . ') as revenue, SUM(' . $this->eGain() . ') as profit, SUM(ABS(quantity)) as qty')->get()
            ->map(fn($x) => [
                'category' => $x->category ?: '(uncategorised)',
                'revenue'  => round((float) $x->revenue, 2),
                'profit'   => round((float) $x->profit, 2),
                'qty'      => round((float) $x->qty, 2),
            ])->sortByDesc('profit')->values();

        $products = $this->sales($from, $to, $pay)->groupBy('product_id', 'name', 'item_code', 'category_name')
            ->selectRaw('product_id, name, item_code, category_name as category, SUM(' . $this->eRevenue() . ') as revenue, SUM(' . $this->eGain() . ') as profit, SUM(ABS(quantity)) as qty')->get()
            ->map(function ($x) {
                $rev = (float) $x->revenue;
                $profit = (float) $x->profit;
                return [
                    'id' => $x->product_id,
                    'name' => $x->name,
                    'category' => $x->category,
                    'qty' => round((float) $x->qty, 2),
                    'profit' => round($profit, 2),
                    'margin' => $rev ? round($profit / $rev * 100, 1) : 0
                ];
            })->sortByDesc('profit')->take(7)->values();

        return response()->json(compact('expense', 'category', 'products'));
    }

    /* ============================================================
     |  TRANSACTIONS
     * ============================================================ */
    public function transactions(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);
        $tab  = (string) $r->input('tab', 'sales');
        $term = trim((string) $r->input('q', ''));
        $sort = (string) $r->input('sort', 'date');
        $dir  = $r->input('dir') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $r->input('page', 1));

        if ($tab === 'sales') {
            $q = $this->sales($from, $to, $pay);

            if ($term !== '') {
                $q->where(
                    fn($w) =>
                    $w->where('document_no', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                );
            }

            $rows = $q
                ->selectRaw("COALESCE(NULLIF(document_no, ''), NULLIF(source_no, ''), CAST(entry_no AS CHAR)) as id")
                ->selectRaw('
            MIN(posting_date) as date,
            MAX(customer_name) as who,
            MAX(customer_address) as meta,
            MAX(payment_method) as pay,
            MAX(currency_name) as cur,
            SUM(' . $this->conv('grand_total_amount') . ') as amount,
            SUM(' . $this->eGain() . ') as profit,
            MAX(CASE WHEN LOWER(document_type) LIKE \'%return%\' OR LOWER(document_type) LIKE \'%credit%\' THEN 1 ELSE 0 END) as returned
        ')
                ->groupByRaw("COALESCE(NULLIF(document_no, ''), NULLIF(source_no, ''), CAST(entry_no AS CHAR))")
                ->get()
                ->map(fn($x) => [
                    'kind' => 'sale',
                    'id' => $x->id,
                    'date' => Carbon::parse($x->date)->toDateString(),
                    'who' => $x->who,
                    'meta' => $x->meta,
                    'pay' => $x->pay,
                    'cur' => $x->cur,
                    'amount' => round((float) $x->amount, 2),
                    'profit' => round((float) $x->profit, 2),
                    'returned' => (int) $x->returned > 0,
                ]);
        } elseif ($tab === 'purchases') {
            $q = $this->purchases($from, $to, $pay);

            if ($term !== '') {
                $q->where(fn($w) => $w
                    ->whereRaw($this->docKey() . " LIKE ?", ["%{$term}%"])
                    ->orWhere('customer_name', 'like', "%{$term}%"));
            }

           $rows = $q
                ->selectRaw($this->docKey() . ' as id')
                ->selectRaw('
            MIN(posting_date) as date,
            MAX(vendor_name) as who,
            MAX(payment_method) as pay,
            MAX(currency_name) as cur,
            -SUM(' . $this->conv('line_amount') . ') as amount,
            COUNT(*) as items
        ')
                ->groupByRaw($this->docKey())
                ->get()
                ->map(fn($x) => [
                    'kind' => 'purchase',
                    'id' => $x->id,
                    'date' => Carbon::parse($x->date)->toDateString(),
                    'who' => $x->who ?: 'Vendor',
                    'meta' => $x->items . ' items',
                    'pay' => $x->pay,
                    'cur' => $x->cur,
                    'amount' => round((float) $x->amount, 2),
                    'profit' => null,
                    'returned' => false,
                ]);
        } else {
            $q = $this->expenses($from, $to, $pay);
            if ($term !== '') $q->where(fn($w) => $w->where('name', 'like', "%{$term}%")->orWhere('document_no', 'like', "%{$term}%"));
            $rows = $q->get()->map(fn($e) => [
                'kind' => 'expense',
                'id' => $e->id,
                'rid' => $e->id,
                'date' => Carbon::parse($e->expense_date)->toDateString(),
                'who' => $e->expense_name,
                'meta' => $e->expense_code ?: $e->note,
                'status' => $e->status,
                'pay' => $e->payment_method,
                'cur' => $e->currency_name,
                'amount' => round($this->dispv($e->amount, $e->factor), 2),
                'profit' => null,
                'returned' => false,
            ]);
        }

        $rows = $rows->sortBy(
            fn($x) => $sort === 'amount' ? $x['amount'] : ($sort === 'profit' ? ($x['profit'] ?? -INF) : $x['date']),
            SORT_REGULAR,
            $dir === 'desc'
        )->values();

        $total = $rows->count();
        $paged = $rows->slice(($page - 1) * $this->perPage, $this->perPage)->values();

        return response()->json([
            'rows'  => $paged,
            'total' => $total,
            'page'  => $page,
            'pages' => max(1, (int) ceil($total / $this->perPage)),
        ]);
    }
    /** Purchases in the period, grouped by document — paginated. */
    private function detailPurchasesList(string $from, string $to, ?string $pay, int $page = 1, int $per = 25): array
    {
         $grouped = $this->purchases($from, $to, $pay)
            ->groupByRaw($this->docKey())
            ->selectRaw($this->docKey() . ' as doc,
            MIN(posting_date) as date, MAX(vendor_name) as vendor,
            COUNT(*) as items, -SUM(' . $this->conv('line_amount') . ') as amount')
            ->orderByDesc('date')->get();

        $total = $grouped->count();
        $grand = (float) $grouped->sum(fn($x) => (float) $x->amount);
        $units = (float) $grouped->sum(fn($x) => (int) $x->items);

        $pages = max(1, (int) ceil($total / $per));
        $page  = max(1, min($page, $pages));
        $slice = $grouped->slice(($page - 1) * $per, $per)->values();

        $lines = $slice->map(fn($x) => [
            'cells' => [
                $this->niceDate($x->date),
                ['v' => $x->doc ?: '—'],
                ['v' => (is_numeric($x->vendor) ? 'Vendor #' . $x->vendor : ($x->vendor ?: 'Vendor'))],
                $x->items . ' items',
                $this->m((float) $x->amount),
            ],
            'drill' => ['type' => 'purchase', 'id' => $x->doc],
        ])->values();

        return [
            'accent'  => 'purchase',
            'eyebrow' => 'Inventory Purchases · cash to vendors',
            'title'   => 'Purchases',
            'tags'    => [$total . ' documents', 'view ' . $this->disp['code']],
            'kpis'    => [
                ['Total Spent', round($grand, 2), 'cogs'],
                ['Documents',   $total,           'purchase', '#'],
                ['Line Items',  round($units, 2), 'revenue', '#'],
            ],
            'columns' => ['Date', 'Document', 'Vendor', 'Items', 'Amount'],
            'lines'   => $lines,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'drillType' => 'purchases',   // ← tells the modal how to re-fetch other pages
        ];
    }
    /* ============================================================
     |  DETAIL
     * ============================================================ */
    public function detail(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);
        $type = (string) $r->input('type');
        $id   = $r->input('id');
        $page = max(1, (int) $r->input('page', 1));

        return response()->json(match ($type) {
            'sale'         => $this->detailSale($id),
            'purchase'     => $this->detailPurchase($id),
            'expense'      => $this->detailExpense($id),
            'product'      => $this->detailProduct($id, $from, $to, $pay),
            'category'     => $this->detailCategory($id, $from, $to, $pay),
            'expenseName'  => $this->detailExpenseName($id, $from, $to, $pay),
            'adjGain'      => $this->detailAdjustment('positive', $from, $to, $pay),
            'adjLoss'      => $this->detailAdjustment('negative', $from, $to, $pay),
            'adjNet'       => $this->detailAdjustment('all', $from, $to, $pay),
            'inventory'    => $this->detailInventory($from, $to, $pay),
            'pnl'          => $this->detailPnl($from, $to, $pay, $r->boolean('cum'), $page),
            'serviceLines' => $this->detailServiceLines($from, $to, $pay, $page),
            'expensesList' => $this->detailExpensesList($from, $to, $pay, $page),
            'stockItem'    => $this->detailStockItem($id),
            'purchases'    => $this->detailPurchasesList($from, $to, $pay, $page),
            'marginInfo'   => $this->detailMarginInfo((string) $id, $from, $to, $pay),
            'customers'    => $this->detailCustomers($from, $to, $pay),
            'vendors'      => $this->detailVendors($from, $to, $pay),
            'saleReturns'  => $this->detailReturns('sale', $from, $to, $pay),
            'purchReturns' => $this->detailReturns('purchase', $from, $to, $pay),
            default        => ['error' => 'unknown type'],
        });
    }
    /** Distinct customers in the period — orders, units, revenue. */
    private function detailCustomers(string $from, string $to, ?string $pay): array
    {
        $rows = $this->sales($from, $to, $pay)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(customer_name) as name,
                COUNT(DISTINCT ' . $this->docKey() . ') as docs,
                SUM(ABS(quantity)) as qty,
                SUM(' . $this->eRevenue() . ') as revenue')
            ->get();

        $lines = $rows->sortByDesc('revenue')->map(fn($x) => ['cells' => [
            ['v' => $x->name ?: ('Customer #' . $x->customer_id)],
            $this->n0((float) $x->docs),
            $this->n0((float) $x->qty),
            $this->m((float) $x->revenue),
        ]])->values();

        return [
            'accent'  => 'revenue',
            'eyebrow' => 'Customers · buyers in period',
            'title'   => 'Customers',
            'tags'    => [$rows->count() . ' customers', 'view ' . $this->disp['code']],
            'kpis'    => [
                ['Customers', $rows->count(), 'revenue', '#'],
                ['Revenue', round((float) $rows->sum('revenue'), 2), 'revenue'],
            ],
            'columns' => ['Customer', 'Invoices', 'Units', 'Revenue'],
            'lines'   => $lines,
        ];
    }

    /** Distinct vendors in the period — docs, units, spend. */
    private function detailVendors(string $from, string $to, ?string $pay): array
    {
     $rows = $this->purchases($from, $to, $pay)
            ->whereNotNull('vendor_id')
            ->groupBy('vendor_id')
            ->selectRaw('vendor_id, MAX(vendor_name) as name,
                COUNT(DISTINCT ' . $this->docKey() . ') as docs,
                SUM(ABS(quantity)) as qty,
                -SUM(' . $this->conv('line_amount') . ') as spent')
            ->get();

        $lines = $rows->sortByDesc('spent')->map(fn($x) => ['cells' => [
            ['v' => (is_numeric($x->name) || !$x->name) ? ('Vendor #' . $x->vendor_id) : $x->name],
            $this->n0((float) $x->docs),
            $this->n0((float) $x->qty),
            $this->m((float) $x->spent),
        ]])->values();

        return [
            'accent'  => 'purchase',
            'eyebrow' => 'Vendors · suppliers in period',
            'title'   => 'Vendors',
            'tags'    => [$rows->count() . ' vendors', 'view ' . $this->disp['code']],
            'kpis'    => [
                ['Vendors', $rows->count(), 'purchase', '#'],
                ['Purchased', round((float) $rows->sum('spent'), 2), 'cogs'],
            ],
            'columns' => ['Vendor', 'Docs', 'Units', 'Amount'],
            'lines'   => $lines,
        ];
    }

    /** Return documents — kind = 'sale' or 'purchase'. */
    private function detailReturns(string $kind, string $from, string $to, ?string $pay): array
    {
        $isSale = $kind === 'sale';
        $types  = $isSale ? $this->saleReturnDocTypes : $this->purchaseReturnDocTypes;

        $rows = $this->returnsBase($from, $to, $pay)
            ->whereIn('document_type', $types)
            ->groupByRaw($this->docKey())
            ->selectRaw($this->docKey() . ' as doc,
                MIN(posting_date) as date,
                MAX(customer_name) as customer,
                MAX(vendor_name) as vendor,
                MAX(payment_method) as pay,
                SUM(ABS(quantity)) as qty')
            ->orderByDesc('date')->get();

        $lines = $rows->map(function ($x) use ($isSale) {
            $who = $isSale
                ? ($x->customer ?: 'Customer')
                : (is_numeric($x->vendor) || !$x->vendor ? 'Vendor #' . $x->vendor : $x->vendor);
            return ['cells' => [
                $this->niceDate($x->date),
                ['v' => $x->doc ?: '—'],
                ['v' => $who],
                $x->pay ?: '—',
                $this->n0((float) $x->qty),
            ]];
        })->values();

        $totQty = (float) $rows->sum(fn($x) => (float) $x->qty);

        return [
            'accent'  => $isSale ? 'revenue' : 'purchase',
            'eyebrow' => $isSale ? 'Sale Returns · goods back from customers' : 'Purchase Returns · goods back to vendors',
            'title'   => $isSale ? 'Sale Returns' : 'Purchase Returns',
            'tags'    => [$rows->count() . ' documents', 'view ' . $this->disp['code']],
            'kpis'    => [
                ['Documents', $rows->count(), 'purchase', '#'],
                ['Units', round($totQty, 2), 'revenue', '#'],
            ],
            'columns' => ['Date', 'Document', $isSale ? 'Customer' : 'Vendor', 'Payment', 'Units'],
            'lines'   => $lines,
        ];
    }
    /** P&L view — KPI header + recent sale lines. Serves Net Revenue / Cost / Net Gain
     *  cards (period scope) AND the trend chart points (single day / range / cumulative). */
 private function detailPnl(string $from, string $to, ?string $pay, bool $cum = false, int $page = 1): array
    {
        $s = $this->sales($from, $to, $pay)->selectRaw('
        SUM(' . $this->eRevenue() . ') as revenue,
        SUM(' . $this->eCogs() . ')    as cogs,
        COUNT(DISTINCT document_no)    as invoices
    ')->first();
        $revenue = (float) ($s->revenue ?? 0);
        $cogs    = (float) ($s->cogs ?? 0);
        $gross   = $revenue - $cogs;
        $expense = (float) ($this->expenses($from, $to, $pay)
            ->selectRaw('SUM(' . $this->conv('amount') . ') as a')->value('a') ?? 0);
        $prodNet = $gross - $expense;

        $svc      = $this->serviceTotals($from, $to, $pay);
        $totalNet = $prodNet + $svc['gain'];
        $hasSvc   = (abs($svc['gain']) > 0.005 || abs($svc['revenue']) > 0.005);

        $per   = 25;
        $q     = $this->sales($from, $to, $pay)
            ->selectRaw('document_no, posting_date, name, item_code, ABS(quantity) as qty,
            ' . $this->eRevenue() . ' as net, ' . $this->eGain() . ' as gain')
            ->orderByDesc('posting_date');
        $total = (clone $q)->count();
        $pages = max(1, (int) ceil($total / $per));
        $page  = max(1, min($page, $pages));
        $rows  = $q->forPage($page, $per)->get();

        $lines = $rows->map(fn($l) => [
            'cells' => [
                $this->niceDate($l->posting_date),
                ['v' => $l->document_no ?: '—'],
                ['v' => $l->name, 'sub' => $l->item_code],
                $this->n0(abs((float) $l->qty)),
                $this->m((float) $l->net),
                ['v' => $this->m((float) $l->gain), 'cls' => (float) $l->gain >= 0 ? 'pos' : 'neg'],
            ],
            'drill' => ['type' => 'sale', 'id' => $l->document_no],
        ])->values();

        $title = $cum ? 'P&L · up to ' . $this->niceDate($to)
            : ($from === $to ? 'P&L · ' . $this->niceDate($from) : 'P&L · ' . $this->niceDate($from) . ' – ' . $this->niceDate($to));

        // Structured P&L rows for the dedicated summary card (reads top to bottom).
        $pnl = [
            ['l' => 'Net Revenue',        'v' => $revenue],
            ['l' => 'Cost of Goods',      'v' => -$cogs],
            ['l' => 'Gross Profit',       'v' => $gross,   'strong' => true, 'rule' => true],
            ['l' => 'Operating Expenses', 'v' => -$expense],
        ];
        if ($hasSvc) {
            $pnl[] = ['l' => 'Product Net',  'v' => $prodNet,      'strong' => true, 'rule' => true];
            $pnl[] = ['l' => 'Service Gain', 'v' => $svc['gain']];
        }
        $pnl[] = ['l' => 'Net Gain', 'v' => $totalNet, 'strong' => true, 'rule' => true];

        return [
            'accent'     => 'profit',
            'eyebrow'    => $cum ? 'Profit & Loss · cumulative · incl. service' : 'Profit & Loss · incl. service',
            'title'      => $title,
            'tags'       => [(int) ($s->invoices ?? 0) . ' invoices', 'view ' . $this->disp['code']],
            'pnl'        => $pnl,
            'linesLabel' => 'Sale lines · ' . $total . ' rows',
            'columns'    => ['Date', 'Document', 'Product', 'Qty', 'Net', 'Gain'],
            'lines'      => $lines,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'drillType'  => $cum ? null : 'pnl',
        ];
    }
    /** Service-type invoice lines (delivery & fees). */
    private function detailServiceLines(string $from, string $to, ?string $pay, int $page = 1): array
    {
        $s    = $this->svc;
        $rev  = $this->serviceConv("l.{$s['amt']}");
        $cost = $this->serviceConv("(l.{$s['cost']} * l.{$s['qty']})");
        $gain = $this->serviceConv("(l.{$s['amt']} - l.{$s['cost']} * l.{$s['qty']})");
        $base = $this->serviceBase($from, $to, $pay);

        $agg = (clone $base)->selectRaw("SUM($rev) as rev, SUM($cost) as cost, SUM($gain) as gain, COUNT(*) as n, COUNT(DISTINCT h.id) as inv")->first();

        $per   = 25;
        $total = (int) ($agg->n ?? 0);
        $pages = max(1, (int) ceil($total / $per));
        $page  = max(1, min($page, $pages));
        $rows  = (clone $base)->orderByDesc("h.{$s['date']}")
            ->selectRaw("l.{$s['docno']} as doc, h.{$s['date']} as date, p.name as service,
            l.{$s['qty']} as qty, ($rev) as rev, ($gain) as gain")
            ->forPage($page, $per)->get();

        $lines = $rows->map(fn($x) => ['cells' => [
            $this->niceDate($x->date),
            ['v' => $x->doc ?: '—'],
            ['v' => $x->service ?: 'Service'],
            $this->n0((float) $x->qty),
            $this->m((float) $x->rev),
            ['v' => $this->m((float) $x->gain), 'cls' => (float) $x->gain >= 0 ? 'pos' : 'neg'],
        ]])->values();

        return [
            'accent'  => 'gold',
            'eyebrow' => 'Services · delivery & fees',
            'title'   => 'Service Lines',
            'tags'    => [(int) ($agg->inv ?? 0) . ' invoices', 'view ' . $this->disp['code']],
            'kpis'    => [
                ['Service Revenue', round((float) ($agg->rev ?? 0), 2),  'revenue'],
                ['Service Cost',    round((float) ($agg->cost ?? 0), 2), 'cogs'],
                ['Service Gain',    round((float) ($agg->gain ?? 0), 2), ((float) ($agg->gain ?? 0)) >= 0 ? 'profit' : 'cogs'],
                ['Lines',           $total, 'purchase', '#'],
            ],
            'columns'   => ['Date', 'Document', 'Service', 'Qty', 'Revenue', 'Gain'],
            'lines'     => $lines,
            'page'      => $page,
            'pages' => $pages,
            'total' => $total,
            'drillType' => 'serviceLines',
        ];
    }

    /** Every operating expense in the period. */
    private function detailExpensesList(string $from, string $to, ?string $pay, int $page = 1): array
    {
        $per   = 25;
        $q     = $this->expenses($from, $to, $pay)->orderByDesc('expense_date');
        $all   = $q->get();                                  // need full set for the total
        $total = $all->count();
        $grand = (float) $all->sum(fn($e) => $this->dispv($e->amount, $e->factor));
        $pages = max(1, (int) ceil($total / $per));
        $page  = max(1, min($page, $pages));
        $slice = $all->slice(($page - 1) * $per, $per)->values();

        $lines = $slice->map(fn($e) => [
            'cells' => [
                $this->niceDate($e->expense_date),
                ['v' => $e->expense_name],
                ['v' => $e->note ?: ''],
                $e->expense_code ?: '',

                $this->m($this->dispv($e->amount, $e->factor)),
            ],
            'drill' => ['type' => 'expense', 'id' => $e->id],
        ])->values();

        return [
            'accent'  => 'expense',
            'eyebrow' => 'Operating Expenses',
            'title'   => 'All Expenses',
            'tags'    => [$total . ' entries', 'view ' . $this->disp['code']],
            'kpis'    => [
                ['Total',   round($grand, 2), 'expense'],
                ['Entries', $total,           'purchase', '#'],
                ['Average', $total ? round($grand / $total, 2) : 0, 'gold'],
            ],
            'columns'   => ['Date', 'Expense', 'Note', 'Code', 'Amount'],
            'lines'     => $lines,
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
            'drillType' => 'expensesList',
        ];
    }

    /** Service revenue (converted) — helper for the overall-margin explainer. */
    private function serviceRevenue(string $from, string $to, ?string $pay): float
    {
        $s = $this->svc;
        return (float) ($this->serviceBase($from, $to, $pay)
            ->selectRaw('SUM(' . $this->serviceConv("l.{$s['amt']}") . ') as r')->value('r') ?? 0);
    }

    /** Service stream totals (converted) for a date range — revenue, cost, gain. */
    private function serviceTotals(string $from, string $to, ?string $pay): array
    {
        $s    = $this->svc;
        $rev  = $this->serviceConv("l.{$s['amt']}");
        $cost = $this->serviceConv("(l.{$s['cost']} * l.{$s['qty']})");
        $gain = $this->serviceConv("(l.{$s['amt']} - l.{$s['cost']} * l.{$s['qty']})");
        $row  = $this->serviceBase($from, $to, $pay)
            ->selectRaw("SUM($rev) as rev, SUM($cost) as cost, SUM($gain) as gain")->first();
        return [
            'revenue' => (float) ($row->rev ?? 0),
            'cost'    => (float) ($row->cost ?? 0),
            'gain'    => (float) ($row->gain ?? 0),
        ];
    }
    /** "How is this % computed" — Product Margin or Overall Margin. */
    private function detailMarginInfo(string $which, string $from, string $to, ?string $pay): array
    {
        $k = $this->kpis($from, $to, $pay);
        $revenue = (float) $k['revenue'];
        $cogs    = (float) $k['cogs'];
        $gross   = (float) $k['gross'];
        $net     = (float) $k['net'];

        if ($which === 'overall') {
            $svc      = $this->serviceTotals($from, $to, $pay);
            $expense  = (float) $k['expense'];                 // operating expenses
            $totalNet = $net + $svc['gain'];                  // product net (already − expense) + service gain
            $totRev   = $revenue + $svc['revenue'];
            $margin   = $totRev ? round($totalNet / $totRev * 100, 1) : 0;
            return [
                'accent'  => 'gold',
                'eyebrow' => 'Margin · how it is computed',
                'title'   => 'Overall Business Margin',
                'tags'    => ['view ' . $this->disp['code']],
                'kpis'    => [
                    ['Gross Profit',       round($gross, 2),       'profit'],
                    ['Operating Expenses', round($expense, 2),     'expense'],
                    ['Service Gain',       round($svc['gain'], 2), 'profit'],
                    ['Net Gain',           round($totalNet, 2),    'gold'],
                    ['Total Revenue',      round($totRev, 2),      'revenue'],
                    ['Overall Margin',     $margin,                'gold', '%'],
                ],
                'note' => "Overall Margin = Net Gain ÷ Total Revenue\n"
                    . "= " . $this->m($totalNet) . " ÷ " . $this->m($totRev) . " = " . $margin . "%\n"
                    . "Net Gain = Gross Profit (" . $this->m($gross) . ") − Operating Expenses (" . $this->m($expense) . ") + Service Gain (" . $this->m($svc['gain']) . ")\n"
                    . "Total Revenue = Product (" . $this->m($revenue) . ") + Service (" . $this->m($svc['revenue']) . ")",
            ];
        }
        $margin = $revenue ? round($gross / $revenue * 100, 1) : 0;
        return [
            'accent'  => 'profit',
            'eyebrow' => 'Margin · how it is computed',
            'title'   => 'Product Margin',
            'tags'    => ['view ' . $this->disp['code']],
            'kpis'    => [
                ['Net Revenue',   round($revenue, 2), 'revenue'],
                ['Cost of Goods', round($cogs, 2),    'cogs'],
                ['Gross Profit',  round($gross, 2),   'profit'],
                ['Product Margin', $margin,           'gold', '%'],
            ],
            'note' => 'Product Margin = Gross Profit ÷ Net Revenue = ' . $this->m($gross) . ' ÷ ' . $this->m($revenue)
                . ' = ' . $margin . '%. Profit on Product only — before operating expenses and the service stream.',
        ];
    }

    /** Lots on hand for one product — lot, location, cost, value. */
    private function detailStockItem($id): array
    {
        $rows = $this->stockScope()
            ->where('item_ledger_entries.product_id', $id)
            ->orderBy('item_ledger_entries.lot')
            ->selectRaw('
            p.name as pname, item_ledger_entries.item_code as code,
            item_ledger_entries.lot as lot, item_ledger_entries.warehouse_name as wh,
            item_ledger_entries.expire_date as expire,
            item_ledger_entries.remaining_quantity as qty, item_ledger_entries.unit as unit,
            item_ledger_entries.unit_cost as cost, item_ledger_entries.factor as factor
        ')->get();

        $name = $rows->first()->pname ?? ('Product #' . $id);
        $lines = $rows->map(function ($x) {
            $qty  = (float) $x->qty;
            $cost = $this->dispv((float) $x->cost, $x->factor);
            $val  = $this->dispv((float) $x->cost * $qty, $x->factor);
            return ['cells' => [
                ['v' => $x->lot ?: '—'],
                $x->wh ?: '—',
                $x->expire ? $this->niceDate($x->expire) : '—',
                $this->n0($qty),
                $this->m($cost),
                $this->m($val),
            ]];
        })->values();

        $totQty = (float) $rows->sum(fn($x) => (float) $x->qty);
        $totVal = (float) $rows->sum(fn($x) => $this->dispv((float) $x->cost * (float) $x->qty, $x->factor));

        return [
            'accent'  => 'gold',
            'eyebrow' => 'Stock on hand · valued at cost',
            'title'   => $name,
            'tags'    => array_values(array_filter([$rows->first()->code ?? null, $rows->count() . ' lots', 'view ' . $this->disp['code']])),
            'kpis'    => [
                ['On Hand',     round($totQty, 2), 'revenue', '#'],
                ['Stock Value', round($totVal, 2), 'gold'],
                ['Lots',        $rows->count(),    'purchase', '#'],
            ],
            'columns' => ['Lot', 'Warehouse', 'Expiry', 'Qty', 'Unit Cost', 'Value'],
            'lines'   => $lines,
        ];
    }
    private function detailSale($documentNo): array
    {
        $rows = ItemLedgerEntry::where('entry_type', $this->saleEntryType)->whereRaw($this->docKey() . ' = ?', [$documentNo])->get();
        $h = $rows->sortBy('posting_date')->first() ?: new ItemLedgerEntry();

        $net = 0;
        $cogs = 0;
        $lines = [];
        foreach ($rows as $l) {
            $q    = abs((float) $l->quantity);
            $sale = $this->dispv(((float) ($l->sell_price ?: $l->unit_price)) * $q, $l->factor);
            $cD   = $this->dispv((float) $l->unit_cost * $q, $l->factor);
            $net += $sale;
            $cogs += $cD;
            $lines[] = ['cells' => [
                ['v' => $l->name, 'sub' => trim(($l->item_code ?: '') . ' · lot ' . ($l->lot ?: '—'))],
                $this->n0($q),
                $this->m($this->dispv($l->sell_price ?: $l->unit_price, $l->factor)),
                $this->m($this->dispv($l->unit_cost, $l->factor)),
                ($l->discount_percent + 0) . '%',
                $this->m($sale),
                ['v' => $this->m($sale - $cD), 'cls' => ($sale - $cD) >= 0 ? 'pos' : 'neg'],
            ]];
        }
        $vat   = (float) $rows->sum(fn($x) => $this->dispv($x->vat_amount, $x->factor));
        $grand = (float) $rows->sum(fn($x) => $this->dispv($x->grand_total_amount, $x->factor));

        return [
            'accent' => 'revenue',
            'eyebrow' => 'Sales · ' . ($h->document_type ?: 'Invoice'),
            'title' => $documentNo,
            'tags' => array_values(array_filter([$h->customer_name, $h->warehouse_name, $h->currency_name, 'view ' . $this->disp['code']])),
            'meta' => [['Customer', $h->customer_name ?: '—'], ['Date', $this->niceDate($h->posting_date)], ['Payment', $h->payment_method ?: '—'], ['By', $h->created_by ?: '—']],
            'kpis' => [['Net Revenue', $net, 'revenue'], ['Cost of lots', $cogs, 'cogs'], ['Gain', $net - $cogs, 'profit'], ['Margin', $net ? round(($net - $cogs) / $net * 100, 1) : 0, 'gold', '%']],
            'columns' => ['Item / Lot', 'Qty', 'Sell', 'Cost', 'Disc', 'Net', 'Gain'],
            'lines' => $lines,
            'totals' => [['Net (excl. VAT)', $net], ['VAT', $vat], ['Grand Total', $grand, true]],
        ];
    }

   private function detailPurchase($documentNo): array
    {
        // pull BOTH the receipt rows (Purchase, entry_type positive, line_amount negative)
        // AND the return rows (Purchase Return, entry_type negative, line_amount positive)
        // for this document, so the GRN nets down when part of it was returned.
        $rows = ItemLedgerEntry::whereRaw($this->docKey() . ' = ?', [$documentNo])
            ->whereIn('document_type', array_merge(['Purchase'], $this->purchaseReturnDocTypes))
            ->get();

        $h = $rows->sortBy('posting_date')->first() ?: new ItemLedgerEntry();
        $amount = 0;
        $units  = 0;
        $lines  = [];

        foreach ($rows as $l) {
            $isReturn = in_array($l->document_type, $this->purchaseReturnDocTypes, true);

            // receipt line_amount is negative → flip to positive cost.
            // return  line_amount is positive → keep, but show as a negative (subtract).
            $lineVal = $isReturn
                ? -$this->dispv(abs((float) $l->line_amount), $l->factor)   // return reduces cost
                :  $this->dispv(abs((float) $l->line_amount), $l->factor);  // receipt adds cost

            // qty: receipt is +, return is − (stock direction)
            $qtyVal = $isReturn
                ? -abs((float) $l->quantity)
                :  abs((float) $l->quantity);

            $amount += $lineVal;
            $units  += $qtyVal;

            $lines[] = ['cells' => [
                ['v' => $l->name . ($isReturn ? '  ↩ return' : ''), 'sub' => $l->item_code],
                $l->lot ?: '—',
                ['v' => $this->n0($qtyVal), 'cls' => $isReturn ? 'neg' : ''],
                $this->m($this->dispv($l->unit_cost, $l->factor)),
                ['v' => $this->m($lineVal), 'cls' => $lineVal >= 0 ? '' : 'neg'],
            ]];
        }

        return [
            'accent'  => 'purchase',
            'eyebrow' => 'Purchase · ' . ($h->document_type ?: 'Receipt') . ($rows->whereIn('document_type', $this->purchaseReturnDocTypes)->count() ? ' · net of returns' : ''),
            'title'   => $documentNo,
            'tags'    => array_values(array_filter([$h->vendor_name, $h->warehouse_name, $h->currency_name, 'view ' . $this->disp['code']])),
            'meta'    => [['Vendor', $h->vendor_name ?: '—'], ['Date', $this->niceDate($h->posting_date)], ['Payment', $h->payment_method ?: '—'], ['By', $h->created_by ?: '—']],
            'kpis'    => [['Net Cost', $amount, 'cogs'], ['Lines', count($lines), 'purchase', '#'], ['Net Units', $units, 'revenue', '#']],
            'columns' => ['Item', 'Lot', 'Qty', 'Unit Cost', 'Line Amount'],
            'lines'   => $lines,
            'totals'  => [['Net Purchase', $amount, true]],
        ];
    }

    private function detailExpense($rid): array
    {
        $e = Expense::findOrFail($rid);

        return [
            'accent' => 'expense',
            'eyebrow' => 'Expense',
            'title' => $e->expense_name,
            'tags' => array_values(array_filter([$e->expense_code, $e->currency_name, 'view ' . $this->disp['code']])),
            'meta' => [
                ['Date', $this->niceDate($e->expense_date)],
                ['Qty', $e->qty],
                ['By', $e->created_by ?: '—'],
                ['Note', $e->note ?: '—'],
            ],
            'kpis' => [
                ['Amount', $this->dispv($e->amount, $e->factor), 'expense']
            ],
            'note' => $e->note ?: 'Operating expense — reduces Net Gain directly.',
        ];
    }
    private function detailProduct($id, $from, $to, $pay): array
    {
        $rows = $this->sales($from, $to, $pay)->where('product_id', $id)
            ->selectRaw('document_no, posting_date, name, item_code, category_name, quantity,
                         ' . $this->eRevenue() . ' as net, ' . $this->eGain() . ' as profit')->get();
        $first = $rows->first();
        $rev = (float) $rows->sum('net');
        $profit = (float) $rows->sum('profit');
        $qty = (float) $rows->sum(fn($x) => abs((float) $x->quantity));
        $lines = $rows->sortByDesc('posting_date')->take(40)->map(fn($l) => [
            'cells' => [
                ['v' => $l->document_no],
                $this->niceDate($l->posting_date),
                $this->n0(abs((float) $l->quantity)),
                $this->m((float) $l->net),
                ['v' => $this->m((float) $l->profit), 'cls' => (float) $l->profit >= 0 ? 'pos' : 'neg']
            ],
            'drill' => ['type' => 'sale', 'id' => $l->document_no],
        ])->values();
        return [
            'accent' => 'profit',
            'eyebrow' => 'Product · ' . ($first->category_name ?? ''),
            'title' => $first->name ?? ('Product #' . $id),
            'tags' => array_values(array_filter([$first->item_code ?? null, 'view ' . $this->disp['code']])),
            'kpis' => [['Revenue', $rev, 'revenue'], ['Gain', $profit, 'profit'], ['Margin', $rev ? round($profit / $rev * 100, 1) : 0, 'gold', '%'], ['Units', $qty, 'cogs', '#']],
            'columns' => ['Document', 'Date', 'Qty', 'Net', 'Gain'],
            'lines' => $lines,
        ];
    }

    private function detailCategory($name, $from, $to, $pay): array
    {
        $rows = $this->sales($from, $to, $pay)->where('category_name', $name)->groupBy('product_id', 'name', 'item_code')
            ->selectRaw('product_id, name, item_code, SUM(' . $this->eRevenue() . ') as revenue, SUM(' . $this->eGain() . ') as profit, SUM(ABS(quantity)) as qty')->get();
        $lines = $rows->sortByDesc('profit')->map(fn($x) => [
            'cells' => [
                ['v' => $x->name, 'sub' => $x->item_code],
                $this->n0($x->qty),
                $this->m((float) $x->revenue),
                ['v' => $this->m((float) $x->profit), 'cls' => (float) $x->profit >= 0 ? 'pos' : 'neg']
            ],
            'drill' => ['type' => 'product', 'id' => $x->product_id],
        ])->values();
        return [
            'accent' => 'profit',
            'eyebrow' => 'Category',
            'title' => $name ?: '(uncategorised)',
            'tags' => [$rows->count() . ' products', 'view ' . $this->disp['code']],
            'kpis' => [['Revenue', round((float) $rows->sum('revenue'), 2), 'revenue'], ['Gain', round((float) $rows->sum('profit'), 2), 'profit'], ['Units', round((float) $rows->sum('qty'), 2), 'gold', '#']],
            'columns' => ['Product', 'Units', 'Revenue', 'Gain'],
            'lines' => $lines,
        ];
    }

    private function detailExpenseName($name, $from, $to, $pay): array
    {
        $rows = $this->expenses($from, $to, $pay)
            ->where('expense_name', $name)
            ->get();

        $total = (float) $rows->sum(fn($e) => $this->dispv($e->amount, $e->factor));

        $lines = $rows->sortByDesc('expense_date')->map(fn($e) => [
            'cells' => [
                $this->niceDate($e->expense_date),

                $e->expense_code ?: '—',
                ['v' => $e->note ?: '—'],
                $this->m($this->dispv($e->amount, $e->factor))
            ],
            'drill' => ['type' => 'expense', 'id' => $e->id],
        ])->values();

        return [
            'accent' => 'expense',
            'eyebrow' => 'Expense',
            'title' => $name,
            'tags' => [$rows->count() . ' entries', 'view ' . $this->disp['code']],
            'kpis' => [
                ['Total', round($total, 2), 'expense'],
                ['Entries', $rows->count(), 'purchase', '#'],
                ['Average', $rows->count() ? round($total / $rows->count(), 2) : 0, 'gold'],
            ],
            'columns' => ['Date',    'Code', 'Note', 'Amount'],
            'lines' => $lines,
        ];
    }

    /* ============================================================
     |  LINE EXPLORER  (async, paginated)   route: /sales-detail
     * ============================================================ */
    public function salesDetail(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);

        $term     = trim((string) $r->input('q', ''));
        $customer = trim((string) $r->input('customer', ''));
        $product  = trim((string) $r->input('product', ''));
        $category = (string) $r->input('category', '');
        $page     = max(1, (int) $r->input('page', 1));
        $per      = min(200, max(10, (int) $r->input('per', 50)));

        $base = $this->sales($from, $to, $pay)
            ->when($term !== '', fn($w) => $w->where(fn($x) => $x
                ->where('document_no', 'like', "%{$term}%")->orWhere('source_no', 'like', "%{$term}%")->orWhere('entry_no', 'like', "%{$term}%")))
            ->when($customer !== '', fn($w) => $w->where('customer_name', 'like', "%{$customer}%"))
            ->when($product !== '', fn($w) => $w->where(fn($x) => $x->where('name', 'like', "%{$product}%")->orWhere('item_code', 'like', "%{$product}%")))
            ->when($category !== '' && $category !== 'ALL', fn($w) => $w->where('category_name', $category));

        $select = '
            document_no as doc, posting_date as date, payment_method as payment, customer_name as customer,
            lot as lot, item_code as code, name as product, variant as variant, category_name as category,
            ABS(quantity) as qty, unit as unit, discount_percent as disc_pct, vat as vat_pct,
            ' . $this->conv('unit_cost') . ' as cost,
            ' . $this->conv('COALESCE(sell_price, unit_price)') . ' as sell,
            ' . $this->conv('ABS(line_amount)') . ' as subtotal,
            ' . $this->conv('discount_amount') . ' as disc_amt,
            ' . $this->conv('vat_amount') . ' as vat_amt,
            ' . $this->conv('net_amount') . ' as net,
            ' . $this->conv('grand_total_amount') . ' as grand,
            ' . $this->eGain() . ' as profit';

        if ($r->input('export') === 'csv') {
            $code = $this->disp['code'];
            return response()->streamDownload(function () use ($base, $select, $code) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($out, [
                    'Doc No',
                    'Date',
                    'Payment',
                    'Customer',
                    'Lot',
                    'Item Code',
                    'Product',
                    'Variant',
                    'Category',
                    'Qty',
                    'Unit',
                    "Cost ($code)",
                    "Sell ($code)",
                    "Subtotal ($code)",
                    'Disc %',
                    "Disc Amt ($code)",
                    'VAT %',
                    "VAT ($code)",
                    "Net ($code)",
                    "Grand ($code)",
                    "Gain ($code)"
                ]);
                (clone $base)->orderByDesc('posting_date')->selectRaw($select)->chunk(1000, function ($rows) use ($out) {
                    foreach ($rows as $x) {
                        fputcsv($out, [
                            $x->doc,
                            Carbon::parse($x->date)->toDateString(),
                            $x->payment,
                            $x->customer,
                            $x->lot,
                            $x->code,
                            $x->product,
                            $x->variant,
                            $x->category,
                            $this->n0($x->qty),
                            $x->unit,
                            round((float) $x->cost, 2),
                            round((float) $x->sell, 2),
                            round((float) $x->subtotal, 2),
                            (float) $x->disc_pct,
                            round((float) $x->disc_amt, 2),
                            (float) $x->vat_pct,
                            round((float) $x->vat_amt, 2),
                            round((float) $x->net, 2),
                            round((float) $x->grand, 2),
                            round((float) $x->profit, 2)
                        ]);
                    }
                });
                fclose($out);
            }, "sales-lines_{$code}_{$from}_{$to}.csv", ['Content-Type' => 'text/csv']);
        }

        $total = (clone $base)->count();
        $rows = (clone $base)->orderByDesc('posting_date')->orderByDesc('document_no')->forPage($page, $per)->selectRaw($select)->get()
            ->map(fn($x) => [
                'doc' => $x->doc,
                'date' => Carbon::parse($x->date)->toDateString(),
                'payment' => $x->payment,
                'ctype' => $x->customer,
                'customer' => $x->customer,
                'lot' => $x->lot,
                'code' => $x->code,
                'product' => $x->product,
                'variant' => $x->variant,
                'category' => $x->category,
                'qty' => (float) $x->qty,
                'unit' => $x->unit,
                'disc_pct' => (float) $x->disc_pct,
                'vat_pct' => (float) $x->vat_pct,
                'cost' => round((float) $x->cost, 2),
                'sell' => round((float) $x->sell, 2),
                'subtotal' => round((float) $x->subtotal, 2),
                'disc_amt' => round((float) $x->disc_amt, 2),
                'vat_amt' => round((float) $x->vat_amt, 2),
                'net' => round((float) $x->net, 2),
                'grand' => round((float) $x->grand, 2),
                'profit' => round((float) $x->profit, 2),
            ]);

        $payload = ['rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'pages' => max(1, (int) ceil($total / $per)), 'view' => $this->disp['code']];
        if ($page === 1) {
            $payload['categories'] = ItemLedgerEntry::query()->where('entry_type', $this->saleEntryType)->select('category_name')->whereNotNull('category_name')->distinct()->orderBy('category_name')->pluck('category_name');
            $payload['payments'] = ItemLedgerEntry::query()->select('payment_method')->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method');
        }
        return response()->json($payload);
    }

    /* ============================================================
     |  EXPORT
     * ============================================================ */
    public function export(Request $r): StreamedResponse
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);
        $kind = (string) $r->input('kind', 'summary');
        $code = $this->disp['code'];

        if ($kind === 'summary') {
            $k = $this->kpis($from, $to, $pay);
            $rows = [
                ['Gain & Cost Summary'],
                ['Period', $this->niceDate($from) . ' — ' . $this->niceDate($to)],
                ['Currency view', $code],
                ['Payment', $pay ?: 'All'],
                [],
                ['Metric', "Value ($code)"],
                ['Net Revenue', $k['revenue']],
                ['Cost of Goods (lots)', $k['cogs']],
                ['Gross / Gain', $k['gross']],
                ['Operating Expenses', $k['expense']],
                ['Net Gain', $k['net']],
                ['Gross Margin %', $k['grossMargin']],
                ['Net Margin %', $k['netMargin']],
                ['Stock Purchases', $k['purch']],
                ['VAT', $k['vat']],
                ['Sales (documents)', $k['invoices']],
                ['Units Sold', $k['qty']],
                ['Avg Order Value', $k['aov']],
            ];
            $critRows = [];
            foreach ($this->critMeta() as $label => $val) $critRows[] = [$label, $val];
            array_splice($rows, 4, 0, $critRows);   // inserts under "Payment", before the blank row
            $name = "gain-cost-summary_{$code}_{$from}_{$to}.csv";
        } else {
            $req = $r->duplicate();
            $data = $this->transactions($req)->getData(true);
            $all = collect();
            for ($p = 1; $p <= $data['pages']; $p++) {
                $req->merge(['page' => $p]);
                $all = $all->merge($this->transactions($req)->getData(true)['rows']);
            }
            $tab = (string) $r->input('tab', 'sales');
            if ($tab === 'sales') {
                $rows = [['Document', 'Date', 'Customer', 'Address', 'Payment', 'Currency', "Grand ($code)", "Gain ($code)", 'Credit/Return']];
                foreach ($all as $x) $rows[] = [$x['id'], $x['date'], $x['who'], $x['meta'], $x['pay'], $x['cur'], $x['amount'], $x['profit'], $x['returned'] ? 'Yes' : 'No'];
            } elseif ($tab === 'purchases') {
                $rows = [['Document', 'Date', 'Vendor', 'Items', 'Payment', 'Currency', "Amount ($code)"]];
                foreach ($all as $x) $rows[] = [$x['id'], $x['date'], $x['who'], $x['meta'], $x['pay'], $x['cur'], $x['amount']];
            } else {
                $rows = [['Entry', 'Date', 'Expense', 'Note', 'Document', 'Payment', 'Currency', "Amount ($code)"]];
                foreach ($all as $x) $rows[] = [$x['id'], $x['date'], $x['who'], $x['meta'], $x['pay'], $x['cur'], $x['amount']];
            }
            $name = "{$tab}_{$code}_{$from}_{$to}.csv";
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /* ============================================================
     |  STOCK ON HAND  ·  current inventory from purchase lots
     * ============================================================ */
    public function stock(Request $r)
    {
        $this->setDisplay($r);
        $this->filters($r); // sets crit; stock uses item/warehouse/category only (no date/payment/user)
        $valExpr = $this->conv('item_ledger_entries.remaining_quantity * item_ledger_entries.unit_cost');

        $tot = $this->stockScope()->selectRaw("
            SUM(remaining_quantity) as qty, SUM($valExpr) as value,
            COUNT(DISTINCT product_id) as items, COUNT(*) as lots
        ")->first();

        $items = $this->stockScope()
            ->groupBy('item_ledger_entries.product_id')
            ->selectRaw("
        item_ledger_entries.product_id,
        MAX(p.name) as name,
        MAX(p.code) as code,
        SUM(item_ledger_entries.remaining_quantity) as qty,
        SUM($valExpr) as value
    ")
            ->orderByRaw("SUM($valExpr) DESC")
            ->get()
            ->map(fn($x) => [
                'id'    => $x->product_id,
                'code'  => $x->code,
                'name'  => $x->name,
                'qty'   => round((float) $x->qty, 2),
                'value' => round((float) $x->value, 2),
            ])
            ->values();

        $cap = 12;
        if ($items->count() > $cap) {
            $top = $items->take($cap)->values();
            $rest = $items->slice($cap);
            $top->push(['id' => null, 'name' => 'Other (' . $rest->count() . ' items)', 'qty' => round($rest->sum('qty'), 2), 'value' => round($rest->sum('value'), 2)]);
            $items = $top->values();
        }

        return response()->json([
            'view'   => $this->disp['code'],
            'totals' => [
                'qty'   => round((float) ($tot->qty ?? 0), 2),
                'value' => round((float) ($tot->value ?? 0), 2),
                'items' => (int) ($tot->items ?? 0),
                'lots'  => (int) ($tot->lots ?? 0),
            ],
            'byItem' => $items,
        ]);
    }

    public function exportStock(Request $r): StreamedResponse
    {
        $this->setDisplay($r);
        $this->filters($r);
        $code = $this->disp['code'];
        $valExpr  = $this->conv('remaining_quantity * unit_cost');
        $costExpr = $this->conv('unit_cost');

        $base = $this->stockScope()->orderBy('name')->orderBy('lot')->selectRaw("
            item_code as code, name as product, variant as variant, category_name as category,
            warehouse_name as warehouse, lot as lot, expire_date as expire,
            remaining_quantity as qty, unit as unit, $costExpr as cost, $valExpr as value
        ");

        return response()->streamDownload(function () use ($base) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Item Code', 'Product', 'Variant', 'Category', 'Warehouse', 'Lot', 'Expiry', 'Qty on Hand', 'Unit', "Unit Cost ($code)", "Stock Value ($code)"]);
            $base->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $x) {
                    fputcsv($out, [
                        $x->code,
                        $x->product,
                        $x->variant,
                        $x->category,
                        $x->warehouse,
                        $x->lot,
                        $x->expire ? Carbon::parse($x->expire)->toDateString() : '',
                        $this->n0($x->qty),
                        $x->unit,
                        round((float) $x->cost, 2),
                        round((float) $x->value, 2)
                    ]);
                }
            });
            fclose($out);
        }, "stock_{$code}_" . now()->toDateString() . ".csv", ['Content-Type' => 'text/csv']);
    }

    /* ============================================================
     |  EXCEL EXPORT  ·  styled .xlsx, honours the current filters
     |  Requires: composer require phpoffice/phpspreadsheet
     * ============================================================ */
    private const XL_INK    = 'FF2A2422';
    private const XL_GREEN   = 'FF1E7A52';
    private const XL_GREEND  = 'FF13543A';
    private const XL_CREAM   = 'FFF3ECDF';
    private const XL_PAPER   = 'FFFBF8F2';
    private const XL_LINE    = 'FFE7DECB';
    private const XL_GOLD    = 'FFC98A2B';
    private const XL_POS     = 'FF1E8A5F';
    private const XL_NEG     = 'FFC0573A';
    private const XL_SUB     = 'FF8A7E6E';

    public function exportExcel(Request $r): StreamedResponse
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);
        $code = $this->disp['code'];

        $ss = new Spreadsheet();


        $ss->getDefaultStyle()->getFont()
            ->setName('Khmer OS Siemreap')
            ->setSize(10);
        $ss->getProperties()->setCreator('Gain & Cost')->setTitle('Gain & Cost')
            ->setSubject("Period {$from} to {$to} ({$code})");
        $this->xlSummary($ss->getActiveSheet(), $from, $to, $pay);
        $this->xlSales($ss->createSheet(), $from, $to, $pay);
        $this->xlPurchases($ss->createSheet(), $from, $to, $pay);

        // build the Expenses sheet only if there are expense rows in range
        if ($this->expenses($from, $to, $pay)->exists()) {
            $this->xlExpenses($ss->createSheet(), $from, $to, $pay);
        }

        $this->xlSalesLines($ss->createSheet(), $from, $to, $pay);
        $this->xlStock($ss->createSheet());
        $ss->setActiveSheetIndex(0);

        $name = "gain-cost_{$code}_{$from}_to_{$to}.xlsx";
        return response()->streamDownload(function () use ($ss) {
            (new XlsxWriter($ss))->save('php://output');
        }, $name, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /* ---- shared style helpers ---- */
    private function xlMoneyFmt(): string
    {
        $sym = $this->disp['sym'];
        $dec = (int) $this->disp['dec'];
        $tail = $dec > 0 ? '.' . str_repeat('0', $dec) : '';
        return '"' . $sym . '"#,##0' . $tail . ';[Red]-"' . $sym . '"#,##0' . $tail;
    }

    private function xlCol(int $i): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    }

    private function xlBand(Worksheet $sh, string $last, string $sub, array $meta): int
    {
        // append every filter the user applied (warehouse / category / product / user)
        $meta = array_merge($meta, $this->critMeta());

        $sh->mergeCells("A1:{$last}1");
        $sh->setCellValue('A1', 'Gain & Cost');
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::XL_GREEN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sh->getRowDimension(1)->setRowHeight(34);

        $sh->mergeCells("A2:{$last}2");
        $sh->setCellValue('A2', $sub);
        $sh->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['argb' => 'FFE9F3EC']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::XL_GREEND]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sh->getRowDimension(2)->setRowHeight(18);

        $line = collect($meta)->map(fn($v, $k) => "{$k}:  {$v}")->implode('     ·     ');
        $sh->mergeCells("A3:{$last}3");
        $sh->setCellValue('A3', $line);
        $sh->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['argb' => self::XL_SUB]],
            'alignment' => ['indent' => 1],
        ]);
        $sh->getRowDimension(3)->setRowHeight(16);
        return 5;
    }

    private function xlHeadRow(Worksheet $sh, int $row, array $cols): void
    {
        $i = 0;
        foreach ($cols as $c) {
            $col = $this->xlCol($i);
            $sh->setCellValue($col . $row, $c[0]);
            $sh->getStyle($col . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::XL_INK]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::XL_CREAM]],
                'alignment' => ['horizontal' => ($c[1] === 'r' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT), 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::XL_GOLD]]],
            ]);
            $i++;
        }
        $sh->getRowDimension($row)->setRowHeight(20);
        $sh->freezePane('A' . ($row + 1));
    }

    private function xlZebra(Worksheet $sh, string $range): void
    {
        $c = new Conditional();
        $c->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)=0');
        $c->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::XL_PAPER);
        $cur = $sh->getStyle($range)->getConditionalStyles();
        $cur[] = $c;
        $sh->getStyle($range)->setConditionalStyles($cur);
    }

    /** Apply number formats, gain colour, zebra, borders + a dark totals row. */
    private function xlStyleTable(Worksheet $sh, int $start, int $end, int $totalRow, string $last, array $fmt): void
    {
        $money = $this->xlMoneyFmt();
        $apply = function (array $cols, string $code, int $a, int $b) use ($sh) {
            foreach ($cols as $c) {
                $sh->getStyle("{$c}{$a}:{$c}{$b}")->getNumberFormat()->setFormatCode($code);
                $sh->getStyle("{$c}{$a}:{$c}{$b}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        };
        if ($end >= $start) {
            $apply($fmt['money'] ?? [], $money, $start, $end);
            $apply($fmt['qty'] ?? [], '#,##0.###', $start, $end);
            $apply($fmt['int'] ?? [], '#,##0', $start, $end);
            $apply($fmt['pct'] ?? [], '0.0%', $start, $end);
            if (!empty($fmt['date'])) $sh->getStyle("{$fmt['date']}{$start}:{$fmt['date']}{$end}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            if (!empty($fmt['gain'])) $sh->getStyle("{$fmt['gain']}{$start}:{$fmt['gain']}{$end}")->getFont()->getColor()->setARGB(self::XL_POS);
            $this->xlZebra($sh, "A{$start}:{$last}{$end}");
            $sh->getStyle("A{$start}:{$last}{$end}")->getBorders()->getInside()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB(self::XL_LINE);
        }
        $tr = $totalRow;
        $sh->getStyle("A{$tr}:{$last}{$tr}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::XL_INK]],
        ]);
        $apply($fmt['money'] ?? [], $money, $tr, $tr);
        $apply($fmt['qty'] ?? [], '#,##0.###', $tr, $tr);
        $apply($fmt['int'] ?? [], '#,##0', $tr, $tr);
        $apply($fmt['pct'] ?? [], '0.0%', $tr, $tr);
        $sh->getRowDimension($tr)->setRowHeight(20);
    }

    private function xlWidths(Worksheet $sh, array $w): void
    {
        foreach ($w as $c => $n) $sh->getColumnDimension($c)->setWidth($n);
    }

    /* ---- sheets ---- */
    private function xlSummary(Worksheet $sh, string $from, string $to, ?string $pay): void
    {
        $sh->setTitle('Summary');
        $k = $this->kpis($from, $to, $pay);
        $money = $this->xlMoneyFmt();
        $row = $this->xlBand($sh, 'C', 'Profitability summary', [
            'Period'  => $this->niceDate($from) . '  –  ' . $this->niceDate($to),
            'View'    => $this->disp['code'],
            'Payment' => $pay ?: 'All methods',
        ]);

        $sh->setCellValue('A' . $row, 'Profit & Loss');
        $sh->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB(self::XL_INK);
        $row++;
        $pl = [
            ['Net Revenue',           $k['revenue'],  false],
            ['Cost of Goods (lots)', -$k['cogs'],     false],
            ['Gross Profit',          $k['gross'],    true],
            ['Operating Expenses',   -$k['expense'],  false],
            ['Net Gain',              $k['net'],      true],
        ];
        foreach ($pl as $p) {
            $sh->setCellValue('A' . $row, $p[0]);
            $sh->setCellValue('C' . $row, $p[1]);
            $sh->getStyle('C' . $row)->getNumberFormat()->setFormatCode($money);
            $sh->getStyle('A' . $row . ':C' . $row)->getFont()->setBold($p[2]);
            $sh->getStyle('A' . $row)->getAlignment()->setIndent(1);
            if ($p[2]) {
                $sh->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::XL_CREAM);
                $sh->getStyle('A' . $row . ':C' . $row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::XL_GOLD);
            }
            $row++;
        }

        $row++;
        $sh->setCellValue('A' . $row, 'Key figures');
        $sh->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB(self::XL_INK);
        $row++;
        $stat = [
            ['Gross Margin',      $k['grossMargin'] / 100, '0.0%'],
            ['Net Margin',        $k['netMargin'] / 100,   '0.0%'],
            ['Stock Purchases',   $k['purch'],             $money],
            ['VAT Collected',     $k['vat'],               $money],
            ['Sales (documents)', $k['invoices'],          '#,##0'],
            ['Units Sold',        $k['qty'],               '#,##0.###'],
            ['Avg Order Value',   $k['aov'],               $money],
        ];
        foreach ($stat as $s) {
            $sh->setCellValue('A' . $row, $s[0]);
            $sh->setCellValue('C' . $row, $s[1]);
            $sh->getStyle('C' . $row)->getNumberFormat()->setFormatCode($s[2]);
            $sh->getStyle('A' . $row)->getAlignment()->setIndent(1);
            $row++;
        }

        $this->xlWidths($sh, ['A' => 28, 'B' => 3, 'C' => 22]);
        $sh->getStyle('C5:C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sh->setShowGridlines(false);
    }

    private function xlSales(Worksheet $sh, string $from, string $to, ?string $pay): void
    {
        $sh->setTitle('Sales');
        $rows = $this->sales($from, $to, $pay)->groupBy('document_no')->selectRaw('
                document_no as doc, MIN(posting_date) as date, MAX(customer_name) as customer,
                MAX(payment_method) as pay, SUM(ABS(quantity)) as qty,
                SUM(' . $this->eRevenue() . ') as revenue, SUM(' . $this->eCogs() . ') as cogs, SUM(' . $this->eGain() . ') as gain
            ')->orderByDesc('date')->get();

        $hr = $this->xlBand($sh, 'I', 'Sales by document', [
            'Period' => $this->niceDate($from) . ' – ' . $this->niceDate($to),
            'View' => $this->disp['code'],
            'Payment' => $pay ?: 'All',
        ]);
        $this->xlHeadRow($sh, $hr, [['Document', 'l'], ['Date', 'l'], ['Customer', 'l'], ['Payment', 'l'], ['Units', 'r'], ['Revenue', 'r'], ['Cost', 'r'], ['Gain', 'r'], ['Margin', 'r']]);

        $r = $hr + 1;
        $start = $r;
        $data = [];
        foreach ($rows as $x) {
            $rev = (float) $x->revenue;
            $gain = (float) $x->gain;
            $data[] = [$x->doc, Carbon::parse($x->date)->format('Y-m-d'), $x->customer, $x->pay, (float) $x->qty, $rev, (float) $x->cogs, $gain, $rev ? $gain / $rev : 0];
        }
        if ($data) $sh->fromArray($data, null, 'A' . $start);
        $end = $data ? $start + count($data) - 1 : $start;
        if (!$data) $sh->setCellValue('A' . $start, 'No sales in this period.');

        $tr = $end + 1;
        $sh->setCellValue('A' . $tr, 'TOTAL');
        foreach (['E', 'F', 'G', 'H'] as $c) $sh->setCellValue($c . $tr, "=SUM({$c}{$start}:{$c}{$end})");
        $sh->setCellValue('I' . $tr, "=IF(F{$tr}=0,0,H{$tr}/F{$tr})");
        $this->xlStyleTable($sh, $start, $end, $tr, 'I', ['money' => ['F', 'G', 'H'], 'gain' => 'H', 'qty' => ['E'], 'pct' => ['I'], 'date' => 'B']);
        $this->xlWidths($sh, ['A' => 20, 'B' => 12, 'C' => 26, 'D' => 14, 'E' => 10, 'F' => 15, 'G' => 15, 'H' => 15, 'I' => 10]);
        $sh->setAutoFilter("A{$hr}:I{$end}");
        $sh->setShowGridlines(false);
    }

    private function xlPurchases(Worksheet $sh, string $from, string $to, ?string $pay): void
    {
        $sh->setTitle('Purchases');
          $rows = $this->purchases($from, $to, $pay)->groupBy('document_no')->selectRaw('
                document_no as doc, MIN(posting_date) as date, MAX(vendor_name) as vendor, MAX(payment_method) as pay,
                COUNT(*) as line_count, SUM(ABS(quantity)) as qty, -SUM(' . $this->conv('line_amount') . ') as cost
            ')->orderByDesc('date')->get();
        $hr = $this->xlBand($sh, 'G', 'Stock purchases  ·  informational, not in profit', [
            'Period' => $this->niceDate($from) . ' – ' . $this->niceDate($to),
            'View' => $this->disp['code'],
            'Payment' => $pay ?: 'All',
        ]);
        $this->xlHeadRow($sh, $hr, [['Document', 'l'], ['Date', 'l'], ['Vendor', 'l'], ['Payment', 'l'], ['Lines', 'r'], ['Units', 'r'], ['Cost', 'r']]);

        $r = $hr + 1;
        $start = $r;
        $data = [];
        foreach ($rows as $x) $data[] = [$x->doc, Carbon::parse($x->date)->format('Y-m-d'), $x->vendor, $x->pay, (int) $x->line_count, (float) $x->qty, (float) $x->cost];
        if ($data) $sh->fromArray($data, null, 'A' . $start);
        $end = $data ? $start + count($data) - 1 : $start;
        if (!$data) $sh->setCellValue('A' . $start, 'No purchases in this period.');

        $tr = $end + 1;
        $sh->setCellValue('A' . $tr, 'TOTAL');
        foreach (['F', 'G'] as $c) $sh->setCellValue($c . $tr, "=SUM({$c}{$start}:{$c}{$end})");
        $this->xlStyleTable($sh, $start, $end, $tr, 'G', ['money' => ['G'], 'int' => ['E'], 'qty' => ['F'], 'date' => 'B']);
        $this->xlWidths($sh, ['A' => 20, 'B' => 12, 'C' => 26, 'D' => 14, 'E' => 8, 'F' => 10, 'G' => 16]);
        $sh->setAutoFilter("A{$hr}:G{$end}");
        $sh->setShowGridlines(false);
    }

    private function xlExpenses(Worksheet $sh, string $from, string $to, ?string $pay): void
    {
        $sh->setTitle('Expenses');
        $rows = $this->expenses($from, $to, $pay)->orderByDesc('expense_date')->get();   // was posting_date

        $hr = $this->xlBand($sh, 'F', 'Operating expenses', [
            'Period' => $this->niceDate($from) . ' – ' . $this->niceDate($to),
            'View' => $this->disp['code'],
            'Payment' => $pay ?: 'All',
        ]);
        $this->xlHeadRow($sh, $hr, [['Code', 'l'], ['Date', 'l'], ['Expense', 'l'], ['Note', 'l'], ['Payment', 'l'], ['Amount', 'r']]);

        $r = $hr + 1;
        $start = $r;
        $data = [];
        foreach ($rows as $e) {
            $data[] = [
                $e->expense_code ?: ('EXP-' . $e->id),                 // was entry_no / LE-
                Carbon::parse($e->expense_date)->format('Y-m-d'),      // was posting_date
                $e->expense_name,                                       // was name
                $e->note,                                               // was document_no
                $e->payment_method,
                $this->dispv($e->amount, $e->factor),                  // was net_amount
            ];
        }
        if ($data) $sh->fromArray($data, null, 'A' . $start);
        $end = $data ? $start + count($data) - 1 : $start;
        if (!$data) $sh->setCellValue('A' . $start, 'No expenses in this period.');

        $tr = $end + 1;
        $sh->setCellValue('A' . $tr, 'TOTAL');
        $sh->setCellValue('F' . $tr, "=SUM(F{$start}:F{$end})");
        $this->xlStyleTable($sh, $start, $end, $tr, 'F', ['money' => ['F'], 'date' => 'B']);
        $this->xlWidths($sh, ['A' => 16, 'B' => 12, 'C' => 28, 'D' => 18, 'E' => 14, 'F' => 16]);
        $sh->setAutoFilter("A{$hr}:F{$end}");
        $sh->setShowGridlines(false);
    }
    private function xlSalesLines(Worksheet $sh, string $from, string $to, ?string $pay): void
    {
        $sh->setTitle('Sales Lines');
        $select = '
            document_no as doc, posting_date as date, customer_name as customer, lot as lot, item_code as code,
            name as product, variant as variant, category_name as category, ABS(quantity) as qty, unit as unit,
            discount_percent as disc_pct, vat as vat_pct,
            ' . $this->conv('unit_cost') . ' as cost,
            ' . $this->conv('COALESCE(sell_price, unit_price)') . ' as sell,
            ' . $this->conv('line_amount') . ' as subtotal,
            ' . $this->conv('discount_amount') . ' as disc_amt,
            ' . $this->conv('vat_amount') . ' as vat_amt,
            ' . $this->conv('net_amount') . ' as net,
            ' . $this->conv('grand_total_amount') . ' as grand,
            ' . $this->eGain() . ' as profit';
        $rows = $this->sales($from, $to, $pay)->orderByDesc('posting_date')->orderByDesc('document_no')->selectRaw($select)->get();

        $hr = $this->xlBand($sh, 'T', 'Every sale line  ·  cost, sell & gain', [
            'Period' => $this->niceDate($from) . ' – ' . $this->niceDate($to),
            'View' => $this->disp['code'],
            'Payment' => $pay ?: 'All',
        ]);
        $this->xlHeadRow($sh, $hr, [
            ['Doc', 'l'],
            ['Date', 'l'],
            ['Customer', 'l'],
            ['Lot', 'l'],
            ['Item Code', 'l'],
            ['Product', 'l'],
            ['Variant', 'l'],
            ['Category', 'l'],
            ['Qty', 'r'],
            ['Unit', 'l'],
            ['Cost', 'r'],
            ['Sell', 'r'],
            ['Subtotal', 'r'],
            ['Disc %', 'r'],
            ['Disc Amt', 'r'],
            ['VAT %', 'r'],
            ['VAT', 'r'],
            ['Net', 'r'],
            ['Grand', 'r'],
            ['Gain', 'r'],
        ]);

        $start = $hr + 1;
        $data = [];
        foreach ($rows as $x) {
            $data[] = [
                $x->doc,
                Carbon::parse($x->date)->format('Y-m-d'),
                $x->customer,
                $x->lot,
                $x->code,
                $x->product,
                $x->variant,
                $x->category,
                (float) $x->qty,
                $x->unit,
                (float) $x->cost,
                (float) $x->sell,
                (float) $x->subtotal,
                ((float) $x->disc_pct) / 100,
                (float) $x->disc_amt,
                ((float) $x->vat_pct) / 100,
                (float) $x->vat_amt,
                (float) $x->net,
                (float) $x->grand,
                (float) $x->profit,
            ];
        }
        if ($data) $sh->fromArray($data, null, 'A' . $start);
        $end = $data ? $start + count($data) - 1 : $start;
        if (!$data) $sh->setCellValue('A' . $start, 'No sale lines in this period.');

        $tr = $end + 1;
        $sh->setCellValue('A' . $tr, 'TOTAL');
        foreach (['I', 'M', 'O', 'Q', 'R', 'S', 'T'] as $c) $sh->setCellValue($c . $tr, "=SUM({$c}{$start}:{$c}{$end})");
        $this->xlStyleTable($sh, $start, $end, $tr, 'T', [
            'money' => ['K', 'L', 'M', 'O', 'Q', 'R', 'S', 'T'],
            'gain' => 'T',
            'qty' => ['I'],
            'pct' => ['N', 'P'],
            'date' => 'B',
        ]);
        $this->xlWidths($sh, ['A' => 16, 'B' => 12, 'C' => 22, 'D' => 12, 'E' => 14, 'F' => 26, 'G' => 14, 'H' => 16, 'I' => 9, 'J' => 8, 'K' => 12, 'L' => 12, 'M' => 14, 'N' => 8, 'O' => 12, 'P' => 8, 'Q' => 12, 'R' => 14, 'S' => 14, 'T' => 14]);
        $sh->setAutoFilter("A{$hr}:T{$end}");
        $sh->setShowGridlines(false);
    }


    private function xlStock(Worksheet $sh): void
    {
        $sh->setTitle('Stock');
        $valExpr  = $this->conv('item_ledger_entries.remaining_quantity * item_ledger_entries.unit_cost');
        $costExpr = $this->conv('item_ledger_entries.unit_cost');
        $rows = $this->stockScope()
            ->orderBy('p.name')->orderBy('item_ledger_entries.lot')
            ->selectRaw("
                item_ledger_entries.item_code as code,
                p.name as product,
                item_ledger_entries.variant as variant,
                p.category_name as category,
                item_ledger_entries.warehouse_name as warehouse,
                item_ledger_entries.lot as lot,
                item_ledger_entries.expire_date as expire,
                item_ledger_entries.remaining_quantity as qty,
                item_ledger_entries.unit as unit,
                $costExpr as cost,
                $valExpr as value
            ")->get();

        $hr = $this->xlBand($sh, 'K', 'Stock on hand  ·  valued at cost', [
            'As of' => $this->niceDate(now()->toDateString()),
            'View'  => $this->disp['code'],
        ]);
        $this->xlHeadRow($sh, $hr, [
            ['Item Code', 'l'],
            ['Product', 'l'],
            ['Variant', 'l'],
            ['Category', 'l'],
            ['Warehouse', 'l'],
            ['Lot', 'l'],
            ['Expiry', 'l'],
            ['Qty', 'r'],
            ['Unit', 'l'],
            ['Unit Cost', 'r'],
            ['Stock Value', 'r'],
        ]);

        $start = $hr + 1;
        $data = [];
        foreach ($rows as $x) {
            $data[] = [
                $x->code,
                $x->product,
                $x->variant,
                $x->category,
                $x->warehouse,
                $x->lot,
                $x->expire ? Carbon::parse($x->expire)->format('Y-m-d') : '',
                (float) $x->qty,
                $x->unit,
                (float) $x->cost,
                (float) $x->value
            ];
        }
        if ($data) $sh->fromArray($data, null, 'A' . $start);
        $end = $data ? $start + count($data) - 1 : $start;
        if (!$data) $sh->setCellValue('A' . $start, 'No stock on hand for this filter.');

        $tr = $end + 1;
        $sh->setCellValue('A' . $tr, 'TOTAL');
        $sh->setCellValue('H' . $tr, "=SUM(H{$start}:H{$end})");
        $sh->setCellValue('K' . $tr, "=SUM(K{$start}:K{$end})");
        $this->xlStyleTable($sh, $start, $end, $tr, 'K', ['money' => ['J', 'K'], 'qty' => ['H']]);
        $this->xlWidths($sh, ['A' => 14, 'B' => 26, 'C' => 14, 'D' => 16, 'E' => 16, 'F' => 12, 'G' => 11, 'H' => 10, 'I' => 8, 'J' => 13, 'K' => 15]);
        $sh->setAutoFilter("A{$hr}:K{$end}");
        $sh->setShowGridlines(false);
    }
    private function xlServices(Worksheet $sh, string $from, string $to, ?string $pay): void
    {
        $sh->setTitle('Services');
        $s = $this->svc;

        $docSel  = "h.{$s['docno']}";
        $custSel = $s['cust'] !== '' ? "h.{$s['cust']}" : "''";   // '' → blank col, never crashes

        $rows = $this->serviceBase($from, $to, $pay)
            ->orderByDesc("h.{$s['date']}")
            ->selectRaw("
            {$docSel} as doc,
            h.{$s['date']} as date,
            {$custSel} as customer,
            p.name as service,
            h.{$s['pay']} as pay,
            {$s['line']}.{$s['qty']} as qty,
            {$this->serviceConv("{$s['line']}.{$s['amt']}")} as amount
        ")->get();

        $hr = $this->xlBand($sh, 'H', 'Service charges  ·  delivery & fees billed on invoices', [
            'Period'  => $this->niceDate($from) . ' – ' . $this->niceDate($to),
            'View'    => $this->disp['code'],
            'Payment' => $pay ?: 'All',
        ]);
        $this->xlHeadRow($sh, $hr, [
            ['Document', 'l'],
            ['Date', 'l'],
            ['Customer', 'l'],
            ['Service', 'l'],
            ['Payment', 'l'],
            ['Qty', 'r'],
            ['Unit Price', 'r'],
            ['Amount', 'r'],
        ]);

        $start = $hr + 1;
        $data = [];
        foreach ($rows as $x) {
            $qty = (float) $x->qty;
            $amt = (float) $x->amount;
            $data[] = [
                $x->doc,
                Carbon::parse($x->date)->format('Y-m-d'),
                $x->customer,
                $x->service,
                $x->pay,
                $qty,
                $qty ? $amt / $qty : 0,   // effective unit price
                $amt,
            ];
        }
        if ($data) $sh->fromArray($data, null, 'A' . $start);
        $end = $data ? $start + count($data) - 1 : $start;
        if (!$data) $sh->setCellValue('A' . $start, 'No service charges in this period.');

        $tr = $end + 1;
        $sh->setCellValue('A' . $tr, 'TOTAL');
        foreach (['F', 'H'] as $c) $sh->setCellValue($c . $tr, "=SUM({$c}{$start}:{$c}{$end})");
        $this->xlStyleTable($sh, $start, $end, $tr, 'H', ['money' => ['G', 'H'], 'qty' => ['F'], 'date' => 'B']);
        $this->xlWidths($sh, ['A' => 18, 'B' => 12, 'C' => 24, 'D' => 26, 'E' => 14, 'F' => 10, 'G' => 14, 'H' => 16]);
        $sh->setAutoFilter("A{$hr}:H{$end}");
        $sh->setShowGridlines(false);
    }
    /* ============================================================
     |  HELPERS
     * ============================================================ */
    private function niceDate($d): string
    {
        return Carbon::parse($d)->format('d M Y');
    }

    private function n0($v): string
    {
        $v = (float) $v;
        return $v == (int) $v ? (string) (int) $v : rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }
    /** The filters the user actually applied, resolved to readable label => value.
     *  warehouse_id / product_id are turned into names; category / created_by are already names. */
    private function critMeta(): array
    {
        $m = [];
        if (!empty($this->crit['warehouse'])) {
            $wh = ItemLedgerEntry::where('warehouse_id', $this->crit['warehouse'])->value('warehouse_name');
            $m['Warehouse'] = $wh ?: ('WH #' . $this->crit['warehouse']);
        }
        if (!empty($this->crit['category'])) $m['Category'] = $this->crit['category'];
        if (!empty($this->crit['product'])) {
            $pn = ItemLedgerEntry::where('product_id', $this->crit['product'])->value('name');
            $m['Product'] = $pn ?: ('#' . $this->crit['product']);
        }
        if (!empty($this->crit['created_by'])) $m['Created by'] = $this->crit['created_by'];
        return $m;
    }
    /** Stock adjustments only — date + crit filters, no value/transfer exclusions. */
    private function adjustments(string $from, string $to, ?string $pay)
    {
        return ItemLedgerEntry::query()
            ->whereBetween('posting_date', [$from, $to])
            ->where('document_type', 'Adjustment')
            ->when($pay, fn($q) => $q->where('payment_method', $pay))
            ->when($this->crit['warehouse']  ?? null, fn($q, $v) => $q->where('warehouse_id', $v))
            ->when($this->crit['created_by'] ?? null, fn($q, $v) => $q->where('created_by', $v))
            ->when($this->crit['product']    ?? null, fn($q, $v) => $q->where('product_id', $v))
            ->when($this->crit['category']   ?? null, fn($q, $v) => $q->where('category_name', $v));
    }

    /** Inventory value (now, post-adjustment) + adjustment gain/loss (in period), at cost. */
    private function inventoryKpis(string $from, string $to, ?string $pay): array
    {
        $costExpr = $this->conv('unit_cost * ABS(quantity)');
        $pos = $this->purchaseEntryType;   // 'positive' = found = gain
        $neg = $this->saleEntryType;       // 'negative' = lost  = loss

        $a = $this->adjustments($from, $to, $pay)->selectRaw("
        SUM(CASE WHEN entry_type = '{$pos}' THEN {$costExpr} ELSE 0 END) as gain_val,
        SUM(CASE WHEN entry_type = '{$neg}' THEN {$costExpr} ELSE 0 END) as loss_val,
        SUM(CASE WHEN entry_type = '{$pos}' THEN ABS(quantity) ELSE 0 END) as gain_qty,
        SUM(CASE WHEN entry_type = '{$neg}' THEN ABS(quantity) ELSE 0 END) as loss_qty,
        SUM(CASE WHEN entry_type = '{$pos}' THEN 1 ELSE 0 END) as gain_n,
        SUM(CASE WHEN entry_type = '{$neg}' THEN 1 ELSE 0 END) as loss_n
    ")->first();

        $gain = (float) ($a->gain_val ?? 0);
        $loss = (float) ($a->loss_val ?? 0);
        $k = $this->kpis($from, $to, $pay);
        $tradingNet = $k['net'];
        // closing inventory value = current stock at cost (already reflects adjustments)
        $valExpr = $this->conv('item_ledger_entries.remaining_quantity * item_ledger_entries.unit_cost');
        $inv = $this->stockScope()->selectRaw("SUM($valExpr) as v, SUM(item_ledger_entries.remaining_quantity) as q, COUNT(*) as lots")->first();

        return [
            'inventoryValue' => round((float) ($inv->v ?? 0), 2),
            'inventoryQty'   => round((float) ($inv->q ?? 0), 2),
            'lots'           => (int) ($inv->lots ?? 0),
            'gain'           => round($gain, 2),
            'loss'           => round($loss, 2),
            'net'            => round($gain - $loss, 2),
            'gainQty'        => round((float) ($a->gain_qty ?? 0), 2),
            'lossQty'        => round((float) ($a->loss_qty ?? 0), 2),
            'tradingNet'  => round($tradingNet, 2),
            'trueNet'     => round($tradingNet + ($gain - $loss), 2),   // 🔥 actual money
            'gainCount'      => (int) ($a->gain_n ?? 0),
            'lossCount'      => (int) ($a->loss_n ?? 0),
        ];
    }

    public function inventory(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);
        return response()->json([
            'view'  => $this->disp['code'],
            'cards' => $this->inventoryKpis($from, $to, $pay),
        ]);
    }

    private function detailAdjustment(string $sign, string $from, string $to, ?string $pay): array
    {
        $q = $this->adjustments($from, $to, $pay);
        if ($sign === 'positive') $q->where('entry_type', $this->purchaseEntryType);
        elseif ($sign === 'negative') $q->where('entry_type', $this->saleEntryType);

        $rows = $q->orderByDesc('posting_date')->orderByDesc('entry_no')->get();

        $gain = 0;
        $loss = 0;
        $lines = $rows->map(function ($l) use (&$gain, &$loss) {
            $qty = abs((float) $l->quantity);
            $isGain = $l->entry_type === $this->purchaseEntryType;
            $val = $this->dispv((float) $l->unit_cost * $qty, $l->factor);
            if ($isGain) $gain += $val;
            else $loss += $val;
            return ['cells' => [
                ['v' => $l->name, 'sub' => trim(($l->item_code ?: '') . ' · lot ' . ($l->lot ?: '—'))],
                $this->niceDate($l->posting_date),
                $l->warehouse_name ?: '—',
                ['v' => ($isGain ? '+' : '−') . $this->n0($qty), 'cls' => $isGain ? 'pos' : 'neg'],
                $this->m($this->dispv((float) $l->unit_cost, $l->factor)),
                ['v' => ($isGain ? '+' : '−') . $this->m($val), 'cls' => $isGain ? 'pos' : 'neg'],
            ]];
        })->values();

        $net = $gain - $loss;
        $title = $sign === 'positive' ? 'Inventory Gains (found)'
            : ($sign === 'negative' ? 'Inventory Losses (shrinkage)' : 'Inventory Adjustments');
        $accent = $sign === 'negative' ? 'cogs' : ($sign === 'positive' ? 'profit' : ($net >= 0 ? 'profit' : 'cogs'));

        $kpis = [];
        if ($sign !== 'negative') $kpis[] = ['Gain (found)', round($gain, 2), 'profit'];
        if ($sign !== 'positive') $kpis[] = ['Loss (shrinkage)', round($loss, 2), 'cogs'];
        $kpis[] = ['Net', round($net, 2), $net >= 0 ? 'profit' : 'cogs'];
        $kpis[] = ['Entries', $rows->count(), 'purchase', '#'];

        return [
            'accent' => $accent,
            'eyebrow' => 'Adjustment · at cost',
            'title' => $title,
            'tags' => [$rows->count() . ' entries', 'view ' . $this->disp['code']],
            'kpis' => $kpis,
            'columns' => ['Item / Lot', 'Date', 'Warehouse', 'Qty', 'Unit Cost', 'Value'],
            'lines' => $lines,
        ];
    }

    private function detailInventory(string $from, string $to, ?string $pay): array
    {
        $rows = $this->stockScope()->selectRaw("
    p.name as p_name,
    item_ledger_entries.item_code as code,
    item_ledger_entries.warehouse_name as wh,
    item_ledger_entries.lot as lot,
    item_ledger_entries.remaining_quantity as qty,
    item_ledger_entries.unit_cost as unit_cost,
    item_ledger_entries.factor as factor
")->get()->map(function ($l) {
            $qty = (float) $l->qty;
            return (object) [
                'name' => $l->p_name,
                'code' => $l->code,
                'wh'   => $l->wh,
                'lot'  => $l->lot,
                'qty'  => $qty,
                'cost' => $this->dispv((float) $l->unit_cost, $l->factor),
                'val'  => $this->dispv((float) $l->unit_cost * $qty, $l->factor),
            ];
        })->sortByDesc('val')->values();

        $lines = $rows->take(100)->map(fn($x) => ['cells' => [
            ['v' => $x->name, 'sub' => trim(($x->code ?: '') . ' · ' . ($x->wh ?: ''))],
            $x->lot ?: '—',
            $this->n0($x->qty),
            $this->m($x->cost),
            $this->m($x->val),
        ]])->values();

        return [
            'accent' => 'gold',
            'eyebrow' => 'Inventory · valued at cost',
            'title' => 'Stock on Hand',
            'tags' => [$rows->count() . ' lots', 'view ' . $this->disp['code']],
            'kpis' => [
                ['Total Value', round($rows->sum('val'), 2), 'gold'],
                ['Total Units', round($rows->sum('qty'), 2), 'revenue', '#'],
                ['Lots', $rows->count(), 'purchase', '#'],
            ],
            'columns' => ['Item', 'Lot', 'Qty', 'Unit Cost', 'Value'],
            'lines' => $lines,
        ];
    }


    private function serviceConv(string $amt): string
    {
        $s = $this->svc;
        return $this->isBaseView() ? "($amt)" : "($amt * h.{$s['fact']})";
    }
    /* ============================================================
        |  SERVICES  ·  service-type invoice lines (delivery, fees…)
        |  Services have no stock → NOT in ItemLedgerEntry. Read straight
        |  from invoice_lines + invoice_headers where product.type = 'service'.
        |  Honours: date range · payment · currency view.
        * ============================================================ */
    public function services(Request $r)
    {
        $this->setDisplay($r);
        [$from, $to, $pay] = $this->filters($r);

        $s    = $this->svc;
        $rev  = $this->serviceConv("l.{$s['amt']}");
        $cost = $this->serviceConv("(l.{$s['cost']} * l.{$s['qty']})");
        $gain = $this->serviceConv("(l.{$s['amt']} - l.{$s['cost']} * l.{$s['qty']})");
        $base = $this->serviceBase($from, $to, $pay);

        $tot = (clone $base)->selectRaw("
        SUM($rev)  as revenue,
        SUM($cost) as cost,
        SUM($gain) as gain,
        SUM(l.{$s['qty']}) as qty,
        COUNT(*) as line_count,
        COUNT(DISTINCT h.id) as invoices
    ")->first();

        $byService = (clone $base)
            ->groupBy('p.id')
            ->selectRaw("p.id as id, MAX(p.name) as name, SUM($rev) as value, SUM($gain) as gain, SUM(l.{$s['qty']}) as qty, COUNT(*) as line_count")
            ->orderByRaw("SUM($rev) DESC")
            ->get()
            ->map(fn($x) => [
                'name'  => $x->name ?: 'Service',
                'value' => round((float) $x->value, 2),
                'gain'  => round((float) $x->gain, 2),
                'qty'   => round((float) $x->qty, 2),
                'lines' => (int) $x->line_count,
            ])->values();

        return response()->json([
            'view'   => $this->disp['code'],
            'totals' => [
                'revenue'  => round((float) ($tot->revenue ?? 0), 2),
                'cost'     => round((float) ($tot->cost ?? 0), 2),
                'gain'     => round((float) ($tot->gain ?? 0), 2),
                'qty'      => round((float) ($tot->qty ?? 0), 2),
                'lines'    => (int) ($tot->line_count ?? 0),
                'invoices' => (int) ($tot->invoices ?? 0),
            ],
            'byService' => $byService,
        ]);
    }
}
