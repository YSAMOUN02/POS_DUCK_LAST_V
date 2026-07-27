<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ItemLedgerEntry;
use App\Models\Product;
use App\Models\PurchaseLine;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function list_warehouse(Request $request)
    {
        $isManager = in_array(Auth::user()->role, ['admin', 'supervisor']);

        $query = Warehouse::select('id', 'name', 'location', 'status', 'created_by')
            ->withSum('products as total_stock', 'warehouse_product.quantity');

        if (Auth::user()->role == 'admin') {
            $query->addSelect('note');
        }

        if (!($request->query('scope') === 'all' && $isManager)) {
            $warehouse_ids = Auth::user()->warehouses->pluck('id');
            $query->whereIn('id', $warehouse_ids);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        $data = [
            'name' => $request->name,
            'location' => $request->location,
            'status' => 1,
            'created_by' => Auth::user()->name,
        ];

        if (Auth::user()->role == 'admin') {
            $data['note'] = $request->note;
        }

        $warehouse = Warehouse::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse created successfully',
            'data' => $warehouse,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Validate the input
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        try {
            $warehouse = Warehouse::findOrFail($id);

            $data = [
                'name' => $request->name,
                'location' => $request->location,
            ];

            if (Auth::user()->role == 'admin') {
                $data['note'] = $request->note;
            }

            $warehouse->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function toggleStatus($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->status = !$warehouse->status;
        $warehouse->save();

        return response()->json([
            'success' => true,
            'status' => $warehouse->status,
            'message' => $warehouse->status ? 'Warehouse enabled' : 'Warehouse disabled',
        ]);
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        if ($warehouse->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: this warehouse still has stock. Transfer or clear stock first.',
            ], 422);
        }

        DB::table('user_warehouse')->where('warehouse_id', $id)->delete();
        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully',
        ]);
    }
    public function getStock(Request $request, $warehouseId)
    {
        if ($request->query('view') === 'summary') {
            return $this->getStockSummary($request, $warehouseId);
        }

        $limit = $request->query('limit', 50);



        $query = \DB::table('warehouse_product')
            ->join('product', 'product.id', '=', 'warehouse_product.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_product.warehouse_id')
            ->leftJoin('categories', 'categories.id', '=', 'product.category_id')
            ->leftJoin('bins', 'bins.id', '=', 'warehouse_product.bin_id')
            ->orderBy('warehouses.id')                 // priority 1
            ->orderBy('warehouse_product.id')          // priority 2
            ->select(
                'warehouse_product.id as lot_id',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'warehouse_product.bin_id',
                'bins.name as bin_name',
                'product.id as product_id',
                'product.name as product_name',
                'product.code',
                'product.variant',
                'product.description',
                'warehouse_product.quantity',
                'warehouse_product.track_lot',
                'warehouse_product.lot',
                'warehouse_product.expire',
                'product.unit',
                'product.cost as cost_price',
                'product.vat',
                'product.sell_price',
                'product.status',
                'product.min_stock',
                'product.max_stock',
                'categories.name as category_name'
            );
        // 🔥 Apply filters FIRST

        if ($warehouseId != 0) {
            $query->where('warehouse_product.warehouse_id', $warehouseId);
        } else {
            $warehouse_ids = Auth::user()->warehouses->pluck('id');
            $query->whereIn('warehouse_product.warehouse_id', $warehouse_ids);
        }

        if ($request->filled('product_id')) {
            $query->where('warehouse_product.product_id', $request->product_id);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(product.name) LIKE ?', ["%$search%"])
                    ->orWhereRaw('LOWER(product.code) LIKE ?', ["%$search%"]);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('product.category_id', $request->category_id);
        }

        // 🔥 fix ALL status
        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('product.status', (int)$request->status);
        }

        if ($request->filled('stock') && $request->stock !== 'All') {
            if ($request->stock === 'has') {
                $query->where('warehouse_product.quantity', '>', 0);
            } else {
                $query->where('warehouse_product.quantity', '<=', 0);
            }
        }
        // 🔥 EXECUTE QUERY LAST (VERY IMPORTANT)

        if ($limit === 'All') {
            $products = $query->get();

            $products = $products->map(function ($p) {
                $vat = (float) $p->vat;
                $sellPrice = (float) $p->sell_price;

                return [
                    'lot_id' => $p->lot_id,
                    'warehouse_id'   => $p->warehouse_id,
                    'warehouse_name' => $p->warehouse_name,
                    'bin_id'         => $p->bin_id,
                    'bin_name'       => $p->bin_name,
                    'product_id'     => $p->product_id,
                    'product_name'   => $p->product_name,
                    'code'           => $p->code,
                    'variant'        => $p->variant,
                    'description'    => $p->description,
                    'lot'            => $p->track_lot ? $p->lot : null,
                    'expire'         => $p->track_lot ? $p->expire : null,
                    'quantity'            => $p->quantity,
                    'unit'           => $p->unit,
                    'cost_price'     => (float) $p->cost_price,
                    'vat'            => $vat,
                    'sell_price'     => $sellPrice,
                    'sell_price_vat' => $sellPrice * (1 + ($vat / 100)),
                    'status'         => (int) $p->status,
                    'min_stock'      => $p->min_stock,
                    'max_stock'      => $p->max_stock,
                    'category_name'  => $p->category_name
                ];
            });

            return response()->json([
                'data' => $products,
                'current_page' => 1,
                'per_page' => 'All',
                'total' => $products->count()
            ]);
        }

        // 🔥 SAFE paginate
        $perPage = max((int)$limit, 1);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(function ($p) {
            $vat = (float) $p->vat;
            $sellPrice = (float) $p->sell_price;

            return [
                'lot_id' => $p->lot_id,
                'warehouse_id'   => $p->warehouse_id,
                'warehouse_name' => $p->warehouse_name,
                'bin_id'         => $p->bin_id,
                'bin_name'       => $p->bin_name,
                'product_id'     => $p->product_id,
                'product_name'   => $p->product_name,
                'code'           => $p->code,
                'variant'        => $p->variant,
                'description'    => $p->description,
                'lot'            => $p->track_lot ? $p->lot : null,
                'expire'         => $p->track_lot ? $p->expire : null,
                'quantity'            => $p->quantity,
                'unit'           => $p->unit,
                'cost_price'     => (float) $p->cost_price,
                'vat'            => $vat,
                'sell_price'     => $sellPrice,
                'sell_price_vat' => $sellPrice * (1 + ($vat / 100)),
                'status'         => (int) $p->status,
                'min_stock'      => $p->min_stock,
                'max_stock'      => $p->max_stock,
                'category_name'  => $p->category_name
            ];
        });

        return response()->json($products);
    }

    /**
     * One row per product — total quantity summed across every warehouse
     * (or a single warehouse if $warehouseId is not 0), plus how many
     * distinct warehouses/lot rows make up that total. Same filters as
     * getStock(), evaluated against the summed quantity for the "stock"
     * (in/out) filter.
     */
    private function getStockSummary(Request $request, $warehouseId)
    {
        $limit = $request->query('limit', 50);

        $query = \DB::table('warehouse_product')
            ->join('product', 'product.id', '=', 'warehouse_product.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'product.category_id')
            ->groupBy(
                'product.id',
                'product.name',
                'product.code',
                'product.unit',
                'product.status',
                'categories.name'
            )
            ->orderBy('product.name')
            ->select(
                'product.id as product_id',
                'product.name as product_name',
                'product.code',
                'product.unit',
                'product.status',
                'categories.name as category_name',
                \DB::raw('SUM(warehouse_product.quantity) as total_quantity'),
                \DB::raw('COUNT(DISTINCT warehouse_product.warehouse_id) as warehouse_count'),
                \DB::raw('COUNT(*) as lot_count')
            );

        if ($warehouseId != 0) {
            $query->where('warehouse_product.warehouse_id', $warehouseId);
        } else {
            $warehouse_ids = Auth::user()->warehouses->pluck('id');
            $query->whereIn('warehouse_product.warehouse_id', $warehouse_ids);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(product.name) LIKE ?', ["%$search%"])
                    ->orWhereRaw('LOWER(product.code) LIKE ?', ["%$search%"]);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('product.category_id', $request->category_id);
        }

        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('product.status', (int) $request->status);
        }

        if ($request->filled('stock') && $request->stock !== 'All') {
            if ($request->stock === 'has') {
                $query->having('total_quantity', '>', 0);
            } else {
                $query->having('total_quantity', '<=', 0);
            }
        }

        $map = fn($p) => [
            'product_id'      => $p->product_id,
            'product_name'    => $p->product_name,
            'code'            => $p->code,
            'unit'            => $p->unit,
            'status'          => (int) $p->status,
            'category_name'   => $p->category_name,
            'total_quantity'  => (float) $p->total_quantity,
            'warehouse_count' => (int) $p->warehouse_count,
            'lot_count'       => (int) $p->lot_count,
        ];

        if ($limit === 'All') {
            $rows = $query->get()->map($map);

            return response()->json([
                'data' => $rows,
                'current_page' => 1,
                'per_page' => 'All',
                'total' => $rows->count(),
            ]);
        }

        $perPage = max((int) $limit, 1);
        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform($map);

        return response()->json($rows);
    }
    public function getCategories()
    {
        $category = Category::orderby('id', 'desc')->select('id', 'name')->get();
        return response()->json($category);
    }

    public function getLotData($product_id)
    {
        $warehouse_ids = Auth::user()->warehouses->pluck('id');

        $lots = WarehouseProduct::with(['warehouse:id,name', 'bin:id,name'])
            ->whereIn('warehouse_id', $warehouse_ids)
            ->where('product_id', $product_id)
            ->where('quantity', '>', 0)
            ->orderBy('expire', 'asc')
            ->get(['id', 'warehouse_id', 'bin_id', 'lot', 'quantity', 'expire'])
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'warehouse_id' => $item->warehouse_id,
                    'lot' => $item->lot,
                    'quantity' => $item->quantity,
                    'expire' => $item->expire,
                    'warehouse_name' => $item->warehouse->name ?? null,
                    'bin_name' => $item->bin->name ?? null,
                ];
            });

        return response()->json($lots);
    }

    public function transfer(Request $request)
    {
     
        $request->validate([
            'wh_product_id' => 'required|integer|exists:warehouse_product,id',
            'warehouse_id'  => 'required|integer|exists:warehouses,id',
            'bin_id'        => 'nullable|integer|exists:bins,id',
            'quantity'           => 'required|numeric|min:0.000001',
        ]);

        DB::beginTransaction();

        try {
            $row = WarehouseProduct::where('id', $request->wh_product_id)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Stock row not found',
                ], 404);
            }

            $transferQty = round((float) $request->quantity, 6);
            $currentQty    = round((float) $row->quantity, 6);
            $toWarehouseId = (int) $request->warehouse_id;
            $destBinId = $request->filled('bin_id') ? (int) $request->bin_id : null;

            if ($destBinId && !\App\Models\Bin::where('id', $destBinId)->where('warehouse_id', $toWarehouseId)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Selected bin does not belong to the destination warehouse',
                ], 422);
            }

            if ($transferQty > $currentQty) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer Quantity cannot be greater than stock',
                ], 422);
            }

            $sameWarehouse = (int) $row->warehouse_id === $toWarehouseId;
            $sameBin = (int) ($row->bin_id ?? 0) === (int) ($destBinId ?? 0);

            if ($sameWarehouse && $sameBin) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Destination must be a different warehouse or bin than the source',
                ], 422);
            }

            // Same endpoint serves two distinct permissions depending on the
            // destination: moving stock to a different warehouse ("transfer")
            // vs. moving it between bins inside the same warehouse
            // ("movement") — decide which one this request actually is and
            // check that specific permission.
            $requiredPermission = $sameWarehouse ? 'warehouse.movement' : 'warehouse.transfer';
            if (!Auth::user()->hasPermission($requiredPermission)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $sameWarehouse
                        ? 'You do not have permission to move stock between bins.'
                        : 'You do not have permission to transfer stock to another warehouse.',
                ], 403);
            }

            $product = Product::with('category')->find($row->product_id);
            $fromWarehouse = Warehouse::find($row->warehouse_id);
            $toWarehouse = Warehouse::find($toWarehouseId);

            if (!$product || !$fromWarehouse || !$toWarehouse) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Product or warehouse not found',
                ], 404);
            }

            $oldRowId       = $row->id;
            $oldWarehouseId = $row->warehouse_id;
            $oldBinId       = $row->bin_id;
            $oldLot         = $row->lot ?: null;
            $oldExpire      = $row->expire ?: null;
            $oldTrackLot    = $row->track_lot;
            $oldControlExp  = $row->control_exp;

            $sourceBinName = $oldBinId ? optional(\App\Models\Bin::find($oldBinId))->name : null;
            $destBinName = $destBinId ? optional(\App\Models\Bin::find($destBinId))->name : null;

            // Transfer No
            $year = now()->format('y');
            $prefix = 'TO' . $year . '-';

            $lastTransfer = ItemLedgerEntry::whereIn('document_type', [
                'Transfer Shipment',
                'Transfer Receipt',
            ])
                ->where('document_no', 'like', $prefix . '%')
                ->orderByDesc('id')
                ->first();

            $nextNumber = $lastTransfer
                ? ((int) substr($lastTransfer->document_no, strlen($prefix))) + 1
                : 1;

            $transferNo = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Target row
            $targetRow = WarehouseProduct::where('product_id', $product->id)
                ->where('warehouse_id', $toWarehouseId)
                ->when(!is_null($oldLot), fn($q) => $q->where('lot', $oldLot), fn($q) => $q->whereNull('lot'))
                ->when(!is_null($oldExpire), fn($q) => $q->whereDate('expire', $oldExpire), fn($q) => $q->whereNull('expire'))
                ->when($destBinId !== null, fn($q) => $q->where('bin_id', $destBinId), fn($q) => $q->whereNull('bin_id'))
                ->lockForUpdate()
                ->first();

            $purchaseLine = PurchaseLine::where('product_id', $product->id)
                ->when(!is_null($oldLot), fn($q) => $q->where('lot', $oldLot), fn($q) => $q->whereNull('lot'))
                ->when(!is_null($oldExpire), fn($q) => $q->whereDate('expire_date', $oldExpire), fn($q) => $q->whereNull('expire_date'))
                ->latest('id')
                ->first();

            $unitCost = $purchaseLine->unit_cost ?? $product->cost ?? 0;

            // Consume FIFO positive ledger from source warehouse
            $this->consumePositiveLedgerRemaining(
                $product->id,
                $oldWarehouseId,
                $oldLot,
                $oldExpire,
                $transferQty
            );

            // Add stock to target
            if ($targetRow) {
                $targetRow->quantity = round((float) $targetRow->quantity + $transferQty, 6);
                // do NOT increase original_qty on transfer
                $targetRow->save();
            } else {
                $targetRow = WarehouseProduct::create([
                    'product_id'    => $product->id,
                    'warehouse_id'  => $toWarehouseId,
                    'bin_id'        => $destBinId,
                    'original_quantity'  => 0,
                    'quantity'           => $transferQty,
                    'track_lot'     => $oldTrackLot,
                    'lot'           => $oldLot,
                    'expire'        => $oldExpire,
                    'control_exp'   => $oldControlExp,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // Subtract stock from source
            // Subtract stock from source
            $newQty = round($currentQty - $transferQty, 6);

            if ($newQty < 0) {

                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Transfer qty cannot be greater than stock',
                ], 422);
            }

            $row->quantity = $newQty;
            $row->save();

            // Ledger OUT
            $itemLedgerOut = ItemLedgerEntry::create([
                'posting_date'       => now()->toDateString(),
                'document_type'      => 'Transfer Shipment',
                'document_no'        => $transferNo,
                'source_id'          => $oldRowId,
                'source_table'       => 'warehouse_product',

                'product_id'         => $product->id,
                'barcode'            => $product->bar_code,
                'item_code'          => $product->code,
                'name'               => $product->name,
                'variant'            => $product->variant,
                'description'        => $product->description,
                'unit'               => $product->unit ?? 'NA',
                'category_name'      => optional($product->category)->name,
                'type'               => 'product',

                'warehouse_id'       => $oldWarehouseId,
                'warehouse_name'     => $fromWarehouse->name,
                'bin_id'             => $oldBinId,
                'bin_name'           => $sourceBinName,
                'lot'                => $oldLot,
                'expire_date'        => $oldExpire,

                'quantity'           => -1 * $transferQty,
                'remaining_quantity' => 0,
                'entry_type'         => 'negative',

                'unit_cost'          => $unitCost,
                'unit_price'         => $product->sell_price ?? 0,
                'sell_price'         => $product->sell_price ?? 0,

                'discount_percent'   => 0,
                'discount_amount'    => 0,
                'vat'                => $product->vat ?? 0,
                'vat_amount'         => 0,
                'line_amount'        => 0,
                'net_amount'         => 0,
                'grand_total_amount' => 0,

                'customer_id'        => null,
                'customer_name'      => null,
                'customer_phone'     => null,
                'customer_address'   => null,
                'payment_method'     => null,

                'remark'             => 'Transfer OUT to ' . $toWarehouse->name . ($destBinName ? ' / ' . $destBinName : ''),
                'created_by'         => Auth::user()->name ?? 'System',
            ]);

            $itemLedgerOut->update([
                'entry_no' => $itemLedgerOut->id,
            ]);

            // Ledger IN
            $itemLedgerIn = ItemLedgerEntry::create([
                'posting_date'       => now()->toDateString(),
                'document_type'      => 'Transfer Receipt',
                'document_no'        => $transferNo,
                'source_id'          => $targetRow->id,
                'source_table'       => 'warehouse_product',

                'product_id'         => $product->id,
                'barcode'            => $product->bar_code,
                'item_code'          => $product->code,
                'name'               => $product->name,
                'variant'            => $product->variant,
                'description'        => $product->description,
                'unit'               => $product->unit ?? 'NA',
                'category_name'      => optional($product->category)->name,
                'type'               => 'product',

                'warehouse_id'       => $toWarehouse->id,
                'warehouse_name'     => $toWarehouse->name,
                'bin_id'             => $destBinId,
                'bin_name'           => $destBinName,
                'lot'                => $oldLot,
                'expire_date'        => $oldExpire,

                'quantity'           => $transferQty,
                'remaining_quantity' => $transferQty,
                'entry_type'         => 'positive',

                'unit_cost'          => $unitCost,
                'unit_price'         => $product->sell_price ?? 0,
                'sell_price'         => $product->sell_price ?? 0,

                'discount_percent'   => 0,
                'discount_amount'    => 0,
                'vat'                => $product->vat ?? 0,
                'vat_amount'         => 0,
                'line_amount'        => 0,
                'net_amount'         => 0,
                'grand_total_amount' => 0,

                'customer_id'        => null,
                'customer_name'      => null,
                'customer_phone'     => null,
                'customer_address'   => null,
                'payment_method'     => null,

                'remark'             => 'Transfer IN from ' . $fromWarehouse->name . ($sourceBinName ? ' / ' . $sourceBinName : ''),
                'created_by'         => Auth::user()->name ?? 'System',
            ]);

            $itemLedgerIn->update([
                'entry_no' => $itemLedgerIn->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transferred successfully',
                'document_no' => $transferNo,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Quick transfer without picking a specific lot: pulls from the source
     * warehouse's lots oldest-expiry-first (FEFO) until the requested
     * quantity is satisfied, splitting across multiple lots if needed.
     */
    public function transferFefo(Request $request)
    {
        $request->validate([
            // table is `product` (singular) — see App\Models\Product::$table.
            // `exists:products,id` raised "Table 'products' doesn't exist" from
            // inside the validator, before the try/catch, so every FEFO
            // transfer returned a 500.
            'product_id'         => 'required|integer|exists:product,id',
            'from_warehouse_id'  => 'required|integer|exists:warehouses,id',
            'warehouse_id'       => 'required|integer|exists:warehouses,id',
            'bin_id'             => 'nullable|integer|exists:bins,id',
            'quantity'           => 'required|numeric|min:0.000001',
        ]);

        DB::beginTransaction();

        try {
            $productId       = (int) $request->product_id;
            $fromWarehouseId = (int) $request->from_warehouse_id;
            $toWarehouseId   = (int) $request->warehouse_id;
            $destBinId       = $request->filled('bin_id') ? (int) $request->bin_id : null;
            $remainingQty    = round((float) $request->quantity, 6);

            if ($fromWarehouseId === $toWarehouseId) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Source and destination warehouse must be different',
                ], 422);
            }

            // Always cross-warehouse (guarded above) — never a same-warehouse
            // bin movement — so this is always "transfer", never "movement".
            if (!Auth::user()->hasPermission('warehouse.transfer')) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to transfer stock to another warehouse.',
                ], 403);
            }

            if ($destBinId && !\App\Models\Bin::where('id', $destBinId)->where('warehouse_id', $toWarehouseId)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Selected bin does not belong to the destination warehouse',
                ], 422);
            }

            $product = Product::with('category')->find($productId);
            $fromWarehouse = Warehouse::find($fromWarehouseId);
            $toWarehouse = Warehouse::find($toWarehouseId);

            if (!$product || !$fromWarehouse || !$toWarehouse) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Product or warehouse not found',
                ], 404);
            }

            // FEFO order: soonest-expiring lots first, lots with no expiry last
            $lots = WarehouseProduct::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->where('quantity', '>', 0)
                ->orderByRaw('expire IS NULL, expire ASC')
                ->lockForUpdate()
                ->get();

            $totalAvailable = round((float) $lots->sum('quantity'), 6);

            if ($remainingQty > $totalAvailable) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer qty cannot be greater than total stock in source warehouse',
                ], 422);
            }

            $year = now()->format('y');
            $prefix = 'TO' . $year . '-';

            $lastTransfer = ItemLedgerEntry::whereIn('document_type', [
                'Transfer Shipment',
                'Transfer Receipt',
            ])
                ->where('document_no', 'like', $prefix . '%')
                ->orderByDesc('id')
                ->first();

            $nextNumber = $lastTransfer
                ? ((int) substr($lastTransfer->document_no, strlen($prefix))) + 1
                : 1;

            $transferNo = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $lotsMoved = 0;

            foreach ($lots as $row) {
                if ($remainingQty <= 0) {
                    break;
                }

                $qtyFromThisLot = min($remainingQty, round((float) $row->quantity, 6));

                $this->moveLotToWarehouse($row, $product, $fromWarehouse, $toWarehouse, $destBinId, $qtyFromThisLot, $transferNo);

                $remainingQty = round($remainingQty - $qtyFromThisLot, 6);
                $lotsMoved++;
            }

            if ($remainingQty > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock across available lots',
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Stock transferred successfully across {$lotsMoved} lot(s)",
                'document_no' => $transferNo,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Moves quantity from one existing warehouse_product row (a single lot)
     * into the destination warehouse/bin, writing the same Transfer Shipment
     * / Transfer Receipt ledger pair that the single-lot transfer() creates.
     * Assumes it's called inside an open DB transaction.
     */
    private function moveLotToWarehouse($row, $product, $fromWarehouse, $toWarehouse, $destBinId, $transferQty, $transferNo)
    {
        $oldRowId       = $row->id;
        $oldWarehouseId = $row->warehouse_id;
        $oldBinId       = $row->bin_id;
        $oldLot         = $row->lot ?: null;
        $oldExpire      = $row->expire ?: null;
        $oldTrackLot    = $row->track_lot;
        $oldControlExp  = $row->control_exp;

        $sourceBinName = $oldBinId ? optional(\App\Models\Bin::find($oldBinId))->name : null;
        $destBinName = $destBinId ? optional(\App\Models\Bin::find($destBinId))->name : null;

        $targetRow = WarehouseProduct::where('product_id', $product->id)
            ->where('warehouse_id', $toWarehouse->id)
            ->when(!is_null($oldLot), fn($q) => $q->where('lot', $oldLot), fn($q) => $q->whereNull('lot'))
            ->when(!is_null($oldExpire), fn($q) => $q->whereDate('expire', $oldExpire), fn($q) => $q->whereNull('expire'))
            ->when($destBinId !== null, fn($q) => $q->where('bin_id', $destBinId), fn($q) => $q->whereNull('bin_id'))
            ->lockForUpdate()
            ->first();

        $purchaseLine = PurchaseLine::where('product_id', $product->id)
            ->when(!is_null($oldLot), fn($q) => $q->where('lot', $oldLot), fn($q) => $q->whereNull('lot'))
            ->when(!is_null($oldExpire), fn($q) => $q->whereDate('expire_date', $oldExpire), fn($q) => $q->whereNull('expire_date'))
            ->latest('id')
            ->first();

        $unitCost = $purchaseLine->unit_cost ?? $product->cost ?? 0;

        // Consume FIFO positive ledger from source warehouse
        $this->consumePositiveLedgerRemaining(
            $product->id,
            $oldWarehouseId,
            $oldLot,
            $oldExpire,
            $transferQty
        );

        if ($targetRow) {
            $targetRow->quantity = round((float) $targetRow->quantity + $transferQty, 6);
            $targetRow->save();
        } else {
            $targetRow = WarehouseProduct::create([
                'product_id'        => $product->id,
                'warehouse_id'      => $toWarehouse->id,
                'bin_id'            => $destBinId,
                'original_quantity' => 0,
                'quantity'          => $transferQty,
                'track_lot'         => $oldTrackLot,
                'lot'               => $oldLot,
                'expire'            => $oldExpire,
                'control_exp'       => $oldControlExp,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        $newQty = round((float) $row->quantity - $transferQty, 6);

        if ($newQty < 0) {
            throw new \RuntimeException('Transfer qty cannot be greater than stock');
        }

        $row->quantity = $newQty;
        $row->save();

        $itemLedgerOut = ItemLedgerEntry::create([
            'posting_date'       => now()->toDateString(),
            'document_type'      => 'Transfer Shipment',
            'document_no'        => $transferNo,
            'source_id'          => $oldRowId,
            'source_table'       => 'warehouse_product',

            'product_id'         => $product->id,
            'barcode'            => $product->bar_code,
            'item_code'          => $product->code,
            'name'               => $product->name,
            'variant'            => $product->variant,
            'description'        => $product->description,
            'unit'               => $product->unit ?? 'NA',
            'category_name'      => optional($product->category)->name,
            'type'               => 'product',

            'warehouse_id'       => $oldWarehouseId,
            'warehouse_name'     => $fromWarehouse->name,
            'bin_id'             => $oldBinId,
            'bin_name'           => $sourceBinName,
            'lot'                => $oldLot,
            'expire_date'        => $oldExpire,

            'quantity'           => -1 * $transferQty,
            'remaining_quantity' => 0,
            'entry_type'         => 'negative',

            'unit_cost'          => $unitCost,
            'unit_price'         => $product->sell_price ?? 0,
            'sell_price'         => $product->sell_price ?? 0,

            'discount_percent'   => 0,
            'discount_amount'    => 0,
            'vat'                => $product->vat ?? 0,
            'vat_amount'         => 0,
            'line_amount'        => 0,
            'net_amount'         => 0,
            'grand_total_amount' => 0,

            'customer_id'        => null,
            'customer_name'      => null,
            'customer_phone'     => null,
            'customer_address'   => null,
            'payment_method'     => null,

            'remark'             => 'Transfer OUT to ' . $toWarehouse->name . ($destBinName ? ' / ' . $destBinName : '') . ' (FEFO)',
            'created_by'         => Auth::user()->name ?? 'System',
        ]);

        $itemLedgerOut->update(['entry_no' => $itemLedgerOut->id]);

        $itemLedgerIn = ItemLedgerEntry::create([
            'posting_date'       => now()->toDateString(),
            'document_type'      => 'Transfer Receipt',
            'document_no'        => $transferNo,
            'source_id'          => $targetRow->id,
            'source_table'       => 'warehouse_product',

            'product_id'         => $product->id,
            'barcode'            => $product->bar_code,
            'item_code'          => $product->code,
            'name'               => $product->name,
            'variant'            => $product->variant,
            'description'        => $product->description,
            'unit'               => $product->unit ?? 'NA',
            'category_name'      => optional($product->category)->name,
            'type'               => 'product',

            'warehouse_id'       => $toWarehouse->id,
            'warehouse_name'     => $toWarehouse->name,
            'bin_id'             => $destBinId,
            'bin_name'           => $destBinName,
            'lot'                => $oldLot,
            'expire_date'        => $oldExpire,

            'quantity'           => $transferQty,
            'remaining_quantity' => $transferQty,
            'entry_type'         => 'positive',

            'unit_cost'          => $unitCost,
            'unit_price'         => $product->sell_price ?? 0,
            'sell_price'         => $product->sell_price ?? 0,

            'discount_percent'   => 0,
            'discount_amount'    => 0,
            'vat'                => $product->vat ?? 0,
            'vat_amount'         => 0,
            'line_amount'        => 0,
            'net_amount'         => 0,
            'grand_total_amount' => 0,

            'customer_id'        => null,
            'customer_name'      => null,
            'customer_phone'     => null,
            'customer_address'   => null,
            'payment_method'     => null,

            'remark'             => 'Transfer IN from ' . $fromWarehouse->name . ($sourceBinName ? ' / ' . $sourceBinName : '') . ' (FEFO)',
            'created_by'         => Auth::user()->name ?? 'System',
        ]);

        $itemLedgerIn->update(['entry_no' => $itemLedgerIn->id]);
    }

    private function getLotUnitCost($productId, $lot = null, $warehouseId = null)
    {
        $lot = !is_null($lot) && trim($lot) !== '' ? trim($lot) : null;

        $purchaseEntry = ItemLedgerEntry::where('product_id', $productId)
            ->where('document_type', 'Purchase')
            ->where('entry_type', 'positive')
            ->when(!is_null($lot), fn($q) => $q->where('lot', $lot), fn($q) => $q->whereNull('lot'))
         
            ->latest('id')
            ->first();

        return (float) ($purchaseEntry->unit_cost ?? 0);
    }

    private function consumePositiveLedgerRemaining($productId, $warehouseId, $lot = null, $expire = null, $qty = 0)
    {
        $qtyToConsume = round((float) $qty, 6);

        $positiveEntries = ItemLedgerEntry::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('entry_type', 'positive')
            ->where('remaining_quantity', '>', 0)
            ->when(!is_null($lot), fn($q) => $q->where('lot', $lot), fn($q) => $q->whereNull('lot'))
            ->when(!is_null($expire), fn($q) => $q->whereDate('expire_date', $expire), fn($q) => $q->whereNull('expire_date'))
            ->orderBy('id', 'asc') // FIFO
            ->lockForUpdate()
            ->get();

        foreach ($positiveEntries as $entry) {
            if ($qtyToConsume <= 0) {
                break;
            }

            $available = round((float) $entry->remaining_quantity, 6);
            $consume = min($available, $qtyToConsume);

            $entry->remaining_quantity = round($available - $consume, 6);
            $entry->save();

            $qtyToConsume = round($qtyToConsume - $consume, 6);
        }

        if ($qtyToConsume > 0.000001) {
            throw new \Exception('Not enough positive ledger remaining quantity.');
        }
    }
}
