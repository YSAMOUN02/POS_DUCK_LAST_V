<?php

namespace App\Livewire;

use App\Concerns\ResolvesPrintProfile;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use App\Models\ItemLedgerEntry;
use App\Models\Product;

use App\Models\SaleOrderHeader;
use App\Models\SaleOrderLine;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Serial_No;
use App\Models\Category;
use App\Models\TableQueue;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

use Livewire\Component;

class Cart extends Component
{
    use ResolvesPrintProfile;




    public $cart = [];
    public $cart_queue_no = 0;
    public $new_cart = true;
    public $old_queue_no = 0;
    public $qty = 0;
    public $count_cart = 0;
    public $currency = 'USD';
    public $currency_name = '$';
    public $factor = 1; // Conversion factor
    public $all_currency;
    public $priceInputNonce = 0; // bumped on every recalcLine() call to force the price input to remount from server state




    public $customer_name = 'Walk-in Customer';
    public $customer_id = null;
    public $customer_phone = '';
    public $customer_address1 = '';
    public $customer_address2 = '';
    public $customer_contact_name = '';
    public $customer_contact_phone = '';
    public $customer_city = '';
    public $customer_discount_percent = 0;

    public $vat_status = 0;
    public $cart_mode = 'normal';

    /**
     * Warehouse this sale draws stock from.
     *
     * A sale used to deduct FEFO across EVERY warehouse the user could reach, so
     * one invoice could quietly pull three units from one site and two from
     * another. One document now belongs to one warehouse.
     *
     * Only offered when the user actually has a choice; with a single warehouse
     * it is selected silently and no control is shown.
     */
    public $sale_warehouse_id = '';

    /** id => name, limited to warehouses usable for selling. */
    public $saleWarehouses = [];
    public $openIndex = null;

    // Sale Order Info
    public $document_no = 'NA';
    // #[Locked] — the browser must not be able to repoint this at another
    // document. updateDepositSaleOrder() and shipSaleOrder() act on whatever id
    // it holds, so while it was writable a cashier could take payment on, or
    // ship, any sale order in the system. Not bound to any wire:model input.
    #[Locked]
    public $document_id = 0;
    public $document_type = 'NA';
    public $deposit = 0;
    public $balanceAmount_display = 0;



    public function toggleItem($index)
    {
        $this->openIndex = $this->openIndex === $index ? null : $index;
    }

    #[\Livewire\Attributes\On('set-item-lots')]
    public function setItemLots($index = null, $lots = [])
    {
        $this->cart[$index]['lots'] = $lots;
    }
    public function viewLots($index)
    {
        $lotsInCart = $this->cart[$index]['lots'] ?? [];
        $productId = $this->cart[$index]['id'] ?? null; // ✅ get product id

        // Get all warehouse products for these lot IDs
        $lotIds = collect($lotsInCart)->pluck('id')->toArray();
        $warehouseProducts = WarehouseProduct::with(['warehouse', 'bin'])->whereIn('id', $lotIds)->get()->keyBy('id');

        // Merge qty from cart with lot details
        $lotsToShow = collect($lotsInCart)->map(function ($lot) use ($warehouseProducts) {
            $wp = $warehouseProducts[$lot['id']] ?? null;

            return [
                'id' => $lot['id'],
                'quantity' => $lot['qty'],
                'lot' => $wp->lot ?? 'NO LOT',
                'expire' => $wp->expire
                    ? \Carbon\Carbon::parse($wp->expire)->format('d-M-Y')
                    : '-',
                'stock' => $wp->qty ?? 0,

                // ✅ ADD THIS
                'warehouse' => $wp?->warehouse?->name ?? 'NA',
                'bin' => $wp?->bin?->name ?? '-',
            ];
        })->toArray();

        // Dispatch to JS modal, include product_id
        $this->dispatch('view-cart-lots', [
            'lots' => $lotsToShow,
            'product_name' => $this->cart[$index]['name'],
            'product_id' => $productId, // ✅ added
        ]);
    }

    private function generateExpenseCode()
    {
        return Serial_No::next('expense');
    }
    #[\Livewire\Attributes\On('payment-expenses')]
    public function paymentExpenses($payload = [])
    {
        // Recording an expense moves money and was previously ungated — any user
        // who could open the POS could book one against the business.
        abort_unless(Auth::user()->hasPermission('expense.create'), 403);

        if (empty($this->cart)) {
            $this->dispatch('payment-error', ['message' => 'Cart is empty']);
            return;
        }


        DB::transaction(function () use ($payload) {
            $expenseCode = $this->generateExpenseCode();

            $riel = $this->getRielCurrency();
            foreach ($this->cart as $cartItem) {
                $qty = round((float) ($cartItem['qty'] ?? 1), 6);
                $unitPrice = round((float) ($cartItem['price'] ?? 0), 6);
                $amount = round($qty * $unitPrice, 6);

                Expense::create([
                    'expense_date'   => $payload['document_date'] ?? now()->toDateString(),
                    'product_id'     => $cartItem['id'] ?? null,
                    'expense_code'   => $expenseCode,
                    'expense_name'   => $cartItem['name'] ?? 'General Expense',
                    'qty'            => $qty,
                    'unit_price'     => $unitPrice,
                    'amount'         => $amount,
                    // dynamic Riel from currency table
                    'currency_name'  => $riel->code,
                    'factor'         => $riel->factor,
                    'payment_method' => $payload['paymentMethod'] ?? null,
                    'note'           => $cartItem['expence_for'] ?? null,
                    'status'         => 1,
                ]);
            }

            $this->cart_queue_no = 0;
            $this->getDocument($this->cart_queue_no);
        });

        $this->new_cart = true;
        $this->cart = [];
        $this->count_cart = 0;

        $this->dispatch('payment-success', [
            'message' => 'Expense saved successfully'
        ]);
    }

    private function createLedgerEntry(array $data)
    {
        $ledger = ItemLedgerEntry::create($data);

        $ledger->update([
            'entry_no' => 'ILE-' . str_pad($ledger->id, 8, '0', STR_PAD_LEFT)
        ]);

        return $ledger;
    }

