<?php


namespace App\Http\Controllers;
use App\Models\ItemLedgerEntry;
use App\Models\PurchaseLine;
use App\Models\WarehouseProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class PurchaseReturnController extends Controller
{
  /** Positive ledger entry_type (receipts / transfers in). */
    private string $positive = 'positive';
    private string $negative = 'negative';

    /** Warehouse ids the current user may act on. */
    private function userWarehouseIds()
    {
        return Auth::user()->warehouses->pluck('id');
    }

    /* ============================================================
     |  RETURNABLE LOTS  ·  GET  /purchase-return/returnable?no=GRN26-0001
     |  Reads the lots on this GRN, then for EACH lot finds every location
     |  that still holds remaining stock (matched by LOT only — receipts AND
     |  transfers count, regardless of which GRN). Scoped to user warehouses.
     |  The same lot in 2 locations returns 2 rows; the user picks which one.
     * ============================================================ */
    public function returnable(Request $request)
    {
        $grnNo = trim((string) $request->input('no', ''));
        if ($grnNo === '') {
            return response()->json(['error' => 'Missing GRN number'], 422);
        }

        $whIds = $this->userWarehouseIds();

        // 1) which lots (and products) does this GRN cover?
        //    Read the GRN's own ledger rows to learn its product_id + lot set.
        $grnRows = ItemLedgerEntry::query()
            ->where('document_no', $grnNo)
            ->get(['product_id', 'lot']);

        if ($grnRows->isEmpty()) {
            return response()->json(['lines' => [], 'message' => 'GRN not found.']);
        }

        // distinct product+lot pairs on this GRN
        $pairs = $grnRows
            ->map(fn($r) => ['product_id' => $r->product_id, 'lot' => (string) ($r->lot ?? '')])
            ->unique(fn($p) => $p['product_id'] . '|' . $p['lot'])
            ->values();

        // 2) for each product+lot, find ALL positive rows with remaining,
        //    in any location the user can touch — matched by LOT, not GRN.
        $lines = collect();
        foreach ($pairs as $pair) {
            $rows = ItemLedgerEntry::query()
                ->where('entry_type', $this->positive)
                ->where('remaining_quantity', '>', 0)
                ->whereIn('warehouse_id', $whIds)
                ->where('product_id', $pair['product_id'])
                ->when($pair['lot'] !== '', fn($q) => $q->where('lot', $pair['lot']), fn($q) => $q->whereNull('lot'))
                ->orderBy('warehouse_id')
                ->get();

            if ($rows->isEmpty()) continue;

            // one line per LOCATION (warehouse), remaining summed within that location
            $rows->groupBy('warehouse_id')->each(function ($grp) use (&$lines) {
                $f = $grp->first();
                $remaining = (float) $grp->sum('remaining_quantity');
                if ($remaining <= 0) return;
                $lines->push([
                    'key'            => $f->product_id . '|' . ($f->lot ?? '') . '|' . $f->warehouse_id,
                    'product_id'     => $f->product_id,
                    'item_code'      => $f->item_code,
                    'name'           => $f->name,
                    'variant'        => $f->variant,
                    'description'    => $f->description ?? '',
                    'lot'            => $f->lot,
                    'expire_date'    => $f->expire_date,
                    'warehouse_id'   => $f->warehouse_id,
                    'warehouse_name' => $f->warehouse_name,
                    'unit'           => $f->unit,
                    'unit_cost'      => (float) $f->unit_cost,
                    'factor'         => (float) ($f->factor ?: 1),
                    'currency_name'  => $f->currency_name,
                    'remaining'      => round($remaining, 6),   // max returnable at THIS location
                ]);
            });
        }

        if ($lines->isEmpty()) {
            return response()->json(['lines' => [], 'message' => 'No returnable stock for these lots in your locations.']);
        }

        $lines = $lines->sortBy('name')->values();

        $vendor = ItemLedgerEntry::where('document_no', $grnNo)->first(['vendor_id', 'vendor_name', 'currency_name']);

        return response()->json([
            'grn_no'        => $grnNo,
            'vendor_id'     => $vendor->vendor_id ?? '',
            'vendor_name'   => $vendor->vendor_name ?? '',
            'currency_name' => $vendor->currency_name ?? '',
            'lines'         => $lines,
        ]);
    }

    /* ============================================================
     |  CONFIRM RETURN  ·  POST  /purchase-return/confirm
     |  Body: { no, return_date, items:[{key, qty}] }
     * ============================================================ */
    public function confirm(Request $request)
    {
        $grnNo      = trim((string) $request->input('no', ''));
        $returnDate = $request->input('return_date') ?: now()->toDateString();
        $reason     = trim((string) $request->input('reason', ''));   // user-entered return reason
        $items      = $request->input('items', []);   // [{key, qty}, ...]

        if ($grnNo === '' || empty($items)) {
            return response()->json(['error' => 'Nothing to return.'], 422);
        }
        if ($reason === '') {
            return response()->json(['error' => 'Please enter a reason for the return.'], 422);
        }

        $whIds = $this->userWarehouseIds();

        try {
            $result = DB::transaction(function () use ($grnNo, $returnDate, $reason, $items, $whIds) {

                // document_no stays the SAME as the original GRN (per requirement)
                $docNo = $grnNo;

                $created = [];
                foreach ($items as $row) {
                    $key   = (string) ($row['key'] ?? '');
                    $qty   = (float) ($row['qty'] ?? 0);
                    // editable cost removed — always use the lot's original cost
                    if ($key === '' || $qty <= 0) continue;
                    $qty = max(0.01, $qty);

                    [$productId, $lot, $warehouseId] = array_pad(explode('|', $key), 3, null);

                    // permission guard — user can only return from their warehouses
                    if (!$whIds->contains((int) $warehouseId)) {
                        throw new \RuntimeException("You don't have permission for warehouse #{$warehouseId}.");
                    }

                    // lock the SOURCE positive rows for this lot/location.
                    // Matched by LOT + warehouse only (receipts AND transfers) —
                    // NOT by GRN, because transferred stock carries a different doc no.
                    $sources = ItemLedgerEntry::query()
                        ->where('entry_type', $this->positive)
                        ->where('remaining_quantity', '>', 0)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->when($lot !== null && $lot !== '', fn($q) => $q->where('lot', $lot), fn($q) => $q->whereNull('lot'))
                        ->orderBy('posting_date')        // consume oldest receipt first
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $available = (float) $sources->sum('remaining_quantity');
                    if ($qty > $available + 1e-6) {
                        $name = optional($sources->first())->name ?: ('Product #' . $productId);
                        throw new \RuntimeException("Return qty {$qty} exceeds remaining {$available} for {$name} (lot " . ($lot ?: '—') . ").");
                    }

                    // representative header data from the first source row
                    $h = $sources->first();
                                 // always the lot's original cost — never user-supplied
                    $unitCost = (float) $h->unit_cost;
                    $factor   = (float) ($h->factor ?: 1);

                    // ---- 1) reduce remaining_quantity on the source rows (FEFO) ----
                    $need = $qty;
                    foreach ($sources as $s) {
                        if ($need <= 1e-6) break;
                        $take = min((float) $s->remaining_quantity, $need);
                        $s->remaining_quantity = (float) $s->remaining_quantity - $take;
                        $s->save();
                        $need -= $take;
                    }

                    // ---- 2) reduce WarehouseProduct stock at this location ----
                    //   Locks every row matching this product/warehouse/lot (there can be
                    //   more than one if the lot was topped up separately), validates the
                    //   combined quantity covers the return, then decrements across them —
                    //   mirroring the FIFO deduction already done on the ledger rows above.
                    $wpRows = WarehouseProduct::query()
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->when($lot !== null && $lot !== '', fn($q) => $q->where('lot', $lot), fn($q) => $q->whereNull('lot'))
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $wpAvailable = (float) $wpRows->sum('quantity');
                    if ($wpRows->isEmpty() || $qty > $wpAvailable + 1e-6) {
                        throw new \RuntimeException(
                            "Warehouse stock ({$wpAvailable}) is less than return qty ({$qty}) for "
                                . ($h->name ?? ('product #' . $productId)) . ', lot ' . ($lot ?: '—') . '.'
                        );
                    }

                    $wpNeed = $qty;
                    foreach ($wpRows as $wp) {
                        if ($wpNeed <= 1e-6) break;
                        $take = min((float) $wp->quantity, $wpNeed);
                        $wp->quantity = (float) $wp->quantity - $take;
                        $wp->save();
                        $wpNeed -= $take;
                    }

                    // ---- 3) insert the Purchase Return ledger row ----
                    //   quantity is NEGATIVE (stock-out, same convention as Sale) and
                    //   line_amount/net_amount/grand_total_amount are POSITIVE (cost
                    //   recovered from the vendor) — mirrors Sale's "money is the inverse
                    //   sign of quantity" convention.
                    //   s() = never store null; empty becomes '' so columns stay strings.
                    $s = fn($v) => ($v === null || $v === '') ? '' : (string) $v;

                    $line = (float) $unitCost * $qty;
                    $entry = new ItemLedgerEntry();
                    $entry->posting_date        = $returnDate;
                    $entry->document_type       = 'Purchase Return';
                    $entry->document_no         = $docNo;                 // SAME as original GRN no
                    $entry->source_no           = '';                     // left empty per requirement
                    $entry->source_id           = '';
                    $entry->source_table        = 'Purchase Lines';       // per requirement
                    $entry->product_id          = $productId;
                    $entry->barcode             = $s($h->barcode);
                    $entry->item_code           = $s($h->item_code);
                    $entry->name                = $s($h->name);
                    $entry->variant             = $s($h->variant);
                    $entry->description         = $s($h->description ?? ''); // item description carried over
                    $entry->unit                = $s($h->unit);
                    $entry->category_name       = $s($h->category_name);
                    $entry->type                = $s($h->type);
                    $entry->warehouse_id        = $warehouseId;
                    $entry->warehouse_name      = $s($h->warehouse_name);
                    $entry->lot                 = $s($lot);                // '' not null when no lot
                    $entry->expire_date         = $h->expire_date;        // leave as-is (date or null col)
                    $entry->quantity            = (-1) * $qty;                   // NEGATIVE — stock-out
                    $entry->remaining_quantity  = 0;                      // a return holds no stock
                    $entry->entry_type          = $this->negative;        // still a stock-OUT type
                    $entry->unit_cost           = $unitCost;              // editable price
                    // Inventory value of the stock going back to the vendor.
                    // Always positive — the direction is carried by quantity /
                    // entry_type, not by the sign of the value.
                    $entry->cost_amount         = round(abs($qty) * abs((float) $unitCost), 6);
                    $entry->unit_price          = (float) ($h->unit_price ?? 0);
                    $entry->sell_price          = (float) ($h->sell_price ?? 0);
                    $entry->line_amount         = $line;                  // POSITIVE — cost recovered
                    $entry->net_amount          = $line;
                    $entry->grand_total_amount  = $line;
                    $entry->currency_name       = $s($h->currency_name);
                    $entry->factor              = $factor;
                    $entry->vendor_id           = $h->vendor_id;          // keep id (FK) as-is
                    $entry->vendor_name         = $s($h->vendor_name);
                    $entry->payment_method      = $s($h->payment_method);
                    $entry->remark              = $reason;                // user-entered return reason
                    $entry->created_by          = Auth::user()->name ?? 'system';
                    $entry->created_user_id     = Auth::id();
                    $entry->save();

                    // entry_no = the row id (per requirement)
                    if (DB::getSchemaBuilder()->hasColumn('item_ledger_entries', 'entry_no')) {
                        $entry->entry_no = (string) $entry->id;
                        $entry->save();
                    }

                    // ---- 4) add a NEW NEGATIVE row to PurchaseLine ----
                    //   Per requirement: negative qty + negative line_amount on the
                    //   purchase line, linked to the GRN via document_no.
                    //   Schema: document_no, product_id, barcode, item_code, name,
                    //   variant, description, quantity, unit, lot, expire_date,
                    //   category_name, unit_cost, line_amount, remark, created_by.
                    $pl = new PurchaseLine();
                    $pl->document_no   = $docNo;                 // GRN no (relation key)
                    $pl->product_id    = $productId;
                    $pl->barcode       = $s($h->barcode);
                    $pl->item_code     = $s($h->item_code);
                    $pl->name          = $s($h->name) ?: 'Item';  // name is NOT NULL
                    $pl->variant       = $s($h->variant);
                    $pl->description   = $s($h->description ?? '');
                    $pl->quantity      = -$qty;                  // NEGATIVE on the purchase line
                    $pl->unit          = $s($h->unit);
                    $pl->lot           = $s($lot);
                    $pl->expire_date   = $h->expire_date;
                    $pl->category_name = $s($h->category_name);
                    $pl->unit_cost     = $unitCost;              // editable price
                    $pl->line_amount   = -$line;                 // NEGATIVE line value
                    $pl->remark        = $reason;
                    $pl->created_by    = Auth::user()->name ?? 'system';
                    $pl->save();

                    $created[] = ['name' => $h->name, 'lot' => $lot, 'qty' => $qty, 'warehouse' => $h->warehouse_name];
                }

                if (empty($created)) {
                    throw new \RuntimeException('No valid lines to return.');
                }

                return ['document_no' => $docNo, 'lines' => $created];
            });

            return response()->json([
                'success'     => true,
                'message'     => 'Purchase Return created for ' . $result['document_no'] . '.',
                'document_no' => $result['document_no'],
                'lines'       => $result['lines'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