    // Deducts stock and writes the invoice/ledger for a SaleOrder that was
    // created via confirmSaleOrder() ("Order" — never touched stock). A
    // deliberate standalone copy of the invoice/stock/ledger logic inside
    // confirmDepositSaleOrder() rather than a shared extraction — that method
    // is the live one-click checkout path and the risk of a refactor
    // subtly breaking it outweighs the duplication here. Reads already-saved
    // SaleOrderLine rows (not a live cart), so per-line lot/bin picks were
    // never captured — always falls back to FEFO-across-all-bins, same
    // fallback used everywhere else in the app when no lot is specified.
    // Caller is responsible for updating $saleOrder->status/payment fields —
    // this only ever touches stock + invoice + ledger.
    private function shipOrderStock(SaleOrderHeader $saleOrder): InvoiceHeader
    {
        $precision = 6;
        $r = fn($value) => round((float) $value, $precision);

        $lines = SaleOrderLine::where('sale_order_id', $saleOrder->id)->get();
        if ($lines->isEmpty()) {
            throw new \Exception('No lines to ship for ' . $saleOrder->document_no);
        }

        $riel = Currency::where('code', '៛')->firstOrFail();
        // One document, one warehouse — never FEFO across sites.
        $warehouse_ids = $this->saleWarehouseIds();

        $invoice = InvoiceHeader::create([
            'sale_order_id'    => $saleOrder->id,
            'invoice_number'   => $this->generateInvoiceNumber(),
            'source_no'        => $saleOrder->document_no,
            'invoice_date'     => now()->toDateString(),

            'customer_id'      => $saleOrder->customer_id,
            'contact_name'     => $saleOrder->contact_name,
            'phone'            => $saleOrder->phone,
            'address'          => $saleOrder->address,

            'total_amount'     => $saleOrder->total_amount,
            'vat_amount'       => $saleOrder->vat_amount,
            'discount_percent' => $saleOrder->discount_percent,
            'discount_amount'  => $saleOrder->discount_amount,
            'grand_total'      => $saleOrder->grand_total,

            'payment_method'   => $saleOrder->payment_method,
            'customer_type'    => $saleOrder->customer_type,
            'currency_name'    => $riel->code,
            'factor'           => $riel->factor,

            'created_by'       => Auth::user()->username ?? 'System',
            'created_user_id'  => Auth::id(),
            'remarks'          => $saleOrder->remarks,
        ]);

        $linesWritten = 0;
        foreach ($lines as $line) {
            $product = $this->requireProduct($line->product_id);
            $linesWritten++;

            $qty = $r($line->quantity);
            $sellPrice = $r($line->sell_price);
            $vatRate = $r($line->vat);
            $discountPercent = $r($line->discount_percent);
            $unitCost = $r($line->cost);
            $unitPrice = $r($line->unit_price);
            $lineAmount = $r($line->line_amount);
            $discountAmount = $r($line->discount_amount);
            $netAmount = $r($line->net_amount);
            $vatAmount = $r($line->vat_amount);
            $grandTotalLine = $r($line->grand_total_amount);
            $unit = $line->unit ?? ($product->unit ?? 'NA');

            InvoiceLine::create([
                'sale_invoice_id'    => $invoice->id,
                'document_no'        => $invoice->invoice_number,
                'product_id'         => $product->id,
                'barcode'            => $product->bar_code,
                'item_code'          => $product->code,
                'name'               => $product->name,
                'variant'            => $product->variant,
                'description'        => $product->description,

                'quantity'           => $qty,
                'unit'               => $unit,
                'category_name'      => optional($product->category)->name,

                'cost'               => $unitCost,
                'unit_price'         => $unitPrice,
                'sell_price'         => $sellPrice,

                'discount_percent'   => $discountPercent,
                'discount_amount'    => $discountAmount,
                'line_amount'        => $lineAmount,
                'vat'                => $vatRate,
                'vat_amount'         => $vatAmount,
                'net_amount'         => $netAmount,
                'grand_total_amount' => $grandTotalLine,

                'created_by'         => Auth::user()->username ?? 'System',
            ]);

            if ((int) ($product->track_stock ?? 0) !== 1) {
                continue;
            }

            $ledgerRows = [];
            $saleQty = $qty;

            $warehouseProducts = WarehouseProduct::where('product_id', $product->id)
                ->whereIn('warehouse_id', $warehouse_ids)
                ->where('quantity', '>', 0)
                ->orderByRaw('CASE WHEN expire IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expire', 'asc')
                ->lockForUpdate()
                ->get();

            $remaining = $saleQty;

            foreach ($warehouseProducts as $wp) {
                if ($remaining <= 0) break;

                $deductQty = $r(min($r($wp->quantity), $remaining));

                if ($deductQty <= 0) continue;

                $wp->quantity = $r($wp->quantity - $deductQty);
                $wp->save();

                $ledgerRows[] = [
                    'warehouse_id' => $wp->warehouse_id,
                    'bin_id' => $wp->bin_id,
                    'lot' => $wp->lot,
                    'expire_date' => $wp->expire,
                    'quantity' => $deductQty,
                ];

                $remaining = $r($remaining - $deductQty);

                $this->syncPurchaseRemainingQty($product->id, $wp->lot, $wp->warehouse_id);
            }

            if ($r($remaining) > 0) {
                throw new \Exception("{$product->name} មិនមានស្តុកគ្រប់គ្រាន់ទេ");
            }

            foreach ($ledgerRows as $row) {
                $rowQty = $r($row['quantity']);

                $rowUnitCost = $r($this->getLotUnitCost($product->id, $row['lot'] ?? null, $row['warehouse_id'] ?? null));

                $warehouseName = Warehouse::where('id', $row['warehouse_id'])->value('name');
                $rowBinName = !empty($row['bin_id']) ? optional(\App\Models\Bin::find($row['bin_id']))->name : null;

                $rowLineAmount = $saleQty > 0 ? $r(($lineAmount / $saleQty) * $rowQty) : 0;
                $rowDiscountAmount = $saleQty > 0 ? $r(($discountAmount / $saleQty) * $rowQty) : 0;
                $rowNetAmount = $saleQty > 0 ? $r(($netAmount / $saleQty) * $rowQty) : 0;
                $rowVatAmount = $saleQty > 0 ? $r(($vatAmount / $saleQty) * $rowQty) : 0;
                $rowGrandTotal = $saleQty > 0 ? $r(($grandTotalLine / $saleQty) * $rowQty) : 0;

                $ledger = $this->createLedgerEntry([
                    'posting_date' => now()->toDateString(),
                    'document_type' => 'Sales Invoice',
                    'source_no' => $saleOrder->document_no,
                    'document_no' => $invoice->invoice_number,

                    'source_id' => $invoice->id,
                    'source_table' => 'sale_invoice_headers',

                    'product_id' => $product->id,
                    'barcode' => $product->bar_code,
                    'item_code' => $product->code,
                    'name' => $product->name,
                    'variant' => $product->variant,
                    'description' => $product->description,
                    'unit' => $unit,
                    'category_name' => optional($product->category)->name,
                    'type' => 'product',

                    'warehouse_id' => $row['warehouse_id'],
                    'warehouse_name' => $warehouseName,
                    'bin_id' => $row['bin_id'] ?? null,
                    'bin_name' => $rowBinName,
                    'lot' => $row['lot'],
                    'expire_date' => $row['expire_date'],

                    'quantity' => $r(-1 * $rowQty),
                    'remaining_quantity' => 0,
                    'entry_type' => 'negative',

                    'currency_name'  => $riel->code,
                    'factor'         => $riel->factor,

                    // unit_cost stays positive; cost_amount is the signed
                    // inventory movement, negative here because the goods are
                    // leaving stock. The sales value of the same line stays in
                    // net_amount / grand_total_amount below, so inventory value
                    // and revenue never share a column.
                    'unit_cost' => abs($rowUnitCost),
                    'cost_amount' => -round(abs($rowQty) * abs($rowUnitCost), 6),
                    'unit_price' => $unitPrice,
                    'sell_price' => $sellPrice,

                    'discount_percent' => $discountPercent,
                    'discount_amount' => $rowDiscountAmount,
                    'vat' => $vatRate,
                    'vat_amount' => $rowVatAmount,

                    'line_amount' => $rowLineAmount,
                    'net_amount' => $rowNetAmount,
                    'grand_total_amount' => $rowGrandTotal,

                    'customer_id' => $saleOrder->customer_id,
                    'customer_name' => $saleOrder->contact_name,
                    'customer_phone' => $saleOrder->phone,
                    'customer_address' => $saleOrder->address,

                    'payment_method' => $saleOrder->payment_method,
                    'remark' => 'Shipped from Order',
                    'created_by' => Auth::user()->username ?? 'System',
                    'created_user_id' => Auth::id(),
                ]);

                $ledger->update(['entry_no' => $ledger->id]);
            }

            $line->update([
                'quantity_shiped' => $r((float) ($line->quantity_shiped ?? 0) + $qty),
            ]);
        }

        $this->assertLinesWritten($linesWritten, $invoice->invoice_number);

        return $invoice;
    }
    public function syncPurchaseRemainingQty($productId, $lot = null, $warehouseId = null)
    {
        $lot = !is_null($lot) && trim($lot) !== '' ? trim($lot) : null;

        // real on-hand for this lot from the warehouse
        $remainingQty = WarehouseProduct::where('product_id', $productId)
            ->when($lot !== null, fn($q) => $q->where('lot', $lot), fn($q) => $q->whereNull('lot'))
            ->when($warehouseId !== null, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');

        // ALL positive ledger rows for this lot (purchase + any sale-return rows), newest first
        $purchaseEntries = ItemLedgerEntry::where('product_id', $productId)
            ->where('entry_type', 'positive')
            ->when($lot !== null, fn($q) => $q->where('lot', $lot), fn($q) => $q->whereNull('lot'))
            ->when($warehouseId !== null, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('id') // newest stock used first
            ->get();

        // distribute the warehouse total across the rows, capped at each row's own quantity.
        // this wipes stale values and guarantees SUM(remaining_quantity) == warehouse.
        $left = $remainingQty;

        foreach ($purchaseEntries as $entry) {
            $entryQty = abs($entry->quantity);

            $newRemaining = min($left, $entryQty);

            $entry->update([
                'remaining_quantity' => $newRemaining,
            ]);

            $left -= $newRemaining;

            if ($left <= 0) {
                $left = 0;
            }
        }

        return $remainingQty;
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
    #[\Livewire\Attributes\On('clearAll_after_payment')]
    public function clearAll_after_payment()
    {
        // 4️⃣ Clear current table products

        // Clear cart/session
        $this->clearCustomer();
        $this->clearCart_no_message();
    }


    protected function generateInvoiceNumber()
    {
        return Serial_No::next('invoice');
    }
    #[\Livewire\Attributes\On('transferCartToTable')]

    #[\Livewire\Attributes\On('save-cart-to-table')]







    #[\Livewire\Attributes\On('printReciept')]
    public function printReciept($invoice_no = null)
    {
        abort_unless(Auth::user()->hasPermission('pos_sale.sell'), 403);

        if (!$invoice_no) {
            $this->dispatch('payment-error', ['message' => 'Invoice number missing']);
            return;
        }

        // Reset cart first
        $this->cart = [];
        $this->count_cart = 0;

        // Get invoice with lines and product info
        $invoice = InvoiceHeader::with(['lines.item'])
            ->where('invoice_number', $invoice_no)
            ->first();

        if (!$invoice) {
            $this->dispatch('payment-error', ['message' => 'Invoice not found']);
            return;
        }
        // $this->dispatch('get-date', [
        //     'invoice_date' => $invoice->invoice_date,
        //     'due_date' => $invoice->due_date,
        //     'invoice_no' => $invoice->invoice_number
        // ]);


        $order = 1;

        foreach ($invoice->lines as $line) {
            // if (!$line->product) continue;

            $discountPrice = $line->total_amount / max($line->quantity, 1);

            $this->cart[] = [
                'id' => $line->product_id,
                'name' => $line->name,
                'price' => $line->unit_price,
                'qty' => $line->quantity,
                'type' => 'product',
                'discount_percent' => $line->discount_percent,
                'discount_price' => $discountPrice,
                'order_no' => $order++,
                'amount_line' => $line->line_amount,
                'discount_amount_line' => $line->discount_amount,
                'net_amount_line' => $line->total_amount,
                'stock' => $line->item->stock ?? 0,
                'unit' => $line->item->unit ?? 'NA',
                'track_stock' => $line->product->track_stock ?? false,
            ];
        }

        $this->count_cart = count($this->cart);

        // Optional: set invoice number in UI
        // $this->GetInvoiceNo($invoice->invoice_number);



        $this->dispatch('trigger-print');
    }




    public function assignQueueForOrder($isUpdate = false)
    {
        // 1️⃣ New cart → always new queue
        if ($this->new_cart && $this->cart_queue_no == 0) {
            $this->cart_queue_no = $this->incrementQueueTable();
            $this->getDocument($this->cart_queue_no);
            $this->new_cart = false;
        }

        // 2️⃣ Updating existing order → assign new queue if desired
        else if ($isUpdate) {
            // optional: only assign new queue if old queue exists
            $this->cart_queue_no = $this->incrementQueueTable();
            $this->getDocument($this->cart_queue_no);
        }

        // 3️⃣ Otherwise → keep old queue
    }

    protected function incrementQueueTable()
    {
        $today = now()->toDateString();

        // Get or create today’s queue
        $queue = TableQueue::firstOrCreate(
            ['queue_date' => $today],
            ['last_number' => 0]
        );

        // Increment properly
        $queue->last_number += 1;
        $queue->save();

        return $queue->last_number;
    }


    #[\Livewire\Attributes\On('exit_table')]
    public function exit_table()
    {
        $this->cart = [];
        $this->count_cart = 0;

        $this->clearCart();
        $this->clearCustomer();
    }






    public function getDocument($queueNo)
    {
        $date = now()->format('Y-m-d'); // 2026-02-13

        $document = $date . '-' . str_pad($queueNo, 4, '0', STR_PAD_LEFT);

        $this->dispatch('get-document', [
            'document_no' => $this->cart_queue_no,
        ]);
    }
    public function clearCustomer()
    {
        $this->customer_name = "";
        $this->customer_id = null;
        $this->customer_phone =  "";
        $this->customer_address1 = "";
        $this->customer_address2 =  "";
        $this->customer_city =  "";

        $this->dispatch('update-customer-input', [
            'display' => "",
            'code' =>  ""
        ]);
    }


    public function updatedCustomerId($value)
    {
        $this->selectcustomer($value);
    }

    public function selectcustomer($customerId)
    {
        $customer = Customer::where('customer_code', $customerId)->first();

        if ($customer) {
            $this->customer_name    = $customer->name;
            $this->customer_phone   = $customer->phone;
            $this->customer_address1 = $customer->address1;
            $this->customer_address2 = $customer->address2;
            $this->customer_city    = $customer->city;
            $this->customer_contact_name = $customer->contact_name;
            $this->customer_contact_phone = $customer->contact_phone;
        } else {
            $this->customer_name    = 'Walk-in Customer';
            $this->customer_phone   = '';
            $this->customer_address1 = '';
            $this->customer_address2 = '';
            $this->customer_city    = '';
            $this->customer_contact_name = '';
            $this->customer_contact_phone  = '';
        }
    }

    protected $listeners = ['refreshCurrency'];


    public function refreshCurrency()
    {
        $currency = Currency::where('code', $this->currency)->first();

        if ($currency) {
            $this->currency = $currency->code;
            $this->factor   = $currency->factor;
            $this->currency_name = $currency->name;
        }
    }
    #[\Livewire\Attributes\On('mount')]
    /**
     * Warehouses this user may sell from — their own, minus any restricted to
     * purchasing. Auto-selects when there is only one, so the picker is only
     * ever shown to someone who genuinely has a choice.
     */
    public function loadSaleWarehouses(): void
    {
        $this->saleWarehouses = Warehouse::whereIn('id', Auth::user()->warehouses->pluck('id'))
            ->usableFor('sale')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        if (count($this->saleWarehouses) === 1) {
            $this->sale_warehouse_id = (string) array_key_first($this->saleWarehouses);
        }
    }

    /**
     * Warehouse ids a sale may draw from.
     *
     * Scoped to the chosen warehouse once one is set. Falls back to all of the
     * user's sale warehouses only when none was chosen — which happens when the
     * user has exactly one (auto-selected above) or none at all.
     */
    private function saleWarehouseIds(): array
    {
        if ($this->sale_warehouse_id !== '' && $this->sale_warehouse_id !== null) {
            return [(int) $this->sale_warehouse_id];
        }

        // Ambiguous: refuse rather than quietly deducting across every site.
        // Reachable when a saved sale order is loaded into a fresh cart, since
        // sale orders carry no warehouse of their own.
        if (count($this->saleWarehouses) > 1) {
            throw new \Exception(__('Select a warehouse before completing this sale.'));
        }

        return array_map('intval', array_keys($this->saleWarehouses))
            ?: Auth::user()->warehouses->pluck('id')->all();
    }

    /**
     * Is this balance settled, allowing for riel that cannot express a cent?
     *
     * Riel is taken on a 100៛ grid, so a USD remainder worth less than half a
     * note is physically unpayable — at 4,025៛ that is anything under $0.0124.
     * Comparing USD directly left orders stuck on Partial showing $0.01 owed
     * after the customer had already handed over every note they could.
     *
     * Judged on the same 100៛ grid the cashier sees, so the screen and the
     * stored status agree. A genuinely owed $0.02 is 80.5៛, which rounds up to
     * a payable 100៛ note and correctly stays outstanding.
     */
    private function isSettled(float $paid, float $total): bool
    {
        if ($paid >= $total) {
            return true;
        }

        $rate = (float) (Currency::where('code', '៛')->value('factor') ?? 0);
        if ($rate <= 0) {
            return false; // no riel configured — the plain USD comparison stands
        }

        return round((($total - $paid) * $rate) / 100) * 100 <= 0;
    }

    /**
     * Tell the POS grid to re-pull stock for the newly chosen warehouse.
     *
     * Without this the cards keep showing the previous warehouse's figures,
     * which no longer describe what the sale can draw on.
     */
    public function updatedSaleWarehouseId($value): void
    {
        $this->dispatch('sale-warehouse-changed', warehouseId: $value);
    }

    /**
     * The picker locks once the cart has lines — see $sale_warehouse_id.
     *
     * Only locks after a warehouse has actually been chosen. Loading a saved
     * sale order fills the cart without setting one, and locking there would
     * strand the user with no way to pick.
     */
    public function getSaleWarehouseLockedProperty(): bool
    {
        return ! empty($this->cart)
            && $this->sale_warehouse_id !== ''
            && $this->sale_warehouse_id !== null;
    }

    public function mount()
    {
        $this->loadSaleWarehouses();

        // ✅ Load currencies ONCE
        $this->all_currency = Currency::all();

        // ✅ Set default currency
        $default = $this->all_currency->where('is_default', 1)->first();

        if ($default) {
            $this->currency = $default->code;
            $this->currency_name = $default->code;
            $this->factor   = $default->factor;
        }
    }


    private function getRielCurrency()
    {
        return Currency::where('code', '៛')->firstOrFail();
    }

    /**
     * Resolves a cart/document line's product or aborts the whole transaction.
     *
     * These loops used to `continue` past a missing product. Because the header
     * row is written before the line loop runs, skipping every line still
     * committed successfully — producing a sale order / invoice / quotation
     * header with no lines at all (and, on the sell path, no stock movement to
     * match it). A line pointing at a product that no longer exists means the
     * cart is stale, so the correct response is to reject the entire save.
     */
    private function requireProduct($productId): Product
    {
        $product = $productId ? Product::find($productId) : null;

        if (!$product) {
            throw new \RuntimeException(
                "This item is no longer available (product #{$productId}). "
                . "Remove it from the cart and try again — nothing was saved."
            );
        }

        return $product;
    }

    /**
     * Last line of defence: a document must never be committed with zero lines,
     * whatever went wrong upstream. Called inside the transaction, so throwing
     * here rolls the header back with it.
     */
    private function assertLinesWritten(int $written, string $documentNo): void
    {
        if ($written < 1) {
            throw new \RuntimeException(
                "Nothing was saved for {$documentNo}: the document had no valid lines."
            );
        }
    }

    public function clearTable()
    {
        $this->customer_id = null;
        $this->customer_discount_percent = 0;
    }
    #[\Livewire\Attributes\On('clearCart')]
    public function clearCart()
    {

        $this->new_cart = true;
        $this->cart = [];
        $this->qty = 0;
        $this->count_cart = 0;
        $this->cart_mode = 'normal';
        $this->customer_discount_percent = 0;
        $this->document_no = 'NA';
        $this->document_id = 0;
        $this->document_type = 'NA';
        $this->dispatch('cart-cleared', [
            'message' => 'Cart has been cleared'
        ]);
    }
    #[\Livewire\Attributes\On('clearCart_no_message')]
    public function clearCart_no_message()
    {
        $this->document_no = 'NA';
        $this->new_cart = true;
        $this->cart = [];
        $this->qty = 0;
        $this->count_cart = 0;
        $this->cart_mode = 'normal';
        $this->customer_discount_percent = 0;
        $this->document_id = 0;
        $this->document_type = 'NA';
    }



    #[\Livewire\Attributes\On('add-product')]
    public function addProduct($productJson)
    {
        if ($this->document_type == 'Deposit' || $this->document_type == 'Completed' ||  $this->document_type == 'Cancelled' || $this->document_type == 'Returned') {
            $this->dispatch('product_item_prevented', [
                'message' => 'មិនអាចថែមទេ'
            ]);
            return;
        }
        if ($this->new_cart && $this->cart_queue_no == 0) {
            $this->cart_queue_no = $this->incrementQueueTable();
            $this->getDocument($this->cart_queue_no);
            $this->new_cart = false;
        }
        $this->deposit = 0;
        $this->balanceAmount_display = 0;
        $product = json_decode($productJson, true);

        if (!is_array($product) || !isset($product['id'])) {
            return;
        }

        // A stock line has to know which warehouse it leaves. Without this the
        // fallback in saleWarehouseIds() would span every warehouse again.
        if (
            ($product['type'] ?? '') === 'product'
            && count($this->saleWarehouses) > 1
            && ($this->sale_warehouse_id === '' || $this->sale_warehouse_id === null)
        ) {
            $this->dispatch('product_item_prevented', [
                'message' => __('Select a warehouse first'),
            ]);
            return;
        }

        if (empty($this->cart)) {
            $this->cart_mode = ($product['type'] === 'expence') ? 'expence' : 'sale';
        }

        if ($this->cart_mode === 'expence' && $product['type'] !== 'expence') {
            $this->dispatch('product_item_prevented', [
                'message' => 'ចំណាយ មិនអាចបន្ថែមបានទេ'
            ]);
            return;
        }

        if ($this->cart_mode === 'sale' && $product['type'] === 'expence') {
            $this->dispatch('expense_item_prevented', [
                'message' => 'ទំនិញលក់ មិនអាចបន្ថែមចំណាយបានទេ'
            ]);
            return;
        }

        $vat = (float) ($product['vat'] ?? 0);
        $price = (float) ($product['sell_price'] ?? 0);
        $type = strtolower($product['type'] ?? '');
        $allowDiscount = array_key_exists('allow_discount', $product) ? (bool) $product['allow_discount'] : true;
        $allowReturn = array_key_exists('allow_return', $product) ? (bool) $product['allow_return'] : true;
        $catalogDiscountPercent = (float) ($product['discount_percent'] ?? 0);

        if (!$allowDiscount) {
            $discountPercent = 0;
        } elseif ($this->customer_discount_percent > 0 && $type === 'product') {
            $discountPercent = (float) $this->customer_discount_percent;
        } else {
            $discountPercent = $catalogDiscountPercent;
        }
        $stock = (float) ($product['stock'] ?? 0);
        $unit = $product['unit'] ?? 'NA';
        $trackStock = (int) ($product['track_stock'] ?? 0);

        // Per unit calculation
        $discountAmount = ($price * $discountPercent) / 100;
        $netPrice = $price - $discountAmount;          // before VAT
        $vatAmount = ($netPrice * $vat) / 100;         // VAT on net price
        $grandPrice = $netPrice + $vatAmount;          // optional, if needed

        static $lastAddedId = null;
        static $lastClickTime = 0;

        $now = microtime(true) * 1000;

        if ($lastAddedId === $product['id'] && $now - $lastClickTime < 300) {
            return;
        }

        $lastAddedId = $product['id'];
        $lastClickTime = $now;

        foreach ($this->cart as $index => $item) {
            if ($item['id'] === $product['id']) {
                if ($this->cart_mode === 'expence') {
                } else {
                    $this->cart[$index]['qty']++;
                    // }

                    $qty = $this->cart[$index]['qty'];

                    $this->cart[$index]['discount_percent'] = $discountPercent;
                    $this->cart[$index]['discount_price'] = $netPrice;
                    $this->cart[$index]['allow_discount'] = $allowDiscount;
                    $this->cart[$index]['allow_return'] = $allowReturn;
                    $this->cart[$index]['amount_line'] = $qty * $price;
                    $this->cart[$index]['discount_amount_line'] = $qty * $discountAmount;
                    $this->cart[$index]['net_amount_line'] = $qty * $netPrice;      // before VAT
                    $this->cart[$index]['vat_amount_line'] = $qty * $vatAmount;     // each line VAT
                    $this->cart[$index]['vat'] = $vat;
                    // optional:
                    // $this->cart[$index]['grand_amount_line'] = ($qty * $netPrice) + ($qty * $vatAmount);

                    $this->count_cart = count($this->cart);
                    return;
                }
            }
        }

        $this->cart[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'type' => $product['type'],
            'price' => $price,
            'qty' => 1,
            'discount_percent' => $discountPercent,
            'original_discount_percent' => $allowDiscount ? $catalogDiscountPercent : 0,
            'allow_discount' => $allowDiscount,
            'allow_return' => $allowReturn,
            'discount_price' => $netPrice,
            'order_no' => count($this->cart) + 1,
            'amount_line' => $price,
            'discount_amount_line' => $discountAmount,
            'net_amount_line' => $netPrice,         // before VAT
            'vat_amount_line' => $vatAmount,        // VAT for this line
            'vat' => $vat,
            'stock' => $stock,
            'unit' => $unit,
            'track_stock' => $trackStock,
            // optional:
            // 'grand_amount_line' => $grandPrice,
        ];

        $this->count_cart = count($this->cart);
    }

    public function getTotalsProperty()
    {
        $precision = 10;

        $totalOriginal = 0;
        $totalDiscount = 0;
        $totalNet = 0;
        $totalVatStatus = 0;
        $totalVatAmount = 0;


        foreach ($this->cart as $item) {
            $lineOriginal  = round((float) ($item['amount_line'] ?? 0), $precision);
            $lineDiscount  = round((float) ($item['discount_amount_line'] ?? 0), $precision);
            $lineNet       = round((float) ($item['net_amount_line'] ?? 0), $precision);
            $lineVat       = round((float) ($item['vat'] ?? 0), $precision);
            $lineVatAmount = round((float) ($item['vat_amount_line'] ?? 0), $precision);

            $totalOriginal  = round($totalOriginal + $lineOriginal, $precision);
            $totalDiscount  = round($totalDiscount + $lineDiscount, $precision);
            $totalNet       = round($totalNet + $lineNet, $precision);
            $totalVatAmount = round($totalVatAmount + $lineVatAmount, $precision);


            if ($lineVat > $totalVatStatus) {
                $totalVatStatus = $lineVat;
            }
        }

        $grandTotal = $this->cart_mode === 'expence'
            ? $totalNet
            : round($totalNet + $totalVatAmount, $precision);

        // Riel snaps every unit price to a 100៛ grid before it is shown, so the
        // figure on screen is NOT base-USD × factor. The cashier tenders what is
        // displayed, so that has to be what is owed — otherwise every riel sale
        // banks a phantom balance: the cart showed 55,330៛, the header stored
        // 55,800៛, and the order sat 470៛ short of Paid. Convert the stepped
        // display back to USD at the order's own locked factor so the two agree.
        $display = $this->totalsDisplay;
        if (($display['step'] ?? 0) > 0 && ($display['factor'] ?? 1) > 1) {
            $f = (float) $display['factor'];

            return [
                'total_original'   => round($display['total_original'] / $f, $precision),
                'total_discount'   => round($display['total_discount'] / $f, $precision),
                'total_net'        => round($display['total_net'] / $f, $precision),
                'vat_status'       => round($display['vat_status'], $precision),
                'total_vat_amount' => round($display['total_vat_amount'] / $f, $precision),
                'grand_total'      => round($display['grand_total'] / $f, $precision),
            ];
        }

        return [
            'total_original'   => round($totalOriginal, $precision),
            'total_discount'   => round($totalDiscount, $precision),
            'total_net'        => round($totalNet, $precision),
            'vat_status'       => round($totalVatStatus, $precision),
            'total_vat_amount' => round($totalVatAmount, $precision),
            'grand_total'      => round($grandTotal, $precision),

        ];
    }
    public function getTotalsDisplayProperty()
    {
        $factor = (float) ($this->factor ?: 1);

        if ($factor == 1) {
            $decimal = 2;
            $step = 0;
        } elseif ($factor >= 4000) {
            $decimal = 0;
            $step = 100;
        }  // KHR: unit ends in 00
        elseif ($factor >= 100) {
            $decimal = 3;
            $step = 0;
        } else {
            $decimal = 2;
            $step = 0;
        }

        // round ONE base-USD unit price to display currency (same as Blade $unitDisp)
        $unitDisp = function ($baseUnit) use ($factor, $step, $decimal) {
            $v = (float) $baseUnit * $factor;
            return $step > 0 ? round($v / $step) * $step : round($v, $decimal);
        };

        $sub = 0.0;
        $net = 0.0;
        $vat = 0.0;
        $vatStatus = 0.0;
        foreach ($this->cart as $item) {
            $price   = (float) ($item['price'] ?? 0);
            $netUnit = (float) ($item['discount_price'] ?? $price);
            $qty     = (float) ($item['qty'] ?? 0);
            $vatRate = (float) ($item['vat'] ?? 0);

            // WYSIWYG: round unit FIRST, then × qty — exactly like the line cells
            $sub += $unitDisp($price)   * $qty;
            $net += $unitDisp($netUnit) * $qty;
            $vat += $unitDisp($netUnit * $vatRate / 100) * $qty;

            // Highest line rate, computed here rather than read from
            // $this->totals: getTotals now derives its figures from THIS
            // method, so reaching back into it would recurse forever.
            if ($vatRate > $vatStatus) {
                $vatStatus = $vatRate;
            }
        }

        $disc  = $sub - $net;
        $grand = ($this->cart_mode === 'expence') ? $net : ($net + $vat);

        return [
            'total_original'   => round($sub, $decimal),
            'total_discount'   => round($disc, $decimal),
            'total_net'        => round($net, $decimal),
            'total_vat_amount' => round($vat, $decimal),
            'vat_status'       => $vatStatus,
            'grand_total'      => round($grand, $decimal),
            'step'             => $step,
            'factor'           => $factor,
        ];
    }
    #[\Livewire\Attributes\On('applyCustomerDiscountEvent')]
    public function applyCustomerDiscountEvent($discount = 0)
    {
        $discount = (float) $discount;

        $this->customer_discount_percent = $discount;
        $this->applyCustomerDiscountToCart($discount);
    }
    public function applyCustomerDiscountToCart($discountPercent = null)
    {
        $customerDiscountPercent = (float) ($discountPercent ?? $this->customer_discount_percent ?? 0);
        $skippedItems = [];

        foreach ($this->cart as $index => $item) {
            $price = (float) ($item['price'] ?? 0);
            $qty   = (float) ($item['qty'] ?? 0);
            $vat   = (float) ($item['vat'] ?? 0);
            $type  = $item['type'] ?? 'sale';
            $allowDiscount = array_key_exists('allow_discount', $item) ? (bool) $item['allow_discount'] : true;

            $originalDiscountPercent = (float) ($item['original_discount_percent'] ?? 0);

            if ($type === 'expence' || $type === 'service') {
                $appliedDiscountPercent = 0;
                $lineOriginal = $price * $qty;
                $lineDiscountAmount = 0;
                $lineNet = $lineOriginal;
                $lineVatAmount = 0;
                $vat = 0;
            } elseif (!$allowDiscount) {
                // this product never gets a discount — not the customer's,
                // not its own catalog one either
                if ($customerDiscountPercent > 0) {
                    $skippedItems[] = $item['name'] ?? 'item';
                }
                $appliedDiscountPercent = 0;
                $lineOriginal = $price * $qty;
                $lineDiscountAmount = 0;
                $lineNet = $lineOriginal;
                $lineVatAmount = ($lineNet * $vat) / 100;
            } else {
                // if customer has discount, use it
                // otherwise restore original product discount only
                $appliedDiscountPercent = $customerDiscountPercent > 0
                    ? $customerDiscountPercent
                    : $originalDiscountPercent;

                $lineOriginal = $price * $qty;
                $lineDiscountAmount = ($lineOriginal * $appliedDiscountPercent) / 100;
                $lineNet = $lineOriginal - $lineDiscountAmount;
                $lineVatAmount = ($lineNet * $vat) / 100;
            }

            $this->cart[$index]['discount_percent'] = $appliedDiscountPercent;
            $this->cart[$index]['amount_line'] = $lineOriginal;
            $this->cart[$index]['discount_amount_line'] = $lineDiscountAmount;
            $this->cart[$index]['net_amount_line'] = $lineNet;
            $this->cart[$index]['vat_amount_line'] = $lineVatAmount;
            $this->cart[$index]['vat'] = $vat;
        }

        if (!empty($skippedItems)) {
            $this->dispatch('discount-not-allowed', [
                'message' => 'Discount not applied to: ' . implode(', ', array_unique($skippedItems)),
            ]);
        }
    }
    /**
     * Nudge a line's quantity by a whole unit, from the right-click stepper.
     *
     * Goes through recalcLine like every other qty change, so discount, VAT and
     * the line total are recomputed by the same code path — a stepper that wrote
     * qty directly would leave the totals stale.
     *
     * Clamped server-side: the caller is a mouse wheel, and stock limits are not
     * the browser's to enforce.
     */
    #[\Livewire\Attributes\On('set-qty')]
    public function setQtyFromStepper($index, $qty)
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        // An ABSOLUTE quantity, not a delta. Deltas raced: the stepper sent one
        // while an earlier round trip was still in flight, the row re-rendered
        // with a stale baseline, and the number snapped backwards — so a wheel
        // could never climb past a dozen or so.
        $next = round((float) $qty, 2);

        // Never below one unit — removing a line is the remove button's job, not
        // something a stray scroll should do.
        if ($next < 0.01) {
            $next = 0.01;
        }

        $type = $this->cart[$index]['type'] ?? 'product';
        $stock = (float) ($this->cart[$index]['stock'] ?? 0);

        // Services have no stock to run out of; goods are capped at what is there.
        if ($type !== 'service' && $stock > 0 && $next > $stock) {
            $next = $stock;
            $this->dispatch('app-error', [
                'message' => 'Only ' . rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.') . ' in stock.',
            ]);
        }

        $this->cart[$index]['qty'] = $next;
        $this->recalcLine($index, 'qty');
    }

    public function recalcLine($index, $field, $inputValue = null)
    {
        $precision = 10;   // base unit price keeps 10 dp so riel survives re-multiplication
        $factor = (float) ($this->factor ?: 1);
        if (!isset($this->cart[$index])) return;

        // Forces the price input's wire:key to change on every call (even a rejected
        // one), so Livewire always remounts it from the server's own current value —
        // guarantees the field snaps back to the real price if an edit is denied,
        // instead of leaving whatever the user typed sitting in the input.
        $this->priceInputNonce++;

        $qty      = round((float) ($this->cart[$index]['qty'] ?? 0), 2);   // ← qty is 2 dp
        $discount = round((float) ($this->cart[$index]['discount_percent'] ?? 0), $precision);
        $vat      = round((float) ($this->cart[$index]['vat'] ?? 0), $precision);
        $price    = round((float) ($this->cart[$index]['price'] ?? 0), $precision);

        // =========================
        // PRICE INPUT (DISPLAY → BASE)
        // =========================
        if ($field === 'price' && $inputValue !== null) {
            if (!Auth::user()->hasPermission('pos_sale.edit_price')) {
                $this->dispatch('app-error', [
                    'message' => 'You do not have permission to edit the price.',
                ]);
                return;
            }

            $inputValue = str_replace(',', '', $inputValue);

            $displayPrice = (float) $inputValue;

            $price = $factor != 1
                ? round($displayPrice / $factor, $precision)
                : round($displayPrice, $precision);

            $this->cart[$index]['price'] = $price;
        }

        // =========================
        // QTY INPUT (MIN 0.01, 2 DP)
        // =========================
        if ($field === 'qty') {
            $inputQty = $inputValue !== null
                ? (float) str_replace(',', '', $inputValue)
                : $qty;

            $qty = round(max(0.01, $inputQty), 2);   // blocks 0.001, snaps up to 0.01
            $this->cart[$index]['qty'] = $qty;
        }

        // =========================
        // DISCOUNT % (manual line-level edit by the cashier — the customer's own
        // stored discount_percent still auto-applies via applyCustomerDiscountToCart()
        // regardless of this permission, per product requirement)
        // =========================
        if ($field === 'discount_percent') {
            if (!Auth::user()->hasPermission('pos_sale.edit_discount')) {
                $this->dispatch('app-error', [
                    'message' => 'You do not have permission to edit the discount.',
                ]);
                return;
            }

            if ($inputValue !== null) {
                $discount = (float) str_replace(',', '', $inputValue);
            }

            $discount = min(max(0, $discount), 100);

            $allowDiscount = array_key_exists('allow_discount', $this->cart[$index])
                ? (bool) $this->cart[$index]['allow_discount']
                : true;

            if (!$allowDiscount && $discount > 0) {
                $discount = 0;
                $this->dispatch('discount-not-allowed', [
                    'message' => 'This item does not allow a discount — reset to 0%.',
                ]);
            }

            $this->cart[$index]['discount_percent'] = round($discount, $precision);
        }

        // =========================
        // 🔥 CALCULATION (BASE USD)
        // =========================
        $discountAmount = round(($price * $discount) / 100, $precision);
        $discountPrice  = round($price - $discountAmount, $precision);
        $vatAmount      = round(($discountPrice * $vat) / 100, $precision);

        $this->cart[$index]['discount_price']       = $discountPrice;
        $this->cart[$index]['amount_line']          = round($price * $qty, $precision);
        $this->cart[$index]['discount_amount_line'] = round($discountAmount * $qty, $precision);
        $this->cart[$index]['net_amount_line']      = round($discountPrice * $qty, $precision);
        $this->cart[$index]['vat_amount_line']      = round($vatAmount * $qty, $precision);

        $this->cart = array_values($this->cart);
    }

    // Set currency and get factor
    public function setCurrency($code)
    {
        $currency = Currency::where('code', $code)->first();


        if ($currency) {
            $this->currency = $currency->code;
            $this->currency_name = $currency->code;
            $this->factor = $currency->factor;

            $this->dispatch('change-currency', ['factor' => $this->factor,  'currency_name' => $this->currency_name]);
        }else{
             $this->currency = 'USD';
                $this->currency_name = '$';
                $this->factor = 1; // Conversion factor
                   $this->dispatch('change-currency', ['factor' => $this->factor,  'currency_name' => $this->currency_name]);
        }

    }

    public function removeItem($id)
    {
        foreach ($this->cart as $index => $item) {
            if ($item['id'] == $id) {
                unset($this->cart[$index]);
                break;
            }
        }

        // Reindex array (IMPORTANT for Livewire)
        $this->cart = array_values($this->cart);

        // Recalculate order_no
        foreach ($this->cart as $i => $item) {
            $this->cart[$i]['order_no'] = $i + 1;
        }

        $this->count_cart = count($this->cart);
        if ($this->count_cart == 0) {

            $this->clearCustomer();
        }
    }

    // Step 1 only — creates the SaleOrderHeader/lines but never touches stock
    // or the invoice/ledger (that only happens in confirmDepositSaleOrder /
    // updateDepositSaleOrder). Order and Sell are independent permissions —
    // Sell is a separate, complete one-click transaction and does not imply
    // the right to create a no-stock-yet order.
    #[\Livewire\Attributes\On('confirmSaleOrder')]
    public function confirmSaleOrder($payload)
    {
        abort_unless(Auth::user()->hasPermission('pos_sale.order'), 403);

        if (empty($this->cart)) {
            $this->dispatch('payment-error', ['message' => 'Cart is empty']);
            return;
        }
        $saleOrderNo = '';
        try {
            DB::transaction(function () use ($payload, &$saleOrderNo) {

                $totalAmount = 0;
                $totalDiscount = 0;
                $totalVAT = 0;

                $customer_id = !empty($payload['customer_id']) ? (int) $payload['customer_id'] : null;
                $customer_name = $payload['customer_name'] ?? 'Walk-in Customer';
                $customer_phone = $payload['customer_phone'] ?? 'NA';
                $customer_address = $payload['customer_address'] ?? null;


                $deliveryStatus = $payload['delivery_status'] ?? 'N/A';
                if ($deliveryStatus == '') {
                    $deliveryStatus = 'Pending';
                }


                if ($deliveryStatus === '' || $deliveryStatus === null) {
                    $deliveryStatus = 'Pending';
                }
                $saleOrderNo = $this->generateSaleOrderNo();

                $requestedStatus = $payload['status'] ?? 'Ordered';

                $saleOrder = SaleOrderHeader::create([
                    'document_no'      => $saleOrderNo,

                    'customer_id'      => $customer_id,
                    'contact_name'     => $customer_name,
                    'phone'            => $customer_phone,
                    'address'          => $customer_address,

                    'posting_date'     => $payload['document_date'] ?? now()->toDateString(),
                    'order_date'       => $payload['order_date'] ?? null,
                    'delivery_date'    => $payload['delivery_date'] ?? null,

                    'delivery_status'  => $payload['delivery_status'] ?? 'N/A',
                    'delivery_info'    => $payload['delivery_info'] ?? null,
                    'driver_name'      => $payload['driver_name'] ?? null,
                    'driver_phone'     => $payload['driver_phone'] ?? null,

                    'total_amount'     => 0,
                    'vat_amount'       => 0,
                    'discount_percent' => $payload['discount_percent'] ?? 0,
                    'discount_amount'  => 0,
                    'grand_total'      => 0,

                    'deposit_amount'   => 0,
                    'paid_amount'      => 0,
                    'balance_amount'   => 0,

                    'status'           => $requestedStatus,
                    'payment_status'   => 'Unpaid',

                    'customer_type'    => $payload['customer_type'] ?? null,
                    'payment_method'   => $payload['paymentMethod'] ?? null,
                    'currency_name'    => $this->currency_name ?? 'USD',
                    'factor'           => $this->factor ?? 1,

                    'remarks'          => $payload['remark'] ?? null,
                    'created_by'       => Auth::user()->username ?? 'System',
                    'created_user_id'  => Auth::id(),
                ]);


                $linesWritten = 0;
                foreach ($this->cart as $cartItem) {
                    $product = $this->requireProduct($cartItem['id']);
                    $linesWritten++;

                    $qty = max(0.01, (float) ($cartItem['qty'] ?? 1));
                    $sellPrice = (float) ($cartItem['price'] ?? $cartItem['sell_price'] ?? 0);
                    $vatRate = (float) ($cartItem['vat'] ?? 0);
                    $discountPercent = (float) ($cartItem['discount_percent'] ?? 0);

                    $unitCost = (float) ($product->cost ?? 0);
                    $unitPrice = (float) ($product->sell_price ?? 0);

                    $lineAmount = round($sellPrice * $qty, 4);
                    $discountAmount = round(($lineAmount * $discountPercent) / 100, 4);
                    $netAmount = round($lineAmount - $discountAmount, 4);
                    $vatAmount = round(($netAmount * $vatRate) / 100, 4);
                    $lineGrandTotal = round($netAmount + $vatAmount, 4);

                    $totalAmount += $lineAmount;
                    $totalDiscount += $discountAmount;
                    $totalVAT += $vatAmount;

                    SaleOrderLine::create([
                        'sale_order_id'      => $saleOrder->id,
                        'document_no'      => $saleOrder->document_no,
                        'product_id'         => $product->id,

                        'barcode'            => $product->bar_code,
                        'item_code'          => $product->code,
                        'name'               => $product->name,
                        'variant'            => $product->variant,
                        'description'        => $product->description,

                        'quantity'           => $qty,
                        'unit'               => $cartItem['unit'] ?? ($product->unit ?? 'NA'),
                        'category_name'      => optional($product->category)->name,

                        'cost'               => $unitCost,
                        'unit_price'         => $unitPrice,
                        'sell_price'         => $sellPrice,

                        'discount_percent'   => $discountPercent,
                        'discount_amount'    => $discountAmount,

                        'line_amount'        => $lineAmount,
                        'vat'                => $vatRate,
                        'vat_amount'         => $vatAmount,
                        'net_amount'         => $netAmount,
                        'grand_total_amount' => $lineGrandTotal,

                        'created_by'         => Auth::user()->username ?? 'System',
                    ]);
                }

                $grandTotal = round($totalAmount - $totalDiscount + $totalVAT, 4);
                $deposit = (float) ($payload['deposit_amount'] ?? 0);


                // Created Sale Order and Line

                if ($requestedStatus === 'Quotation') {
                    $deposit = 0;
                    $paymentStatus = 'N/A';
                    $finalStatus = 'Quotation';
                    $delivery = 'N/A';
                } else {
                    if ($deposit <= 0) {
                        $paymentStatus = 'Unpaid';
                        $finalStatus = 'Ordered';
                    } else {
                        $paymentStatus = 'Partial';
                        $finalStatus = 'Ordered';
                    }

                    $delivery = $payload['delivery_status'] ?? 'N/A';
                    if ($delivery == '') {
                        $delivery = 'NA';
                    }
                }

                $this->assertLinesWritten($linesWritten, $saleOrder->document_no);

                $saleOrder->update([
                    'total_amount'     => $totalAmount,
                    'vat_amount'       => $totalVAT,
                    'discount_amount'  => $totalDiscount,
                    'grand_total'      => $grandTotal,

                    'deposit_amount'   => $deposit,
                    'paid_amount'      => $deposit,
                    'balance_amount'   => max($grandTotal - $deposit, 0),

                    'payment_status'   => $paymentStatus,
                    'status'           => $finalStatus,
                    'delivery_status'  => $delivery,
                ]);
            });
            $this->new_cart = true;
            $this->cart = [];
            $this->count_cart = 0;

            $this->dispatch('ordered', [
                'message' => 'ដាក់ការកម្មង់ ជោកជ័យ : ' . $saleOrderNo
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('payment-error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function generateSaleOrderNo()
    {
        return Serial_No::next('sale_order');
    }



    // Both drive updateQuotation() / sale-order updates by id, so neither may be
    // settable from the client. updateQuotation() deletes and rewrites the
    // quotation's lines, which made a writable id destructive.
    #[Locked]
    public $loaded_sale_order_id = null;
    #[Locked]
    public $loaded_quotation_id = null;

    #[\Livewire\Attributes\On('load-sale-order-to-cart')]
    public function loadSaleOrderToCart($saleOrderId)
    {
        $warehouse_ids = Auth::user()->warehouses->pluck('id');


        $saleOrder = SaleOrderHeader::with([
            'lines.product.warehouses' => function ($q) use ($warehouse_ids) {
                $q->whereIn('warehouse_id', $warehouse_ids);
            },
        ])->find($saleOrderId);

        $posInfo = $this->saleDocProfile($saleOrder);


        if (!$saleOrder) {
            $this->dispatch('app-error', [
                'message' => 'Sale order not found'
            ]);
            return;
        }

        $this->factor = $saleOrder->factor;
        $this->currency = $saleOrder->currency_name;
        $this->currency_name = $saleOrder->currency_name;

        if ($this->new_cart && $this->cart_queue_no == 0) {
            $this->cart_queue_no = $this->incrementQueueTable();
            $this->getDocument($this->cart_queue_no);
            $this->new_cart = false;
        }

        $this->document_id = $saleOrder->id;
        $this->document_type = $saleOrder->status;
        $this->document_no = $saleOrder->document_no;
        $this->dispatch('change-document-type', [
            'document' => $saleOrder->status,
            'payment_status' => $saleOrder->payment_status,
        ]);


        $this->cart = [];
        $this->cart_mode = 'sale';
        $this->loaded_sale_order_id = $saleOrder->id;

        $this->customer_id = $saleOrder->customer_id;
        $this->customer_name = $saleOrder->contact_name ?? 'Walk-in Customer';
        $this->customer_phone = $saleOrder->phone ?? '';
        $this->customer_address1 = $saleOrder->address ?? '';
        $this->customer_address2 = '';
        $this->customer_contact_name = $saleOrder->contact_name ?? '';
        $this->customer_contact_phone = $saleOrder->phone ?? '';
        $this->document_type = $saleOrder->status;


        foreach ($saleOrder->lines as $line) {
            $product = $line->product;

            $qty = (float) ($line->quantity ?? 1);
            $price = (float) ($line->sell_price ?? $product?->sell_price ?? 0);
            $discountPercent = (float) ($line->discount_percent ?? $product?->discount_percent ?? 0);
            $vat = (float) ($line->vat ?? $product?->vat ?? 0);

            $stock = $product?->warehouses?->sum(function ($wh) {
                return (float) ($wh->pivot->qty ?? 0);
            }) ?? 0;

            $discountAmount = ($price * $discountPercent) / 100;
            $netPrice = $price - $discountAmount;
            $vatAmount = ($netPrice * $vat) / 100;

            $this->cart[] = [
                'id' => $line->product_id,
                'code' => $product?->code ?? '',
                'name' => $product?->name ?? $line->name,
                'type' => $product?->type ?? 'product',

                'price' => $price,
                'qty' => $qty,

                'discount_percent' => $discountPercent,
                'discount_price' => $netPrice,

                'order_no' => count($this->cart) + 1,

                'amount_line' => $qty * $price,
                'discount_amount_line' => $qty * $discountAmount,
                'net_amount_line' => $qty * $netPrice,
                'vat_amount_line' => $qty * $vatAmount,

                'vat' => $vat,
                'stock' => $stock,
                'unit' => $product?->unit ?? $line->unit ?? 'NA',
                'track_stock' => $product?->track_stock ?? 0,
            ];
        }

        $this->count_cart = count($this->cart);

        $this->dispatch('load-sale-order', [
            'message' => 'Sale order loaded to cart successfully',
            'header' => $saleOrder,
            'posInfo' => $posInfo,
        ]);
    }

    #[\Livewire\Attributes\On('load-sale-order-print')]
    public function loadSaleOrderPrint($saleOrderId)
    {
        $warehouse_ids = Auth::user()->warehouses->pluck('id');


        $saleOrder = SaleOrderHeader::with([
            'lines.product.warehouses' => function ($q) use ($warehouse_ids) {
                $q->whereIn('warehouse_id', $warehouse_ids);
            },
        ])->find($saleOrderId);

        $posInfo = $this->saleDocProfile($saleOrder);

        $this->deposit = $saleOrder->paid_amount;
        $this->balanceAmount_display = $saleOrder->balance_amount;
        if (!$saleOrder) {
            $this->dispatch('app-error', [
                'message' => 'Sale order not found'
            ]);
            return;
        }


        $this->factor = $saleOrder->factor;
        $this->currency = $saleOrder->currency_name;
        $this->currency_name = $saleOrder->currency_name;



        $this->document_id = $saleOrder->id;
        $this->document_type = $saleOrder->status;
        $this->document_no = $saleOrder->document_no;

        $this->cart = [];
        $this->cart_mode = 'sale';
        $this->loaded_sale_order_id = $saleOrder->id;

        $this->customer_id = $saleOrder->customer_id;
        $this->customer_name = $saleOrder->contact_name ?? 'Walk-in Customer';
        $this->customer_phone = $saleOrder->phone ?? '';
        $this->customer_address1 = $saleOrder->address ?? '';

        foreach ($saleOrder->lines as $line) {
            $product = $line->product;

            $qty = (float) ($line->quantity ?? 1);
            $price = (float) ($line->sell_price ?? $product?->sell_price ?? 0);
            $discountPercent = (float) ($line->discount_percent ?? 0);
            $vat = (float) ($line->vat ?? 0);

            $stock = $product?->warehouses?->sum(function ($wh) {
                return (float) ($wh->pivot->qty ?? 0);
            }) ?? 0;

            $discountAmount = ($price * $discountPercent) / 100;
            $netPrice = $price - $discountAmount;
            $vatAmount = ($netPrice * $vat) / 100;

            $this->cart[] = [
                'id' => $line->product_id,
                'code' => $product?->code ?? $line->item_code ?? '',
                'name' => $product?->name ?? $line->name,
                'type' => $product?->type ?? 'product',

                'price' => $price,
                'qty' => $qty,

                'discount_percent' => $discountPercent,
                'discount_price' => $netPrice,

                'order_no' => count($this->cart) + 1,

                'amount_line' => $qty * $price,
                'discount_amount_line' => $qty * $discountAmount,
                'net_amount_line' => $qty * $netPrice,
                'vat_amount_line' => $qty * $vatAmount,

                'vat' => $vat,
                'stock' => $stock,
                'unit' => $product?->unit ?? $line->unit ?? 'NA',
                'track_stock' => $product?->track_stock ?? 0,
            ];
        }

        $this->count_cart = count($this->cart);

        $this->dispatch('print-sale-order', [
            'message' => 'Sale order ready to print',
            'header' => $saleOrder,
            'posInfo' => $posInfo
        ]);
    }
    #[On('resetCustomer')]
    public function resetCustomer()
    {
        $this->customer_name = 'Walk-in Customer';
        $this->customer_id = null;
        $this->customer_phone = '';
        $this->customer_address1 = '';
        $this->customer_address2 = '';
        $this->customer_contact_name = '';
        $this->customer_contact_phone = '';
        $this->customer_city = '';
        $this->customer_discount_percent = 0;
    }
    #[\Livewire\Attributes\On('confirmDepositSaleOrder')]
    public function confirmDepositSaleOrder($payload)
    {
        abort_unless(Auth::user()->hasPermission('pos_sale.sell'), 403);

        if (empty($this->cart)) {
            $this->dispatch('payment-error', ['message' => 'Cart is empty']);
            return;
        }

        $saleOrderNo = '';
        $precision = 6;

        $r = fn($value) => round((float) $value, $precision);

        // =========================================================
        // 🔥 WYSIWYG UNIT STEPPING  (mirror of Blade $unitDisp)
        //  The screen rounds each UNIT price to the currency step
        //  (KHR → nearest 100) BEFORE multiplying by qty. The saved
        //  totals must follow the same rule, or the DB record won't
        //  match the printed invoice.
        // =========================================================
        $factor = (float) ($this->factor ?: 1);

        if ($factor >= 4000) {
            $step = 100;
            $decimal = 0;
        }   // KHR
        elseif ($factor == 1) {
            $step = 0;
            $decimal = 2;
        }   // USD
        elseif ($factor >= 100) {
            $step = 0;
            $decimal = 3;
        }   // mid-rate
        else {
            $step = 0;
            $decimal = 2;
        }   // fallback

        $snapUnit = function ($baseUnit) use ($factor, $step, $decimal) {
            if ($factor == 1) {
                return round((float) $baseUnit, $decimal);
            }
            $disp = (float) $baseUnit * $factor;                          // → display ccy
            $disp = $step > 0 ? round($disp / $step) * $step             // step round (KHR→100)
                : round($disp, $decimal);
            return $disp / $factor;                                       // → back to BASE
        };

        try {

            DB::transaction(function () use ($payload, &$saleOrderNo, $precision, $r, $snapUnit) {
                $riel = Currency::where('code', '៛')->firstOrFail();

                
                $totalAmount = 0;
                $totalDiscount = 0;
                $totalVAT = 0;

                $customer_id = !empty($payload['customer_id']) ? (int) $payload['customer_id'] : null;
                $customer_name = $payload['customer_name'] ?? 'Walk-in Customer';
                $customer_phone = $payload['customer_phone'] ?? 'NA';
                $customer_address = $payload['customer_address'] ?? null;

                $saleOrderNo = $this->generateSaleOrderNo();

                $saleOrder = SaleOrderHeader::create([
                    'document_no'      => $saleOrderNo,
                    'customer_id'      => $customer_id,
                    'contact_name'     => $customer_name,
                    'phone'            => $customer_phone,
                    'address'          => $customer_address,

                    'posting_date'     => $payload['document_date'] ?? now()->toDateString(),
                    'order_date'       => $payload['order_date'] ?? null,
                    'delivery_date'    => $payload['delivery_date'] ?? null,

                    'delivery_status'  => 'Pending',
                    'delivery_info'    => $payload['delivery_info'] ?? null,
                    'driver_name'      => $payload['driver_name'] ?? null,
                    'driver_phone'     => $payload['driver_phone'] ?? null,

                    'total_amount'     => 0,
                    'vat_amount'       => 0,
                    'discount_percent' => $r($payload['discount_percent'] ?? 0),
                    'discount_amount'  => 0,
                    'grand_total'      => 0,

                    'deposit_amount'   => 0,
                    'paid_amount'      => 0,
                    'balance_amount'   => 0,

                    'status'           => 'Deposit',
                    'payment_status'   => 'Partial',

                    'customer_type'    => $payload['customer_type'] ?? null,
                    'payment_method'   => $payload['paymentMethod'] ?? null,
                    'currency_name'    => $riel->code,
                    'factor'           => $riel->factor,
                    'remarks'          => $payload['remark'] ?? null,
                    'created_by'       => Auth::user()->username ?? 'System',
                    'created_user_id'  => Auth::user()->id,
                ]);

                $calculatedLines = [];

                $linesWritten = 0;
                foreach ($this->cart as $cartItem) {
                    $product = $this->requireProduct($cartItem['id']);
                    $linesWritten++;

                    $qty = max(0.01, $r($cartItem['qty'] ?? 1));

                    // 🔥 stepped unit price (base USD) — matches the screen line
                    $sellPrice = $r($snapUnit($cartItem['price'] ?? $cartItem['sell_price'] ?? 0));

                    $vatRate = $r($cartItem['vat'] ?? 0);
                    $discountPercent = min(max(0, $r($cartItem['discount_percent'] ?? 0)), 100);

                    $unitCost = $r($product->cost ?? 0);
                    $unitPrice = $r($product->sell_price ?? 0);

                    $lineAmount = $r($sellPrice * $qty);
                    $discountAmount = $r(($lineAmount * $discountPercent) / 100);
                    $netAmount = $r($lineAmount - $discountAmount);
                    $vatAmount = $r(($netAmount * $vatRate) / 100);
                    $lineGrandTotal = $r($netAmount + $vatAmount);

                    $totalAmount = $r($totalAmount + $lineAmount);
                    $totalDiscount = $r($totalDiscount + $discountAmount);
                    $totalVAT = $r($totalVAT + $vatAmount);

                    $saleLine = SaleOrderLine::create([
                        'sale_order_id'      => $saleOrder->id,
                        'document_no'        => $saleOrder->document_no,
                        'product_id'         => $product->id,
                        'barcode'            => $product->bar_code,
                        'item_code'          => $product->code,
                        'name'               => $product->name,
                        'variant'            => $product->variant,
                        'description'        => $product->description,

                        'quantity'           => $qty,
                        'quantity_shiped'    => 0,

                        'unit'               => $cartItem['unit'] ?? ($product->unit ?? 'NA'),
                        'category_name'      => optional($product->category)->name,

                        'cost'               => $unitCost,
                        'unit_price'         => $unitPrice,
                        'sell_price'         => $sellPrice,

                        'discount_percent'   => $discountPercent,
                        'discount_amount'    => $discountAmount,
                        'line_amount'        => $lineAmount,
                        'vat'                => $vatRate,
                        'vat_amount'         => $vatAmount,
                        'net_amount'         => $netAmount,
                        'grand_total_amount' => $lineGrandTotal,

                        'created_by'         => Auth::user()->username ?? 'System',
                    ]);

                    $calculatedLines[] = [
                        'cartItem' => $cartItem,
                        'product' => $product,
                        'saleLine' => $saleLine,
                        'qty' => $qty,
                        'sellPrice' => $sellPrice,
                        'vatRate' => $vatRate,
                        'discountPercent' => $discountPercent,
                        'unitCost' => $unitCost,
                        'unitPrice' => $unitPrice,
                        'lineAmount' => $lineAmount,
                        'discountAmount' => $discountAmount,
                        'netAmount' => $netAmount,
                        'vatAmount' => $vatAmount,
                        'lineGrandTotal' => $lineGrandTotal,
                    ];
                }

                // Bill discount — a discretionary top-level adjustment on top
                // of the per-item discounts already folded into $totalDiscount
                // above (e.g. writing off the last few dollars to close a
                // deal). Applied to the header/invoice totals only; it does
                // not redistribute into individual line amounts. Same
                // permission as per-line discounts — silently ignored (not
                // an error) if the user isn't allowed to grant discounts.
                $billDiscount = Auth::user()->hasPermission('pos_sale.edit_discount')
                    ? max(0, $r($payload['bill_discount'] ?? 0))
                    : 0;
                $totalDiscount = $r($totalDiscount + $billDiscount);

                $grandTotal = $r($totalAmount - $totalDiscount + $totalVAT);

                $paidAmount = $r($payload['deposit_amount'] ?? 0);
                $paidAmount = max(0, $paidAmount);                      // ← removed the min($paidAmount, $grandTotal) cap
                $balanceAmount = $r(max($grandTotal - $paidAmount, 0)); // stays 0 when overpaid, never negative
                if ($paidAmount <= 0) {
                    $paymentStatus = 'Unpaid';
                    $finalStatus = 'Deposit';
                } elseif ($this->isSettled($paidAmount, $grandTotal)) {   // overpay lands here → Paid / Completed
                    $paymentStatus = 'Paid';
                    $finalStatus = 'Completed';
                    // Absorb the sub-note rounding dust, so no report can
                    // recompute a phantom cent from grand_total - paid_amount.
                    $paidAmount = max($paidAmount, $grandTotal);
                    $balanceAmount = 0;
                } else {
                    $paymentStatus = 'Partial';
                    $finalStatus = 'Deposit';
                }
                $this->assertLinesWritten($linesWritten, $saleOrder->document_no);

                $saleOrder->update([
                    'total_amount'     => $totalAmount,
                    'vat_amount'       => $totalVAT,
                    'discount_amount'  => $totalDiscount,
                    'grand_total'      => $grandTotal,

                    'deposit_amount'   => $paidAmount,
                    'paid_amount'      => $paidAmount,
                    'balance_amount'   => $balanceAmount,

                    'payment_status'   => $paymentStatus,
                    'status'           => $finalStatus,
                    'delivery_status'  => 'Pending',
                ]);


                $invoice = InvoiceHeader::create([
                    'sale_order_id'    => $saleOrder->id,
                    'invoice_number'   => $this->generateInvoiceNumber(),
                    'source_no'        => $saleOrder->document_no,
                    'invoice_date'     => $payload['document_date'] ?? now()->toDateString(),

                    'customer_id'      => $customer_id,
                    'contact_name'     => $customer_name,
                    'phone'            => $customer_phone,
                    'address'          => $customer_address,

                    'total_amount'     => $totalAmount,
                    'vat_amount'       => $totalVAT,
                    'discount_percent' => $r($payload['discount_percent'] ?? 0),
                    'discount_amount'  => $totalDiscount,
                    'grand_total'      => $grandTotal,

                    'payment_method'   => $payload['paymentMethod'] ?? null,
                    'customer_type'    => $payload['customer_type'] ?? null,
                    'currency_name'    => $riel->code,
                    'factor'           => $riel->factor,

                    'created_by'       => Auth::user()->username ?? 'System',
                    'created_user_id'  => Auth::user()->id,
                    'remarks'          => $payload['remark'] ?? null,
                ]);

                foreach ($calculatedLines as $line) {
                    $cartItem = $line['cartItem'];
                    $product = $line['product'];

                    $qty = $line['qty'];
                    $sellPrice = $line['sellPrice'];
                    $vatRate = $line['vatRate'];
                    $discountPercent = $line['discountPercent'];
                    $unitCost = $line['unitCost'];
                    $unitPrice = $line['unitPrice'];

                    $lineAmount = $line['lineAmount'];
                    $discountAmount = $line['discountAmount'];
                    $netAmount = $line['netAmount'];
                    $vatAmount = $line['vatAmount'];
                    $grandTotalLine = $line['lineGrandTotal'];

                    InvoiceLine::create([
                        'sale_invoice_id'    => $invoice->id,
                        'document_no'        => $invoice->invoice_number,
                        'product_id'         => $product->id,
                        'barcode'            => $product->bar_code,
                        'item_code'          => $product->code,
                        'name'               => $product->name,
                        'variant'            => $product->variant,
                        'description'        => $product->description,

                        'quantity'           => $qty,
                        'unit'               => $cartItem['unit'] ?? ($product->unit ?? 'NA'),
                        'category_name'      => optional($product->category)->name,

                        'cost'               => $unitCost,
                        'unit_price'         => $unitPrice,
                        'sell_price'         => $sellPrice,

                        'discount_percent'   => $discountPercent,
                        'discount_amount'    => $discountAmount,
                        'line_amount'        => $lineAmount,
                        'vat'                => $vatRate,
                        'vat_amount'         => $vatAmount,
                        'net_amount'         => $netAmount,
                        'grand_total_amount' => $grandTotalLine,

                        'created_by'         => Auth::user()->username ?? 'System',
                    ]);

                    if ((int) ($product->track_stock ?? 0) !== 1) {
                        continue;
                    }

                    $ledgerRows = [];
                    $cartLots = $cartItem['lots'] ?? [];
                    $saleQty = $qty;

                    // One document, one warehouse — never FEFO across sites.
                    $warehouse_ids = $this->saleWarehouseIds();
                    if (!empty($cartLots)) {
                        foreach ($cartLots as $lot) {

                            // Lot ids come from the browser (set-item-lots) and are stored
                            // verbatim on the cart line, so the row must be pinned to THIS
                            // product too. Warehouse alone was checked, which meant a stale
                            // cart index could deduct a different product's lot while the
                            // invoice and ledger recorded this one.
                            $warehouseProduct = WarehouseProduct::where('id', $lot['id'] ?? null)
                                ->where('product_id', $product->id)
                                ->whereIn('warehouse_id', $warehouse_ids)
                                ->lockForUpdate()->first();

                            if (!$warehouseProduct) {
                                throw new \Exception(
                                    "Selected lot is not valid for {$product->name} — reopen the lot picker and try again."
                                );
                            }

                            $lotQty = $r($lot['qty'] ?? 0);

                            if ($lotQty <= 0) {
                                continue;
                            }

                            if ($r($warehouseProduct->quantity) < $lotQty) {
                                throw new \Exception("Not enough stock in Lot {$warehouseProduct->lot}");
                            }

                            $warehouseProduct->quantity = $r($warehouseProduct->quantity - $lotQty);
                            $warehouseProduct->save();

                            $ledgerRows[] = [
                                'warehouse_id' => $warehouseProduct->warehouse_id,
                                'bin_id' => $warehouseProduct->bin_id,
                                'lot' => $warehouseProduct->lot,
                                'expire_date' => $warehouseProduct->expire,
                                'quantity' => $lotQty,
                            ];

                            $this->syncPurchaseRemainingQty(
                                $product->id,
                                $warehouseProduct->lot,
                                $warehouseProduct->warehouse_id
                            );
                        }

                        if ($r(collect($ledgerRows)->sum('quantity')) != $r($saleQty)) {
                            throw new \Exception("Selected lot quantity not equal sale quantity for {$product->name}");
                        }
                    } else {

                        $warehouseProducts = WarehouseProduct::where('product_id', $product->id)
                            ->whereIn('warehouse_id', $warehouse_ids)
                            ->where('quantity', '>', 0)
                            ->orderByRaw('CASE WHEN expire IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('expire', 'asc')
                            ->lockForUpdate()
                            ->get();

                        $remaining = $saleQty;

                        foreach ($warehouseProducts as $wp) {
                            if ($remaining <= 0) break;

                            $deductQty = $r(min($r($wp->quantity), $remaining));

                            if ($deductQty <= 0) continue;

                            $wp->quantity = $r($wp->quantity - $deductQty);
                            $wp->save();

                            $ledgerRows[] = [
                                'warehouse_id' => $wp->warehouse_id,
                                'bin_id' => $wp->bin_id,
                                'lot' => $wp->lot,
                                'expire_date' => $wp->expire,
                                'quantity' => $deductQty,
                            ];

                            $remaining = $r($remaining - $deductQty);

                            $this->syncPurchaseRemainingQty(
                                $product->id,
                                $wp->lot,
                                $wp->warehouse_id
                            );
                        }

                        if ($r($remaining) > 0) {
                            throw new \Exception("{$product->name} មិនមានស្តុកគ្រប់គ្រាន់ទេ");
                        }
                    }


                    foreach ($ledgerRows as $row) {
                        $rowQty = $r($row['quantity']);

                        $rowUnitCost = $r($this->getLotUnitCost(
                            $product->id,
                            $row['lot'] ?? null,
                            $row['warehouse_id'] ?? null
                        ));

                        $warehouseName = Warehouse::where('id', $row['warehouse_id'])->value('name');
                        $rowBinName = !empty($row['bin_id']) ? optional(\App\Models\Bin::find($row['bin_id']))->name : null;

                        $rowLineAmount = $saleQty > 0 ? $r(($lineAmount / $saleQty) * $rowQty) : 0;
                        $rowDiscountAmount = $saleQty > 0 ? $r(($discountAmount / $saleQty) * $rowQty) : 0;
                        $rowNetAmount = $saleQty > 0 ? $r(($netAmount / $saleQty) * $rowQty) : 0;
                        $rowVatAmount = $saleQty > 0 ? $r(($vatAmount / $saleQty) * $rowQty) : 0;
                        $rowGrandTotal = $saleQty > 0 ? $r(($grandTotalLine / $saleQty) * $rowQty) : 0;

                        $ledger = $this->createLedgerEntry([
                            'posting_date' => $payload['document_date'] ?? now()->toDateString(),
                            'document_type' => 'Sales Invoice',
                            'source_no' => $saleOrder->document_no,
                            'document_no' => $invoice->invoice_number,

                            'source_id' => $invoice->id,
                            'source_table' => 'sale_invoice_headers',

                            'product_id' => $product->id,
                            'barcode' => $product->bar_code,
                            'item_code' => $product->code,
                            'name' => $product->name,
                            'variant' => $product->variant,
                            'description' => $product->description,
                            'unit' => $cartItem['unit'] ?? ($product->unit ?? 'NA'),
                            'category_name' => optional($product->category)->name,
                            'type' => 'product',

                            'warehouse_id' => $row['warehouse_id'],
                            'warehouse_name' => $warehouseName,
                            'bin_id' => $row['bin_id'] ?? null,
                            'bin_name' => $rowBinName,
                            'lot' => $row['lot'],
                            'expire_date' => $row['expire_date'],

                            'quantity' => $r(-1 * $rowQty),
                            'remaining_quantity' => 0,
                            'entry_type' => 'negative',

                            'currency_name'  => $riel->code,
                            'factor'         => $riel->factor,

                            // Negative — goods leaving stock. Sales value stays
                            // in net_amount / grand_total_amount below.
                            'unit_cost' => abs($rowUnitCost),
                            'cost_amount' => -round(abs($rowQty) * abs($rowUnitCost), 6),
                            'unit_price' => $unitPrice,
                            'sell_price' => $sellPrice,

                            'discount_percent' => $discountPercent,
                            'discount_amount' => $rowDiscountAmount,
                            'vat' => $vatRate,
                            'vat_amount' => $rowVatAmount,

                            'line_amount' => $rowLineAmount,
                            'net_amount' => $rowNetAmount,
                            'grand_total_amount' => $rowGrandTotal,

                            'customer_id' => $customer_id,
                            'customer_name' => $customer_name,
                            'customer_phone' => $customer_phone,
                            'customer_address' => $customer_address,

                            'payment_method' => $payload['paymentMethod'] ?? null,
                            'remark' => 'Deposit sale order',
                            'created_by' => Auth::user()->username ?? 'System',
                            'created_user_id'       => Auth::user()->id,
                        ]);

                        $ledger->update([
                            'entry_no' => $ledger->id,
                        ]);
                    }

                    SaleOrderLine::where('sale_order_id', $saleOrder->id)
                        ->where('product_id', $product->id)
                        ->update([
                            'quantity_shiped' => DB::raw('ROUND(COALESCE(quantity_shiped, 0) + ' . $qty . ', 6)')
                        ]);
                }
                $posInfo = $this->saleDocProfile($saleOrder);
                $this->dispatch('pass_sale_header', [
                    'header' => $saleOrder,
                    'posInfo' => $posInfo
                ]);
                $this->cart_queue_no = 0;
                $this->getDocument($this->cart_queue_no);
            });

            if ($this->loaded_quotation_id) {
                Quotation::where('id', $this->loaded_quotation_id)->update(['status' => 'Completed']);
                $this->loaded_quotation_id = null;
            }

            $this->dispatch('ordered', [
                'message' => 'Deposit ' . $saleOrderNo . ' បានរក្សាទុក និងដកស្តុករួច ',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('payment-error', [
                'message' => $e->getMessage(),
            ]);
        }
    }
    #[\Livewire\Attributes\On('updateDepositSaleOrder')]
    public function updateDepositSaleOrder($payload)
    {
        abort_unless(Auth::user()->hasPermission('pos_sale.sell'), 403);

        $saleOrder = null;
        $paidAmount = 0;
        $grandTotal = 0;

        try {
            DB::transaction(function () use ($payload, &$saleOrder, &$paidAmount, &$grandTotal) {

                $saleOrder = SaleOrderHeader::where('id', $this->document_id)->lockForUpdate()->first();

                if (!$saleOrder) {
                    throw new \Exception('Sale Order not found');
                }

                // 'Deposit' already has stock deducted + an invoice (via
                // confirmDepositSaleOrder) — just accumulate payment below as
                // before. 'Ordered' never went through that step (it's the
                // no-stock-yet "Order" flow), so ship it first — this is the
                // "Ship & Pay" combined action — then fall through to the
                // same payment accumulation. Completed/Cancelled/Returned are
                // already closed and stay rejected.
                if (!in_array($saleOrder->status, ['Deposit', 'Ordered'], true)) {
                    throw new \Exception(
                        'This order is ' . $saleOrder->status . ' — only Ordered or Deposit orders can take payment here.'
                    );
                }

                if ($saleOrder->status === 'Ordered') {
                    $this->shipOrderStock($saleOrder);
                }

                // Bill discount entered now (e.g. writing off the last few
                // dollars to close out an order) — was previously only ever
                // shown in the on-screen "remaining" preview and never sent
                // to the server, so it never actually reduced what was owed.
                // Same permission as per-line discounts.
                $newDiscount = Auth::user()->hasPermission('pos_sale.edit_discount')
                    ? max(0, (float) ($payload['bill_discount'] ?? 0))
                    : 0;

                $grandTotal = (float) ($saleOrder->grand_total ?? 0);
                if ($newDiscount > 0) {
                    $grandTotal = max($grandTotal - $newDiscount, 0);
                }

                // new payment entered now
                $newPaid = (float) ($payload['deposit_amount'] ?? 0);

                // accumulate old + new
                $paidAmount = (float) ($saleOrder->paid_amount ?? 0) + $newPaid;

                $paidAmount = max($paidAmount, 0);
                $paidAmount = min($paidAmount, $grandTotal);

                // Check the "fully covered" case first — a big-enough discount
                // can bring grandTotal down to 0 (or below paidAmount) with no
                // cash payment at all, which must still close the order out
                // rather than falling into the "Unpaid" branch below.
                if ($this->isSettled($paidAmount, $grandTotal)) {
                    $paymentStatus = 'Paid';
                    $finalStatus = 'Completed';
                    // Absorb the sub-note rounding dust, so no report can
                    // recompute a phantom cent from grand_total - paid_amount.
                    $paidAmount = max($paidAmount, $grandTotal);
                } elseif ($paidAmount <= 0) {
                    $paymentStatus = 'Unpaid';
                    $finalStatus = 'Deposit';
                } else {
                    $paymentStatus = 'Partial';
                    $finalStatus = 'Deposit';
                }

                $saleOrder->update([
                    'discount_amount' => (float) ($saleOrder->discount_amount ?? 0) + $newDiscount,
                    'grand_total'    => $grandTotal,
                    'deposit_amount' => $paidAmount,
                    'paid_amount'    => $paidAmount,
                    'balance_amount' => max($grandTotal - $paidAmount, 0),

                    'payment_status' => $paymentStatus,
                    'status'         => $finalStatus,

                    'payment_method' => $payload['paymentMethod'] ?? $saleOrder->payment_method,
                    'remarks'        => $payload['remark'] ?? $saleOrder->remarks,
                ]);
            });

            if ($paidAmount < $grandTotal) {
                $msg = 'Deposit : ' . $saleOrder->document_no;
            } else {
                $msg = 'បានបង់ប្រាក់គ្រប់ចំនួន : ' . $saleOrder->document_no;
            }

            $this->dispatch('deposit-success', [
                'message' => $msg
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('payment-error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    // "Ship" — deducts stock / writes the invoice for an Ordered (no-stock-yet)
    // order, and leaves payment fields exactly as they were. Operates on
    // whichever order was loaded via load-sale-order-to-cart ($this->document_id).
    #[On('ship-sale-order')]
    public function shipSaleOrder()
    {
        abort_unless(Auth::user()->hasPermission('pos_sale.sell'), 403);

        $saleOrder = null;

        try {
            DB::transaction(function () use (&$saleOrder) {
                $saleOrder = SaleOrderHeader::where('id', $this->document_id)->lockForUpdate()->first();

                if (!$saleOrder) {
                    throw new \Exception('Sale Order not found');
                }

                if (!in_array($saleOrder->status, ['Ordered', 'Quotation'], true)) {
                    throw new \Exception('This order already has stock deducted (' . $saleOrder->status . ').');
                }

                $this->shipOrderStock($saleOrder);

                // Payment fields untouched — status just needs to reflect that
                // an invoice/stock now exist, using whatever payment_status
                // this order already had (possibly already Paid if a Pay-only
                // action ran first).
                $saleOrder->update([
                    'status' => $saleOrder->payment_status === 'Paid' ? 'Completed' : 'Deposit',
                ]);
            });

            $this->dispatch('deposit-success', [
                'message' => 'Stock shipped for ' . $saleOrder->document_no,
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('payment-error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function openQuotationPreview()
    {
        $this->dispatch(
            'open-quotation-preview',
            cart: $this->cart,
            totals: $this->totals,
            factor: $this->factor,
            currency: $this->currency,
            previewOnly: false,
        );
    }

    /**
     * The same modal the Quote button opens, minus the quotation.
     *
     * Preview used to print straight from the cart bar, so there was nowhere to
     * put the customer name or remark that the printed document shows. It now
     * opens the identical modal — same customer fields, same line table, same
     * totals — with the Save Quotation button hidden and the title reading
     * Preview, so nothing about it offers to issue a document.
     *
     * Needs no permission, same as previewQuotation(): nothing is written.
     */
    public function openDocumentPreview()
    {
        $this->dispatch(
            'open-quotation-preview',
            cart: $this->cart,
            totals: $this->totals,
            factor: $this->factor,
            currency: $this->currency,
            previewOnly: true,
        );
    }

    /**
     * Build the quotation exactly as saveQuotation would, but write nothing and
     * hand it straight to the printer.
     *
     * Listens on 'previewQuotation'. The attribute here used to read
     * #[On('saveQuotation')], which wired the SAVE event to this method and left
     * saveQuotation() with no listener at all — pressing "Save Quotation" only
     * printed a preview and stored nothing, and the Preview button did nothing,
     * since a dispatched Livewire event only reaches a matching #[On].
     *
     * No permission check: this renders the caller's OWN cart, reads no stored
     * document, issues no number and writes nothing, so a user who may not raise
     * a quotation can still show a customer what the figures come to. Issuing
     * one is still gated — see saveQuotation().
     *
     * The per-line arithmetic mirrors saveQuotation on purpose — a preview that
     * totals differently from the document it previews is worse than none.
     */
    #[On('previewQuotation')]
    public function previewQuotation($payload = [])
    {
        $figures = $this->previewFigures();

        if ($figures === null) {
            return;
        }

        $this->dispatch('quotation-preview', [
            'header' => [
                // No number is issued — nothing was saved, and printing a real
                // looking quotation number on an unsaved document invites someone
                // to quote against it.
                'quotation_no'    => 'PREVIEW',
            ] + $this->previewCustomer($payload) + $figures['totals'],
            'lines' => $figures['lines'],
        ]);
    }

    /**
     * The same preview, on the INVOICE form rather than the quotation form.
     *
     * What a cashier shows a customer before taking payment is the invoice they
     * are about to be given, not a quotation — so the Preview button prints the
     * Khmer invoice layout, with the same figures the sale would charge.
     *
     * document_no is 'PREVIEW' for the same reason quotation_no is: nothing has
     * been issued, and a real-looking invoice number on an unsaved document is
     * an invitation to pay against it. Nothing is written here either, so this
     * needs no permission — see previewQuotation().
     */
    #[On('previewInvoice')]
    public function previewInvoice($payload = [])
    {
        $figures = $this->previewFigures();

        if ($figures === null) {
            return;
        }

        $totals = $figures['totals'];

        $this->dispatch('invoice-preview', [
            'header' => [
                'document_no'    => 'PREVIEW',
                'posting_date'   => now()->toDateString(),
                // The invoice form prints its money through the document's own
                // factor, so it has to travel with the payload — without it a
                // riel cart would print its figures as dollars.
                'factor'         => $this->factor,
                'currency_name'  => $this->currency_name,
                // Nothing is paid on a cart that has not been through payment, so
                // the form prints Deposit 0 and the full balance as still owed.
                'payment_status' => 'Unpaid',
                'paid_amount'    => 0,
            ] + $this->previewCustomer($payload, invoice: true) + $totals,
            'lines' => $figures['lines'],
        ]);
    }

    /**
     * Customer block for a preview.
     *
     * The modal sends its own fields; the Preview button on the cart bar sends
     * none, so fall back to the customer already selected on the cart rather
     * than printing a preview addressed to "Walk-in Customer" for a named one.
     *
     * The two forms name this field differently — the quotation reads
     * customer_name, the invoice reads contact_name.
     */
    private function previewCustomer(array $payload, bool $invoice = false): array
    {
        $name = $payload['customer_name'] ?? ($this->customer_name ?: 'Walk-in Customer');

        return [
            $invoice ? 'contact_name' : 'customer_name' => $name,
            'phone'   => $payload['customer_phone'] ?? ($this->customer_phone ?? ''),
            'address' => $payload['customer_address'] ?? ($this->customer_address1 ?? ''),
            'remarks' => $payload['remark'] ?? '',
        ];
    }

    /**
     * Lines and totals for a preview, shared by both forms so the quotation and
     * the invoice can never quote different figures for the same cart.
     *
     * Returns null (having reported it) when there is nothing to preview.
     */
    private function previewFigures(): ?array
    {
        if (empty($this->cart)) {
            $this->dispatch('payment-error', ['message' => 'Cart is empty']);
            return null;
        }

        $lines = [];
        $totalAmount = 0.0;
        $totalDiscount = 0.0;
        $totalVAT = 0.0;

        foreach ($this->cart as $cartItem) {
            $qty = max(0.01, (float) ($cartItem['qty'] ?? 1));
            $sellPrice = (float) ($cartItem['price'] ?? $cartItem['sell_price'] ?? 0);
            $vatRate = (float) ($cartItem['vat'] ?? 0);
            $discountPercent = (float) ($cartItem['discount_percent'] ?? 0);

            $lineAmount = round($sellPrice * $qty, 4);
            $discountAmount = round(($lineAmount * $discountPercent) / 100, 4);
            $netAmount = round($lineAmount - $discountAmount, 4);
            $vatAmount = round(($netAmount * $vatRate) / 100, 4);

            $totalAmount += $lineAmount;
            $totalDiscount += $discountAmount;
            $totalVAT += $vatAmount;

            $lines[] = [
                'name'               => $cartItem['name'] ?? '',
                'unit'               => $cartItem['unit'] ?? '',
                'quantity'           => $qty,
                'sell_price'         => $sellPrice,
                // The invoice form lists services below the goods subtotal rather
                // than among the numbered items, and needs this to tell them apart.
                'type'               => $cartItem['type'] ?? 'product',
                'grand_total_amount' => round($netAmount + $vatAmount, 4),
            ];
        }

        return [
            'lines'  => $lines,
            'totals' => [
                'total_amount'    => round($totalAmount, 4),
                'discount_amount' => round($totalDiscount, 4),
                'vat_amount'      => round($totalVAT, 4),
                'grand_total'     => round($totalAmount - $totalDiscount + $totalVAT, 4),
            ],
        ];
    }

    #[On('saveQuotation')]
    public function saveQuotation($payload)
    {
        abort_unless(Auth::user()->hasPermission('quotation.create'), 403);

        if (empty($this->cart)) {
            $this->dispatch('payment-error', ['message' => 'Cart is empty']);
            return;
        }

        $quotationNo = '';
        $quotationId = null;
        try {
            DB::transaction(function () use ($payload, &$quotationNo, &$quotationId) {

                $totalAmount = 0;
                $totalDiscount = 0;
                $totalVAT = 0;

                $customer_id = !empty($payload['customer_id']) ? (int) $payload['customer_id'] : null;
                $customer_name = $payload['customer_name'] ?? 'Walk-in Customer';
                $customer_phone = $payload['customer_phone'] ?? 'NA';
                $customer_address = $payload['customer_address'] ?? null;

                $quotationNo = $this->generateQuotationNo();

                $quotation = Quotation::create([
                    'quotation_no'     => $quotationNo,

                    'customer_id'      => $customer_id,
                    'customer_name'    => $customer_name,
                    'phone'            => $customer_phone,
                    'address'          => $customer_address,

                    'quotation_date'   => $payload['quotation_date'] ?? now()->toDateString(),
                    'valid_until'      => $payload['valid_until'] ?? null,

                    'total_amount'     => 0,
                    'vat_amount'       => 0,
                    'discount_percent' => $payload['discount_percent'] ?? 0,
                    'discount_amount'  => 0,
                    'grand_total'      => 0,

                    'status'           => 'Quotation',

                    // Quotations always store the real transaction currency (USD).
                    // Riel (or any other DB currency) is only a display/input
                    // convenience in the cart UI — it never changes what's saved.
                    'currency_name'    => 'USD',
                    'factor'           => 1,

                    'remarks'          => $payload['remark'] ?? null,
                    'created_by'       => Auth::user()->username ?? 'System',
                    'created_user_id'  => (string) Auth::id(),
                ]);

                $quotationId = $quotation->id;

                $linesWritten = 0;
                foreach ($this->cart as $cartItem) {
                    $product = $this->requireProduct($cartItem['id']);
                    $linesWritten++;

                    $qty = max(0.01, (float) ($cartItem['qty'] ?? 1));
                    $sellPrice = (float) ($cartItem['price'] ?? $cartItem['sell_price'] ?? 0);
                    $vatRate = (float) ($cartItem['vat'] ?? 0);
                    $discountPercent = (float) ($cartItem['discount_percent'] ?? 0);

                    $unitCost = (float) ($product->cost ?? 0);
                    $unitPrice = (float) ($product->sell_price ?? 0);

                    $lineAmount = round($sellPrice * $qty, 4);
                    $discountAmount = round(($lineAmount * $discountPercent) / 100, 4);
                    $netAmount = round($lineAmount - $discountAmount, 4);
                    $vatAmount = round(($netAmount * $vatRate) / 100, 4);
                    $lineGrandTotal = round($netAmount + $vatAmount, 4);

                    $totalAmount += $lineAmount;
                    $totalDiscount += $discountAmount;
                    $totalVAT += $vatAmount;

                    QuotationLine::create([
                        'quotation_id'       => $quotation->id,
                        'product_id'         => $product->id,

                        'barcode'            => $product->bar_code,
                        'item_code'          => $product->code,
                        'name'               => $product->name,
                        'variant'            => $product->variant,
                        'description'        => $product->description,

                        'quantity'           => $qty,
                        'unit'               => $cartItem['unit'] ?? ($product->unit ?? 'NA'),
                        'category_name'      => optional($product->category)->name,

                        'cost'               => $unitCost,
                        'unit_price'         => $unitPrice,
                        'sell_price'         => $sellPrice,

                        'discount_percent'   => $discountPercent,
                        'discount_amount'    => $discountAmount,

                        'line_amount'        => $lineAmount,
                        'vat'                => $vatRate,
                        'vat_amount'         => $vatAmount,
                        'net_amount'         => $netAmount,
                        'grand_total_amount' => $lineGrandTotal,

                        'created_by'         => Auth::user()->username ?? 'System',
                    ]);
                }

                $grandTotal = round($totalAmount - $totalDiscount + $totalVAT, 4);

                $this->assertLinesWritten($linesWritten, $quotation->quotation_no);

                $quotation->update([
                    'total_amount'    => $totalAmount,
                    'vat_amount'      => $totalVAT,
                    'discount_amount' => $totalDiscount,
                    'grand_total'     => $grandTotal,
                ]);
            });

            $this->new_cart = true;
            $this->cart = [];
            $this->count_cart = 0;
            $this->loaded_quotation_id = null;

            $this->dispatch('quotation-saved', [
                'message' => 'Quotation saved : ' . $quotationNo,
                'id'      => $quotationId,
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('payment-error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    #[On('updateQuotation')]
    public function updateQuotation($payload)
    {
        abort_unless(Auth::user()->hasPermission('quotation.edit'), 403);

        if (empty($this->cart)) {
            $this->dispatch('payment-error', ['message' => 'Cart is empty']);
            return;
        }

        if (!$this->loaded_quotation_id) {
            $this->dispatch('payment-error', ['message' => 'No quotation loaded to update']);
            return;
        }

        try {
            DB::transaction(function () use ($payload) {
                $quotation = Quotation::findOrFail($this->loaded_quotation_id);

                $totalAmount = 0;
                $totalDiscount = 0;
                $totalVAT = 0;

                $quotation->lines()->delete();

                $linesWritten = 0;
                foreach ($this->cart as $cartItem) {
                    $product = $this->requireProduct($cartItem['id']);
                    $linesWritten++;

                    $qty = max(0.01, (float) ($cartItem['qty'] ?? 1));
                    $sellPrice = (float) ($cartItem['price'] ?? $cartItem['sell_price'] ?? 0);
                    $vatRate = (float) ($cartItem['vat'] ?? 0);
                    $discountPercent = (float) ($cartItem['discount_percent'] ?? 0);

                    $unitCost = (float) ($product->cost ?? 0);
                    $unitPrice = (float) ($product->sell_price ?? 0);

                    $lineAmount = round($sellPrice * $qty, 4);
                    $discountAmount = round(($lineAmount * $discountPercent) / 100, 4);
                    $netAmount = round($lineAmount - $discountAmount, 4);
                    $vatAmount = round(($netAmount * $vatRate) / 100, 4);
                    $lineGrandTotal = round($netAmount + $vatAmount, 4);

                    $totalAmount += $lineAmount;
                    $totalDiscount += $discountAmount;
                    $totalVAT += $vatAmount;

                    QuotationLine::create([
                        'quotation_id'       => $quotation->id,
                        'product_id'         => $product->id,

                        'barcode'            => $product->bar_code,
                        'item_code'          => $product->code,
                        'name'               => $product->name,
                        'variant'            => $product->variant,
                        'description'        => $product->description,

                        'quantity'           => $qty,
                        'unit'               => $cartItem['unit'] ?? ($product->unit ?? 'NA'),
                        'category_name'      => optional($product->category)->name,

                        'cost'               => $unitCost,
                        'unit_price'         => $unitPrice,
                        'sell_price'         => $sellPrice,

                        'discount_percent'   => $discountPercent,
                        'discount_amount'    => $discountAmount,

                        'line_amount'        => $lineAmount,
                        'vat'                => $vatRate,
                        'vat_amount'         => $vatAmount,
                        'net_amount'         => $netAmount,
                        'grand_total_amount' => $lineGrandTotal,

                        'created_by'         => Auth::user()->username ?? 'System',
                    ]);
                }

                $grandTotal = round($totalAmount - $totalDiscount + $totalVAT, 4);

                $this->assertLinesWritten($linesWritten, $quotation->quotation_no);

                $quotation->update([
                    'customer_id'      => !empty($payload['customer_id']) ? (int) $payload['customer_id'] : $quotation->customer_id,
                    'customer_name'    => $payload['customer_name'] ?? $quotation->customer_name,
                    'phone'            => $payload['customer_phone'] ?? $quotation->phone,
                    'address'          => $payload['customer_address'] ?? $quotation->address,

                    'total_amount'     => $totalAmount,
                    'vat_amount'       => $totalVAT,
                    'discount_amount'  => $totalDiscount,
                    'grand_total'      => $grandTotal,

                    'remarks'          => $payload['remark'] ?? $quotation->remarks,
                ]);
            });

            $quotationId = $this->loaded_quotation_id;
            $quotationNo = Quotation::find($quotationId)?->quotation_no;

            $this->new_cart = true;
            $this->cart = [];
            $this->count_cart = 0;
            $this->loaded_quotation_id = null;

            $this->dispatch('quotation-saved', [
                'message' => 'Quotation updated : ' . $quotationNo,
                'id'      => $quotationId,
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('payment-error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function generateQuotationNo()
    {
        return Serial_No::next('quotation');
    }

    #[On('load-quotation-to-cart')]
    public function loadQuotationToCart($quotationId)
    {
        $quotation = Quotation::with(['lines.product.warehouses'])->find($quotationId);

        if (!$quotation) {
            $this->dispatch('app-error', [
                'message' => 'Quotation not found'
            ]);
            return;
        }

        if ($quotation->status !== 'Quotation') {
            $this->dispatch('app-error', [
                'message' => 'This quotation is already ' . strtolower($quotation->status) . ' and cannot be loaded again'
            ]);
            return;
        }

        $this->factor = $quotation->factor;
        $this->currency = $quotation->currency_name;
        $this->currency_name = $quotation->currency_name;

        if ($this->new_cart && $this->cart_queue_no == 0) {
            $this->cart_queue_no = $this->incrementQueueTable();
            $this->getDocument($this->cart_queue_no);
            $this->new_cart = false;
        }

        $this->cart = [];
        $this->cart_mode = 'sale';
        $this->loaded_quotation_id = $quotation->id;

        $this->customer_id = $quotation->customer_id;
        $this->customer_name = $quotation->customer_name ?? 'Walk-in Customer';
        $this->customer_phone = $quotation->phone ?? '';
        $this->customer_address1 = $quotation->address ?? '';
        $this->customer_address2 = '';
        $this->customer_contact_name = $quotation->customer_name ?? '';
        $this->customer_contact_phone = $quotation->phone ?? '';

        foreach ($quotation->lines as $line) {
            $product = $line->product;

            $qty = (float) ($line->quantity ?? 1);
            $price = (float) ($line->sell_price ?? $product?->sell_price ?? 0);
            $discountPercent = (float) ($line->discount_percent ?? $product?->discount_percent ?? 0);
            $vat = (float) ($line->vat ?? $product?->vat ?? 0);

            $stock = $product?->warehouses?->sum(function ($wh) {
                return (float) ($wh->pivot->qty ?? 0);
            }) ?? 0;

            $discountAmount = ($price * $discountPercent) / 100;
            $netPrice = $price - $discountAmount;
            $vatAmount = ($netPrice * $vat) / 100;

            $this->cart[] = [
                'id' => $line->product_id,
                'code' => $product?->code ?? '',
                'name' => $product?->name ?? $line->name,
                'type' => $product?->type ?? 'product',

                'price' => $price,
                'qty' => $qty,

                'discount_percent' => $discountPercent,
                'discount_price' => $netPrice,

                'order_no' => count($this->cart) + 1,

                'amount_line' => $qty * $price,
                'discount_amount_line' => $qty * $discountAmount,
                'net_amount_line' => $qty * $netPrice,
                'vat_amount_line' => $qty * $vatAmount,

                'vat' => $vat,
                'stock' => $stock,
                'unit' => $product?->unit ?? $line->unit ?? 'NA',
                'track_stock' => $product?->track_stock ?? 0,
            ];
        }

        $this->count_cart = count($this->cart);

        $this->dispatch('load-quotation', [
            'message' => 'Quotation loaded to cart successfully',
            'header' => $quotation,
            'cart' => $this->cart,
            'totals' => $this->totals,
            'factor' => $this->factor,
            'currency' => $this->currency,
        ]);
    }






#[\Livewire\Attributes\On('sale-return')]
    public function saleReturn($payload)
    {
        abort_unless(Auth::user()->hasPermission('pos_sale.sell'), 403);

        DB::beginTransaction();

        try {
            $return_date = $payload['return_date'] ?? null;
            $document_no = trim($payload['document_no'] ?? '');
            $remark      = trim($payload['remark'] ?? '');

            if (!$return_date || !$document_no) {
                throw new \Exception('Missing return date or document no');
            }

            $invoice = InvoiceHeader::where('invoice_number', $document_no)
                ->orWhere('source_no', $document_no)
                ->first();

            if (!$invoice) {
                throw new \Exception('Invoice not found');
            }

            $saleOrder = SaleOrderHeader::where('document_no', $invoice->source_no)
                ->orWhere('document_no', $document_no)
                ->first();

            if (!$saleOrder) {
                throw new \Exception('Sale order not found');
            }

            $invoiceLines = InvoiceLine::where('sale_invoice_id', $invoice->id)->get();

            if ($invoiceLines->isEmpty()) {
                throw new \Exception('Invoice lines not found');
            }

            $allEntries = ItemLedgerEntry::where(function ($q) use ($invoice) {
                $q->where('document_no', $invoice->invoice_number)
                    ->orWhere('source_no', $invoice->source_no);
            })
                ->where('entry_type', 'negative')
                ->lockForUpdate()
                ->get();

            if ($allEntries->isEmpty()) {
                throw new \Exception('No sale ledger entries found');
            }

            // ---- split into what can actually be returned this time vs
            // what has to be skipped (product doesn't allow returns, or this
            // exact lot-deduction was already returned once before) ----
            $allowReturnMap = Product::whereIn('id', $allEntries->pluck('product_id')->unique())
                ->pluck('allow_return', 'id');

            $oldEntries = collect(); // eligible → actually returned this call
            $skipped = [];           // ['name' => ..., 'reason' => ...]

            foreach ($allEntries as $entry) {
                if ($entry->returned_at) {
                    $skipped[] = ['name' => $entry->name, 'reason' => 'already returned'];
                    continue;
                }
                if (!($allowReturnMap[$entry->product_id] ?? true)) {
                    $skipped[] = ['name' => $entry->name, 'reason' => 'not returnable'];
                    continue;
                }
                $oldEntries->push($entry);
            }

            if ($oldEntries->isEmpty()) {
                $reasons = collect($skipped)->pluck('reason')->unique()->implode(', ');
                throw new \Exception('No items are eligible for return' . ($reasons ? " ({$reasons})" : '.'));
            }

            $returnableProductIds = $oldEntries->pluck('product_id')->unique();
            $returnableLines = $invoiceLines->whereIn('product_id', $returnableProductIds);

            // 1. Update sale order status — everything currently returnable
            //    is being returned in this single pass, so nothing further
            //    will ever become eligible again. The return closes out
            //    whatever was still owed, so the remaining balance clears to 0
            //    instead of continuing to show as outstanding.
            $saleOrder->status = 'Returned';
            $saleOrder->return_remarks = '[Returned: ' . ($remark ?: 'No remark') . ']';
            $saleOrder->balance_amount = 0;
            $saleOrder->save();

            // 2. Create return invoice header (negative mirror) — totals
            //    reflect only what is actually being returned this time
            $returnInvoice = InvoiceHeader::create([
                'sale_order_id'    => $invoice->sale_order_id,
                'source_no'        => $invoice->source_no,
                'invoice_number'   => $invoice->invoice_number,

                'customer_id'      => $invoice->customer_id,
                'contact_name'     => $invoice->contact_name,
                'phone'            => $invoice->phone,
                'address'          => $invoice->address,
                'invoice_date'     => $return_date,

                'total_amount'     => -abs($returnableLines->sum('line_amount')),
                'vat_amount'       => -abs($returnableLines->sum('vat_amount')),
                'discount_percent' => $invoice->discount_percent,
                'discount_amount'  => -abs($returnableLines->sum('discount_amount')),
                'grand_total'      => -abs($returnableLines->sum('grand_total_amount')),

                'customer_type'    => $invoice->customer_type,
                'payment_method'   => $invoice->payment_method,
                'currency_name'    => $invoice->currency_name,
                'factor'           => $invoice->factor,

                'remarks'          => $saleOrder->return_remarks ?? null,
                'created_by'       => Auth::user()->name ?? $invoice->created_by,
                'created_user_id'  => Auth::id() ?? $invoice->created_user_id,
            ]);

            // 3. Copy only the returnable invoice lines as negative
            foreach ($returnableLines as $line) {
                InvoiceLine::create([
                    'sale_invoice_id'    => $returnInvoice->id,
                    'product_id'         => $line->product_id,
                    'document_no'        => $returnInvoice->invoice_number,

                    'barcode'            => $line->barcode,
                    'item_code'          => $line->item_code,
                    'name'               => $line->name,
                    'variant'            => $line->variant,
                    'description'        => $line->description,

                    'quantity'           => -abs($line->quantity),

                    'unit'               => $line->unit,
                    'category_name'      => $line->category_name,
                    'cost'               => $line->cost,
                    'unit_price'         => $line->unit_price,
                    'sell_price'         => $line->sell_price,
                    'discount_percent'   => $line->discount_percent,

                    'discount_amount'    => -abs($line->discount_amount),
                    'line_amount'        => -abs($line->line_amount),
                    'vat'                => $line->vat,
                    'vat_amount'         => -abs($line->vat_amount),
                    'net_amount'         => -abs($line->net_amount),
                    'grand_total_amount' => -abs($line->grand_total_amount),

                    'created_by'         => Auth::user()->name ?? $line->created_by,
                    'remarks'            => $saleOrder->return_remarks,
                ]);
            }

            // 4. One positive ledger entry per returned line (remaining = returned qty)
            //    + add the qty back to physical warehouse stock for that lot.
            foreach ($oldEntries as $entry) {
                $returnQty = abs($entry->quantity);

                $ledger = ItemLedgerEntry::create([
                    'entry_no'           => 'ILE-' . now()->format('YmdHis') . '-' . $entry->product_id . '-' . rand(100, 999),

                    'posting_date'       => $return_date,
                    'document_type'      => 'Sale Return',
                    'source_no'          => $entry->source_no,
                    'document_no'        => $returnInvoice->invoice_number,
                    'source_id'          => $returnInvoice->id,
                    'source_table'       => 'sale_invoice_headers,sale_invoice_lines',

                    'product_id'         => $entry->product_id,
                    'barcode'            => $entry->barcode,
                    'item_code'          => $entry->item_code,
                    'name'               => $entry->name,
                    'variant'            => $entry->variant,
                    'description'        => $entry->description,
                    'unit'               => $entry->unit,
                    'category_name'      => $entry->category_name,
                    'type'               => $entry->type,

                    'warehouse_id'       => $entry->warehouse_id,
                    'warehouse_name'     => $entry->warehouse_name,
                    'bin_id'             => $entry->bin_id,
                    'bin_name'           => $entry->bin_name,
                    'lot'                => $entry->lot,
                    'expire_date'        => $entry->expire_date,

                    'quantity'           => $returnQty,
                    'remaining_quantity' => $returnQty,   // ← returned stock goes onto its own entry
                    'entry_type'         => 'positive',

                    // Positive — stock coming back in, worth what it cost going
                    // out. Mirrors the negative cost_amount on the sale.
                    'unit_cost'          => abs((float) $entry->unit_cost),
                    'cost_amount'        => round(abs($returnQty) * abs((float) $entry->unit_cost), 6),
                    'unit_price'         => $entry->unit_price,
                    'sell_price'         => $entry->sell_price,
                    'discount_percent'   => $entry->discount_percent,

                    'discount_amount'    => -abs($entry->discount_amount),
                    'vat'                => $entry->vat,
                    'vat_amount'         => -abs($entry->vat_amount),
                    'line_amount'        => -abs($entry->line_amount),
                    'net_amount'         => -abs($entry->net_amount),
                    'grand_total_amount' => -abs($entry->grand_total_amount),

                    'currency_name'      => $entry->currency_name,
                    'factor'             => $entry->factor,

                    'customer_id'        => $entry->customer_id,
                    'customer_name'      => $entry->customer_name,
                    'customer_phone'     => $entry->customer_phone,
                    'customer_address'   => $entry->customer_address,

                    'vendor_id'          => $entry->vendor_id,
                    'vendor_name'        => $entry->vendor_name,
                    'payment_method'     => $entry->payment_method,

                    'remark'             => $saleOrder->return_remarks,
                    'created_by'         => Auth::user()->name ?? $entry->created_by,
                    'created_user_id'    => Auth::id() ?? $entry->created_user_id,
                ]);

                // keep your numeric entry_no convention
                $ledger->update(['entry_no' => $ledger->id]);

                // add stock back to the physical warehouse for this lot+bin —
                // matching bin_id too (populated on the sale's own ledger
                // entry) is required so a lot split across multiple bins
                // doesn't have every matching row over-credited by this
                // increment() at once.
                $updated = WarehouseProduct::where('product_id', $entry->product_id)
                    ->where('warehouse_id', $entry->warehouse_id)
                    ->where(function ($q) use ($entry) {
                        if (!empty($entry->lot)) {
                            $q->where('lot', $entry->lot);
                        } else {
                            $q->whereNull('lot')->orWhere('lot', '');
                        }
                    })
                    ->when(!empty($entry->bin_id), fn($q) => $q->where('bin_id', $entry->bin_id), fn($q) => $q->whereNull('bin_id'))
                    ->increment('quantity', $returnQty);

                if ($updated === 0) {
                    throw new \Exception(
                        'Warehouse stock row not found for item: ' .
                            ($entry->item_code ?? $entry->product_id) .
                            ', warehouse: ' . $entry->warehouse_id .
                            ', lot: ' . ($entry->lot ?: 'NO LOT')
                    );
                }

                // mark this exact lot-deduction as returned so a second
                // return attempt on the same invoice can never return it again
                $entry->returned_at = now();
                $entry->save();
            }

            DB::commit();

            $message = 'Sale return completed: ' . $oldEntries->count() . ' item(s) returned.';
            if (!empty($skipped)) {
                $skippedSummary = collect($skipped)
                    ->groupBy('reason')
                    ->map(fn($group, $reason) => $group->pluck('name')->unique()->implode(', ') . " ({$reason})")
                    ->implode('; ');
                $message .= ' Skipped: ' . $skippedSummary;
            }

            $this->dispatch('return-success', message: $message);
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->dispatch(
                'update-fail',
                message: $e->getMessage() . ' Line: ' . $e->getLine()
            );
        }
    }










#[On('open-stock-adjustment')]
public function openStockAdjustment()
{
    if (!$this->canAdjustStock()) {
        $this->dispatch('payment-error', ['message' => 'អ្នកគ្មានសិទ្ធិកែស្តុកទេ']);
        return;
    }

    $warehouse_ids = Auth::user()->warehouses->pluck('id');

    // A purchase-only warehouse is receiving stock, not selling it, so it is
    // not offered on the sale side.
    $warehouses = Warehouse::whereIn('id', $warehouse_ids)
        ->usableFor('sale')
        ->orderBy('name')
        ->get(['id', 'name'])
        ->toArray();

    $categories = Category::orderBy('name')->get(['id', 'name'])->toArray();

    $this->dispatch('show-stock-adjustment', [
        'warehouses' => $warehouses,
        'categories' => $categories,
        'today'      => now()->toDateString(),
    ]);
}
private function canAdjustStock(): bool
{
    return Auth::user()?->hasPermission('warehouse.adjustment') ?? false;
}
#[On('adj-search')]
public function adjSearch($payload = [])
{
    if (!$this->canAdjustStock()) {
        $this->dispatch('payment-error', ['message' => 'អ្នកគ្មានសិទ្ធិកែស្តុកទេ']);
        return;
    }

    $term        = trim($payload['term'] ?? '');
    $warehouseId = $payload['warehouse_id'] ?? 'All';
    $categoryId  = $payload['category_id'] ?? 'All';

    $warehouse_ids = Auth::user()->warehouses->pluck('id');

    $query = WarehouseProduct::query()
        ->with(['product.category', 'warehouse', 'bin'])
        ->whereIn('warehouse_id', $warehouse_ids);   // user belongs to these only

    if ($warehouseId !== 'All' && $warehouseId !== '' && $warehouseId !== null) {
        $query->where('warehouse_id', (int) $warehouseId);
    }

    if ($categoryId !== 'All' && $categoryId !== '' && $categoryId !== null) {
        $query->whereHas('product', fn($q) => $q->where('category_id', (int) $categoryId));
    }

    if ($term !== '') {
        $query->where(function ($q) use ($term) {
            $q->where('lot', 'like', "%{$term}%")
                ->orWhereHas('product', function ($p) use ($term) {
                    $p->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
        });
    }

    // NOTE: no quantity>0 filter — 0-qty lots must stay visible so they can be topped up.
    $rows = $query->orderByRaw('CASE WHEN expire IS NULL THEN 1 ELSE 0 END')
        ->orderBy('expire', 'asc')
        ->limit(50)
        ->get()
        ->map(function ($wp) {
            $product = $wp->product;

            // GRN + supplier from this lot's purchase history (cost comes from the lot row itself)
            $info = $this->getLotPurchaseInfo($product?->id, $wp->lot, $wp->warehouse_id);

            return [
                'wp_id'          => $wp->id,
                'product_id'     => $product?->id,
                'code'           => $product?->code ?? '',
                'name'           => $product?->name ?? '',
                'description'    => $product?->description ?? '',
                'unit'           => $product?->unit ?? 'NA',
                'lot'            => $wp->lot,
                'expire'         => $wp->expire ? \Carbon\Carbon::parse($wp->expire)->format('Y-m-d') : null,
                'current_stock'  => (float) $wp->quantity,
                'warehouse_id'   => $wp->warehouse_id,
                'warehouse_name' => $wp->warehouse?->name ?? 'NA',
                'bin_id'         => $wp->bin_id,
                'bin_name'       => $wp->bin?->name,
                'category_name'  => optional($product?->category)->name,
                'unit_cost'      => (float) ($wp->cost ?? $info['unit_cost'] ?? ($product?->cost ?? 0)), // lot cost first
                'grn'            => $info['grn'],
                'vendor_id'      => $info['vendor_id'],
                'vendor_name'    => $info['vendor_name'],
            ];
        })
        ->toArray();

    $this->dispatch('adj-search-results', ['rows' => $rows]);
}

// same-cost / GRN / supplier lookup for a lot (falls back cleanly for brand-new lots)
private function getLotPurchaseInfo($productId, $lot = null, $warehouseId = null)
{
    $lot = !is_null($lot) && trim($lot) !== '' ? trim($lot) : null;

    $purchaseEntry = ItemLedgerEntry::where('product_id', $productId)
        ->where('document_type', 'Purchase')
        ->where('entry_type', 'positive')
        ->when(!is_null($lot), fn($q) => $q->where('lot', $lot), fn($q) => $q->whereNull('lot'))
        ->when(!is_null($warehouseId), fn($q) => $q->where('warehouse_id', $warehouseId))
        ->latest('id')
        ->first();

    return [
        'unit_cost'   => (float) ($purchaseEntry->unit_cost ?? 0),
        'grn'         => $purchaseEntry->document_no ?? '',   // ← GRN = purchase document_no
        'vendor_id'   => $purchaseEntry->vendor_id ?? null,
        'vendor_name' => $purchaseEntry->vendor_name ?? null,
    ];
}
private function generateAdjustmentNo()
{
    return Serial_No::next('adjustment');
}

#[On('stock-adjustment')]
public function stockAdjustment($payload)
{
    if (!$this->canAdjustStock()) {
        $this->dispatch('payment-error', ['message' => 'អ្នកគ្មានសិទ្ធិកែស្តុកទេ']);
        return;
    }

    $lines      = $payload['lines'] ?? [];
    $adjustDate = $payload['adjust_date'] ?? now()->toDateString();
    $remark     = trim($payload['remark'] ?? '') ?: 'Stock adjustment';

    if (empty($lines)) {
        $this->dispatch('payment-error', ['message' => 'No adjustment lines']);
        return;
    }

    $precision = 6;
    $r = fn($v) => round((float) $v, $precision);

    $docNo = null;

    // The adjustment UI (adjSearch) only offers rows from the user's own
    // warehouses, but this handler re-reads warehouse_id / wp_id straight from
    // the payload, so the restriction has to be enforced again here.
    $adjWarehouseIds = Auth::user()->role === 'admin'
        ? Warehouse::pluck('id')
        : Auth::user()->warehouses->pluck('id');

    try {
        DB::transaction(function () use ($lines, $adjustDate, $remark, $r, &$docNo, $adjWarehouseIds) {

            $docNo = $this->generateAdjustmentNo();   // ONE document for all lines

            $linesWritten = 0;
            foreach ($lines as $line) {
                $product = $this->requireProduct($line['product_id'] ?? null);

                $direction = (($line['direction'] ?? 'positive') === 'negative') ? 'negative' : 'positive';
                $qty       = $r(abs((float) ($line['qty'] ?? 0)));
                if ($qty <= 0) continue;
                $qty = max(0.01, $qty);

                // Counted only once a line is actually going to be written —
                // counting before the zero-qty `continue` above let an all-zero
                // payload satisfy assertLinesWritten() and report success while
                // writing nothing.
                $linesWritten++;

                $wpId    = $line['wp_id'] ?? null;
                $lotText = trim($line['lot'] ?? '')      !== '' ? trim($line['lot'])      : null;
                $origLot = trim($line['orig_lot'] ?? '') !== '' ? trim($line['orig_lot']) : null;
                $expire  = trim($line['expire'] ?? '')   !== '' ? trim($line['expire'])   : null;
                $lineWh  = (int) ($line['warehouse_id'] ?? 0);
                $binId   = !empty($line['bin_id']) ? (int) $line['bin_id'] : null;

                // Reject a line aimed at a warehouse this user has no claim on,
                // which otherwise created or wrote off stock in someone else's
                // location.
                if (!$adjWarehouseIds->contains($lineWh)) {
                    throw new \Exception("You are not assigned to the warehouse for {$product->name}.");
                }

                if ($direction === 'negative') {
                    // ---- deduct the EXACT selected lot row; identity fixed by the row ----
                    // wp_id and product_id both arrive from the DOM independently, so
                    // the row is constrained to this product AND to a warehouse the
                    // user is assigned to. A bare find($wpId) let a stale or edited
                    // payload move one product's stock while the ledger blamed
                    // another — and reach warehouses outside the user's scope.
                    $wp = $wpId
                        ? WarehouseProduct::where('id', $wpId)
                            ->where('product_id', $product->id)
                            ->whereIn('warehouse_id', $adjWarehouseIds)
                            ->lockForUpdate()->first()
                        : null;

                    if (!$wp) {
                        $wp = WarehouseProduct::where('product_id', $product->id)
                            ->where('warehouse_id', $lineWh)
                            ->when($lotText !== null, fn($q) => $q->where('lot', $lotText), fn($q) => $q->whereNull('lot'))
                            ->when($binId !== null, fn($q) => $q->where('bin_id', $binId), fn($q) => $q->whereNull('bin_id'))
                            ->lockForUpdate()->first();
                    }
                    if (!$wp) {
                        throw new \Exception("Lot row not found for {$product->name}");
                    }

                    if ($r($wp->quantity) < $qty) {
                        throw new \Exception(
                            "Not enough stock for {$product->name} lot " . ($wp->lot ?: 'NO LOT') .
                            " (have " . $r($wp->quantity) . ", need {$qty})"
                        );
                    }

                    $wp->quantity = $r($wp->quantity - $qty);   // can reach 0, row is kept
                    $wp->save();

                    $warehouseId  = $wp->warehouse_id;
                    $ledgerBinId  = $wp->bin_id;
                    $ledgerLot    = $wp->lot;
                    $ledgerExpire = $wp->expire;
                    $signedQty    = $r(-1 * $qty);
                    $remainingQty = 0;
                    $entryType    = 'negative';
                    $lineSign     = -1;

                } else {
                    // ---- positive: same lot row, or a new lot row if the lot text changed ----
                    $warehouseId = $lineWh > 0 ? $lineWh : null;
                    if (!$warehouseId) {
                        throw new \Exception("Warehouse required for {$product->name}");
                    }

                    $lotChanged = $wpId && ($origLot !== $lotText);

                    if ($wpId && !$lotChanged) {
                        // top up same lot (even if 0) — constrained to this product and
                        // an in-scope warehouse for the same reason as the negative branch
                        $wp = WarehouseProduct::where('id', $wpId)
                            ->where('product_id', $product->id)
                            ->whereIn('warehouse_id', $adjWarehouseIds)
                            ->lockForUpdate()->first();
                    } else {
                        $wp = WarehouseProduct::where('product_id', $product->id)
                            ->where('warehouse_id', $warehouseId)
                            ->when($lotText !== null, fn($q) => $q->where('lot', $lotText), fn($q) => $q->whereNull('lot'))
                            ->when($binId !== null, fn($q) => $q->where('bin_id', $binId), fn($q) => $q->whereNull('bin_id'))
                            ->lockForUpdate()->first();
                    }

                    if (!$wp) {
                        $wp = new WarehouseProduct();
                        $wp->product_id   = $product->id;
                        $wp->warehouse_id = $warehouseId;
                        $wp->bin_id       = $binId;
                        $wp->lot          = $lotText;
                        $wp->quantity     = 0;
                        // seed any other NOT NULL columns here if your schema requires them
                    }

                    if ($expire !== null) {
                        $wp->expire = $expire;   // editable expire applied on the add
                    }

                    $wp->quantity = $r(($wp->quantity ?? 0) + $qty);
                    $wp->save();

                    $warehouseId  = $wp->warehouse_id;
                    $ledgerBinId  = $wp->bin_id;
                    $ledgerLot    = $wp->lot;
                    $ledgerExpire = $wp->expire;
                    $signedQty    = $qty;
                    $remainingQty = $qty;
                    $entryType    = 'positive';
                    $lineSign     = 1;
                }

                $warehouseName = Warehouse::where('id', $warehouseId)->value('name');
                $ledgerBinName = $ledgerBinId ? optional(\App\Models\Bin::find($ledgerBinId))->name : null;

                // reuse cost / GRN / supplier for this final lot
                $info     = $this->getLotPurchaseInfo($product->id, $ledgerLot, $warehouseId);
                $unitCost = $r($info['unit_cost'] ?: ($product->cost ?? 0));
                $grn      = $info['grn'] ?? '';          // empty if none found
                $vendorId = $info['vendor_id'] ?? null;
                $vendorNm = $info['vendor_name'] ?? null;

                $ledger = $this->createLedgerEntry([
                    'posting_date'       => $adjustDate,
                    'document_type'      => 'Adjustment',
                    'source_no'          => $grn,        // GRN if found, else ''
                    'document_no'        => $docNo,      // ADJ26-0001 (shared)

                    'product_id'         => $product->id,
                    'barcode'            => $product->bar_code,
                    'item_code'          => $product->code,
                    'name'               => $product->name,
                    'variant'            => $product->variant,
                    'description'        => $product->description,
                    'unit'               => $product->unit ?? 'NA',
                    'category_name'      => optional($product->category)->name,
                    'type'               => 'product',

                    'warehouse_id'       => $warehouseId,
                    'warehouse_name'     => $warehouseName,
                    'bin_id'             => $ledgerBinId,
                    'bin_name'           => $ledgerBinName,
                    'lot'                => $ledgerLot,
                    'expire_date'        => $ledgerExpire,

                    'quantity'           => $signedQty,
                    'remaining_quantity' => $remainingQty,
                    'entry_type'         => $entryType,   // positive / negative

                    'currency_name'      => $this->currency_name,
                    'factor'             => $this->factor,

                    // Stock adjustment is a pure inventory move — it has a cost
                    // value and no sales value at all. unit_cost stays positive;
                    // cost_amount carries the direction, so writing stock up is
                    // positive and writing it off is negative.
                    'unit_cost'          => abs($unitCost),
                    'cost_amount'        => $r($lineSign * abs($qty) * abs($unitCost)),
                    'unit_price'         => $r($product->sell_price ?? 0),
                    'sell_price'         => $r($product->sell_price ?? 0),

                    // Sales value — not applicable to an adjustment.
                    'line_amount'        => 0,
                    'net_amount'         => 0,
                    'grand_total_amount' => 0,

                    'vendor_id'          => $vendorId,    // supplier if found
                    'vendor_name'        => $vendorNm,

                    'remark'             => $remark,
                    'created_by'         => Auth::user()->username ?? 'System',
                    'created_user_id'    => Auth::user()->id ?? null,
                ]);

                // keep entry_no consistent with every other flow (Sale, Purchase,
                // Transfer, Sale Return) — plain numeric id, not the padded "ILE-..."
                // string createLedgerEntry() sets by default.
                $ledger->update(['entry_no' => $ledger->id]);

                // re-cap positive rows' remaining_quantity to the new warehouse total
                $this->syncPurchaseRemainingQty($product->id, $ledgerLot, $warehouseId);
            }

            $this->assertLinesWritten($linesWritten, $docNo);
        });

        $this->dispatch('adjustment-success', [
            'message'     => 'Stock adjusted successfully — ' . $docNo,
            'document_no' => $docNo,
        ]);
    } catch (\Throwable $e) {
        $this->dispatch('payment-error', ['message' => $e->getMessage() . ' Line: ' . $e->getLine()]);
    }
}





}
