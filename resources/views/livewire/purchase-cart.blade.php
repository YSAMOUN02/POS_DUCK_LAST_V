<div>
    <div class="screen-only">
        <div id="header_invoice"
            class="border-b bg-white border-default pb-2  p-2 flex items-center justify-between sticky top-0">
            <h1 id="chasier" class="mb-2 font-bold">

                <span id="tittle_span" class="text-transparent bg-clip-text bg-gradient-to-r to-emerald-600 from-sky-400">
                    Purchase Order
                </span>



            </h1>
            <div class="flex items-center gap-3">
                <div class="px-4" id="refreshBtn" data-popover-target="popover-user-profile">
                    <i id="refresh-icon" class="fa-solid fa-arrows-rotate"></i>
                </div>
            </div>

            <div data-popover id="popover-user-profile" role="tooltip"
                class="absolute z-10 invisible inline-block w-64 text-sm text-body transition-opacity duration-300 bg-neutral-primary-soft border border-default rounded-base shadow-xs opacity-0">
                <div class="p-3">
                    <p class="text-sm text-gray-500">Tip: Click on the arrows to refresh the Page.</p>
                </div>
                <div data-popper-arrow></div>
            </div>

        </div>
        @php
            // =========================================================
            // CURRENCY DISPLAY RULES  (ported from the Sales/POS cart)
            //  - $step    : rounding step applied to PER-UNIT prices only
            //               (KHR -> 100, so every unit cost ends in 00)
            //  - $decimal : decimals for the FINAL formatted amount
            //
            // KEY RULE (WYSIWYG):
            //   round the UNIT cost first, THEN multiply by qty.
            //   => displayed unit x qty == displayed line, exactly.
            //   The total sums these same per-line values, so the screen
            //   and the printed PO reconcile to the last digit.
            //
            //  NOTE: stepping/format affect DISPLAY ONLY. The stored USD
            //  cost_price / amount_line / totals are never changed, so
            //  costs and margins computed elsewhere stay exact.
            // =========================================================
            $factor = (float) ($this->factor ?: 1);

            if ($factor == 1) {
                // 3dp: purchase costs are often quoted per-unit to a tenth of a
                // cent (e.g. 0.475), and 2dp silently rounded that away on entry.
                // Trailing zeros are trimmed by $money, so whole amounts still
                // read as "12" rather than "12.000".
                $decimal = 3;
                $step = 0;
                $thousands = ''; // USD
            } elseif ($factor >= 4000) {
                $decimal = 0;
                $step = 0;
                $thousands = ','; // KHR -> unit ends in 00
            } elseif ($factor >= 100) {
                $decimal = 3;
                $step = 0;
                $thousands = ''; // mid-rate
            } else {
                $decimal = 2;
                $step = 0;
                $thousands = ''; // fallback
            }

            // round a BASE (USD) per-unit value into stepped display currency
            $unitDisp = function ($baseUnit) use ($factor, $step, $decimal) {
                $v = (float) $baseUnit * $factor;
                return $step > 0 ? round($v / $step) * $step : round($v, $decimal);
            };

            // format a FINAL display-currency amount — NO thousands (for <input> values)
            $money_no_format = function ($v) use ($decimal) {
                $s = number_format((float) $v, $decimal, '.', '');
                return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
            };

            // format a FINAL display-currency amount — WITH thousands (for on-screen / print)
            $money = function ($v) use ($decimal, $thousands) {
                $s = number_format((float) $v, $decimal, '.', $thousands);
                // trim trailing decimals only (won't touch the thousands separator)
    return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
};

// PER-UNIT cost display (stepped, with thousands) e.g. 72,200
$priceFmt = function ($baseUnit) use ($unitDisp, $money) {
    return $money($unitDisp($baseUnit));
};

// PER-UNIT cost for <input> (stepped, no thousands) e.g. 72200
$priceInput = function ($baseUnit) use ($unitDisp, $money_no_format) {
    return $money_no_format($unitDisp($baseUnit));
};

// WYSIWYG line (display): stepped unit x qty, with thousands  e.g. 72,200 x 3 = 216,600
$fmtLine = function ($baseUnit, $qty) use ($unitDisp, $money) {
    return $money($unitDisp($baseUnit) * (float) $qty);
};

// WYSIWYG line for <input>: stepped unit x qty, no thousands
$lineInput = function ($baseUnit, $qty) use ($unitDisp, $money_no_format) {
    return $money_no_format($unitDisp($baseUnit) * (float) $qty);
};

// USD base — never stepped (for saving / USD readout)
$fmtUsd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

// qty — never money-rounded
$qtyFmt = fn($v) => rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');

// WYSIWYG display total = SUM of the (rounded) displayed lines.
// Summing the already-rounded line values guarantees the printed
// total equals the printed lines added up — even for fractional qty.
$displayTotal = 0.0;
foreach ($cart as $__it) {
    $displayTotal += round($unitDisp($__it['cost_price']) * (float) $__it['qty'], $decimal);
            }
        @endphp
        <div class="flex flex-col gap-2.5 pt-2">
            <style>
                :root {
                    --ci-accent: oklch(0.58 0.11 165);
                    --ci-ink: oklch(0.28 0.01 90);
                    --ci-muted: oklch(0.55 0.01 90);
                    --ci-faint: oklch(0.62 0.01 90);
                    --ci-border: oklch(0.9 0.008 95);
                    --ci-border-soft: oklch(0.93 0.008 95);
                    --ci-panel-bg: oklch(0.985 0.004 95);
                    --ci-danger: oklch(0.62 0.17 22);
                    --ci-mono: 'JetBrains Mono', monospace;
                }

                .ci-card {
                    background: #fff;
                    border: 1px solid var(--ci-border);
                    border-radius: 14px;
                    box-shadow: 0 1px 2px oklch(0.7 0.02 95 / 0.18);
                    overflow: hidden;
                    font-family: 'Public Sans', 'Noto Sans Khmer', system-ui, sans-serif;
                    color: var(--ci-ink);
                    animation: ci-row-in 0.25s ease both;
                    transition: background-color 0.2s;
                }

                .ci-card:focus-within {
                    background: oklch(0.99 0.02 95);
                }

                @keyframes ci-row-in {
                    from {
                        opacity: 0;
                        transform: translateY(6px);
                    }

                    to {
                        opacity: 1;
                        transform: none;
                    }
                }

                .ci-header {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 14px 16px;
                    cursor: pointer;
                }

                /* ===== these two were missing — they fix the broken layout ===== */
                .ci-left {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    min-width: 0;
                }

                .ci-controls {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 6px;
                    padding-top: 2px;
                }

                /* =============================================================== */

                .ci-chevron {
                    font-size: 16px;
                    color: var(--ci-accent);
                    transition: transform 0.3s ease;
                    display: inline-block;
                    line-height: 1;
                    user-select: none;
                }

                .ci-chevron.ci-open {
                    transform: rotate(180deg);
                }

                .ci-remove {
                    background: none;
                    border: none;
                    padding: 0;
                    cursor: pointer;
                    line-height: 0;
                    color: var(--ci-danger);
                    font-size: 15px;
                }

                .ci-order-no {
                    color: oklch(0.6 0.01 90);
                    font-family: var(--ci-mono);
                    font-size: 10px;
                    font-weight: 500;
                }

                .ci-description {
                    font-size: 10px;
                }

                .ci-name {
                    margin: 0;
                    font-weight: 600;
                    font-size: 10px;
                    line-height: 1.35;
                }

                .ci-qty {
                    color: oklch(0.6 0.01 90);
                    font-weight: 500;
                    font-size: 10px;
                }

                .ci-price-line {
                    margin: 6px 0 0;
                    font-size: 13px;
                    color: oklch(0.6 0.01 90);
                }

                .ci-total {
                    text-align: right;
                    white-space: nowrap;
                    padding-top: 2px;
                }

                .ci-total-amount {
                    font-weight: 700;
                    font-size: 15px;
                    font-family: var(--ci-mono);
                }

                .ci-total-currency {
                    font-size: 11px;
                    color: var(--ci-faint);
                }

                /* Dropdown panel — taller than the sale one (extra Lot/Expire + Remark rows) */
                .ci-panel {
                    overflow: hidden;
                    transition: max-height 0.3s ease, opacity 0.25s ease;
                    max-height: 0;
                    opacity: 0;
                }

                .ci-panel.ci-open {
                    max-height: 480px;
                    opacity: 1;
                }

                /* 3-column grid: Qty | Cost | Amount, then Lot/Expire, then Remark full width */
                .ci-panel-inner {
                    border-top: 1px solid var(--ci-border-soft);
                    background: var(--ci-panel-bg);
                    padding: 16px;
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 14px;
                }

                /* Lot + Expire share a row as a pair (Lot wider than Expire looks better) */
                .ci-field-lot {
                    grid-column: 1 / 3;
                }

                .ci-field-expire {
                    grid-column: 3 / 4;
                }

                /* Remark spans the full row */
                .ci-field-full {
                    grid-column: 1 / -1;
                }

                .ci-label {
                    display: block;
                    font-size: 12px;
                    font-weight: 500;
                    color: var(--ci-muted);
                    margin-bottom: 5px;
                }

                .ci-input {
                    width: 100%;
                    border: 1px solid oklch(0.88 0.008 95);
                    border-radius: 8px;
                    padding: 9px 11px;
                    font-size: 14px;
                    font-family: var(--ci-mono);
                    outline: none;
                    background: #fff;
                    color: var(--ci-ink);
                }

                .ci-input:focus {
                    border-color: var(--ci-accent);
                    box-shadow: 0 0 0 3px oklch(0.58 0.11 165 / 0.15);
                }

                .ci-input[type=text],
                .ci-input[type=date] {
                    font-family: 'Public Sans', 'Noto Sans Khmer', system-ui, sans-serif;
                }

                .ci-empty {
                    text-align: center;
                    padding: 60px 20px;
                    color: var(--ci-faint);
                    border: 1px dashed oklch(0.85 0.008 95);
                    border-radius: 14px;
                    font-family: 'Public Sans', 'Noto Sans Khmer', system-ui, sans-serif;
                    font-size: 15px;
                }

                .ci-input::-webkit-outer-spin-button,
                .ci-input::-webkit-inner-spin-button {
                    -webkit-appearance: none;
                    margin: 0;
                }

                .ci-input[type=number] {
                    -moz-appearance: textfield;
                }

                /* ==========================================
                   LAPTOP 1000–1400px : compact cart
                   ========================================== */
                @media (min-width: 1000px) and (max-width: 1400px) {

                    .ci-header {
                        padding: 7px 10px;
                        gap: 8px;
                    }

                    .ci-name { font-size: 12px; }
                    .ci-order-no { font-size: 10.5px; }
                    .ci-qty { font-size: 11px; }
                    .ci-price-line { font-size: 11px; margin-top: 3px; }
                    .ci-badge { font-size: 9.5px; padding: 1px 6px; margin-top: 3px; }

                    .ci-total-amount { font-size: 13px; }
                    .ci-total-currency { font-size: 10.5px; }
                    .ci-total del { font-size: 10px; }

                    .ci-chevron { font-size: 13px; }
                    .ci-remove { font-size: 13px; }
                    .ci-controls { gap: 5px; }

                    .ci-panel-inner {
                        padding: 8px 10px 10px;
                        gap: 7px;
                    }

                    .ci-label { font-size: 10.5px; margin-bottom: 2px; }

                    .ci-input {
                        padding: 5px 7px;
                        font-size: 12px;
                        border-radius: 6px;
                    }

                    .ci-lot-row { padding: 5px 8px; }
                    .ci-lot-label { font-size: 11px; }
                    .ci-lot-btn {
                        min-width: 28px;
                        padding: 4px 8px;
                        font-size: 11.5px;
                    }

                    .ci-empty { padding: 30px 14px; font-size: 13px; }
                }
            </style>
            @forelse ($cart as $item)
                <div class="w-full mx-auto animate-add mt-1">
                    {{-- data-* drive the right-click quantity stepper (qty_stepper.js),
                         shared with the sales cart. --}}
                    <div class="ci-card" data-cart-index="{{ $loop->index }}"
                        data-cart-name="{{ $item['name'] ?? '' }}" data-cart-qty="{{ $item['qty'] ?? 0 }}"
                        data-cart-locked="0">

                        {{-- ===== Header (clickable) ===== --}}
                        <div class="ci-header" wire:click="toggleItem({{ $loop->index }})">

                            <div class="ci-left">
                                <div class="ci-controls">
                                    <span class="ci-chevron {{ $openIndex === $loop->index ? 'ci-open' : '' }}">▾</span>
                                    <button class="ci-remove" title="Remove item"
                                        wire:click.stop="removeItem({{ $item['id'] }})">
                                        <i class="fa-solid fa-delete-left fa-flip-horizontal"></i>
                                    </button>
                                </div>

                                <div class="text-left" style="min-width:0;">
                                    <p class="ci-name number-change">
                                        <span class="ci-order-no">{{ $item['order_no'] }}.</span>
                                        <span class="ci-description">
                                            {{ $item['name'] }}
                                        </span>
                                        <span class="ci-qty"> × {{ $qtyFmt($item['qty']) }} {{ $item['unit'] }}</span>
                                    </p>

                                    <p class="ci-price-line number-change">
                                        តម្លៃ:
                                        {{ $priceFmt($item['cost_price']) }} {{ $this->currency_name }}
                                    </p>
                                </div>
                            </div>

                            {{-- amount + currency on ONE line --}}
                            <div class="ci-total">
                                <span
                                    class="ci-total-amount number-change">{{ $fmtLine($item['cost_price'], $item['qty']) }}</span><span
                                    class="ci-total-currency">{{ $this->currency_name }}</span>
                            </div>
                        </div>

                        {{-- ===== Dropdown editor ===== --}}
                        <div class="ci-panel bonus {{ $openIndex === $loop->index ? 'ci-open' : '' }}">
                            <div class="ci-panel-inner" wire:key="row-{{ $loop->index }}-{{ $this->factor }}">

                                {{-- QTY --}}
                                <div>
                                    <label class="ci-label">ចំនួន · Qty</label>
                                    <input type="number" step="0.01" min="0.01"
                                        wire:model.lazy="cart.{{ $loop->index }}.qty"
                                        wire:change="recalcLine({{ $loop->index }}, 'qty')" class="ci-input" />
                                </div>

                                {{-- COST (stepped display: WYSIWYG with the line above) --}}
                                <div>
                                    <label class="ci-label">ថ្លៃដើម · Cost</label>
                                    <input type="number" step="any" min="0"
                                        wire:key="cost-{{ $loop->index }}-{{ $this->cart[$loop->index]['cost_price'] }}-{{ $this->factor }}"
                                        value="{{ $priceInput($item['cost_price']) }}"
                                        wire:change="recalcLine({{ $loop->index }}, 'cost_price', $event.target.value)"
                                        class="ci-input" />
                                </div>

                                {{-- AMOUNT (WYSIWYG line = stepped unit x qty, matches the header line) --}}
                                <div>
                                    <label class="ci-label">សរុប · Amount</label>
                                    <input type="number" step="any" min="0"
                                        wire:key="amount-{{ $loop->index }}-{{ $this->cart[$loop->index]['cost_price'] }}-{{ $this->cart[$loop->index]['qty'] }}-{{ $this->factor }}"
                                        value="{{ $lineInput($item['cost_price'], $item['qty']) }}"
                                        wire:change="recalcLine({{ $loop->index }}, 'amount_line', $event.target.value)"
                                        class="ci-input" />
                                </div>

                                {{-- LOT + EXPIRE --}}
                                <div class="ci-field-lot">
                                    <label class="ci-label">Lot Auto (editable)</label>
                                    <input type="text" wire:model.lazy="cart.{{ $loop->index }}.lot"
                                        class="ci-input" />
                                </div>
                                <div class="ci-field-expire">
                                    <div class="flex items-center justify-between">
                                        <label class="ci-label" style="margin-bottom:0">Expire</label>
                                        <label
                                            class="flex items-center gap-1 text-[11px] text-gray-500 cursor-pointer select-none">
                                            <input type="checkbox"
                                                wire:click="toggleLineExpire({{ $loop->index }})"
                                                @checked($item['has_expire'] ?? false)
                                                class="w-3.5 h-3.5 rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                                            Set date
                                        </label>
                                    </div>
                                    @if ($item['has_expire'] ?? false)
                                        <input type="date" wire:model.lazy="cart.{{ $loop->index }}.expire"
                                            class="ci-input mt-1" />
                                    @else
                                        <div class="ci-input mt-1 flex items-center text-gray-400">NA</div>
                                    @endif
                                </div>
                                <div class="ci-field-lot">
                                    <label class="ci-label">Bin</label>
                                    <select wire:model.lazy="cart.{{ $loop->index }}.bin_id" class="ci-input">
                                        <option value="">No Bin</option>
                                        @foreach ($binsForWarehouse as $b)
                                            <option value="{{ $b['id'] }}">{{ $b['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- REMARK --}}
                                <div class="ci-field-full">
                                    <label class="ci-label">Remark</label>
                                    <input type="text" wire:model.lazy="cart.{{ $loop->index }}.remark"
                                        class="ci-input" />
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

            @empty

                <div class="flex flex-col items-center justify-center py-6 px-6 text-center">

                    <img src="{{ asset('assets/defult/cart.png') }}" alt="Empty Cart"
                        class="w-24 h-24 lg:w-28 lg:h-28 object-contain opacity-80">

                    <h3 class="mt-4 text-lg font-bold text-slate-800">
                        No items in cart
                    </h3>

                    <p class="mt-1 text-sm text-slate-500 max-w-xs">
                        Scan a barcode or select a product to start building the order.
                    </p>

                </div>
            @endforelse


            {{-- Totals --}}

            <div id="total" class="grid grid-cols-1 gap-1 p-2 ">
                <div class="flex items-end flex-col justify-between">

                    <p class="font-semibold">
                        តម្លៃសរុប :
                        {{ $money($displayTotal) }}
                        {{ $currency_name }}
                    </p>
                    <input type="hidden" id="total_amount" value="{{ $fmtUsd($this->totals['total_amount']) }}">
                    <input type="hidden" id="currency_name" value="{{ $currency_name }}">
                    <input type="hidden" id="currency_display_symbol" value="{{ $currency }}">
                    <input type="hidden" id="currency_display_factor" value="{{ $factor }}">

                    {{-- Deposit input and Balance line removed: purchases are not
                         part-paid here, so deposit_amount stays 0 and Balance only ever
                         repeated the total shown above. --}}

                    @if ($factor != 1)
                        <div class="w-full flex justify-between">

                            <div class="flex items-center">

                                {{-- <p class="font-semibold">1$ : {{ (float) $factor }}{{ $currency }}</p> --}}
                                <span
                                    class="inline-flex items-center bg-brand-softer border border-brand-subtle text-fg-brand-strong text-xs font-medium px-1.5 py-0.5 rounded-sm">

                                    1$ : {{ (float) $factor }}&ensp;{{ $currency }}
                                </span>
                            </div>
                            <p class="font-semibold">
                                Total USD: {{ $fmtUsd($this->totals['total_amount']) }} $

                            </p>


                        </div>
                        <input type="hidden" id="converted_total_amount"
                            value="{{ $money_no_format($displayTotal) }}">
                    @else
                        <input type="hidden" id="converted_total_amount"
                            value="{{ $money_no_format($displayTotal) }}">
                    @endif

                </div>
                <div class="w-full flex  items-end justify-between gap-2">
                    <!-- Select -->
                    <select wire:change="setCurrency($event.target.value)"
                        class="w-full border border-gray-300 rounded-xl pl-10 pr-4 py-2
               shadow-sm focus:ring-2 focus:ring-green-300
               focus:outline-none bg-white text-gray-700 appearance-none">
                        <option value="$">
                            USD
                        </option>
                        @foreach ($all_currency as $currency_symbol)
                            <option value="{{ $currency_symbol->code }}" @selected($currency === $currency_symbol->code)>
                                {{ $currency_symbol->name }}
                            </option>
                        @endforeach

                    </select>
                    <div id="list_main" class="relative col-span-2 w-[300px]">

                        <!-- Icon -->
                        <i id="fa-user-tie2" class="fa-solid fa-user-tie absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>

                        <!-- Search Input -->
                        <input type="text" id="vendorSearch" placeholder="Search Vendor..." autocomplete="off"
                            class="w-full border border-gray-300 rounded-xl pl-10 pr-4 py-2
               shadow-sm focus:ring-2 focus:ring-green-300
               focus:outline-none text-gray-700">

                        <!-- Hidden Value -->
                        <input type="hidden" id="vendorValue" wire:model.live="vendor_id">

                        <!-- Dropdown List -->
                        <ul id="vendorList"
                            class="hidden absolute z-50 mt-1 bg-white border border-gray-200
               rounded-xl shadow-lg w-full max-h-60 overflow-auto">
                        </ul>

                    </div>
                </div>

                <div class="w-full flex justify-end">

                    <input type="text" wire:model.lazy="$this->Remark" placeholder="Remark"
                        class="w-full mt-1 border rounded px-3 py-2" />
                </div>

                <hr>
                <div class="mt-5 relative">
                    <!-- Icon -->
                    <i class="fa-solid fa-warehouse absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>

                    <!-- Select -->
                    <select wire:model.live="warehouse_id"
                        class="w-full border border-gray-300 rounded-xl pl-10 pr-4 py-2 shadow-sm
           focus:ring-2 focus:ring-green-300 focus:outline-none
           bg-white text-gray-700">

                        <option value="">Select warehouse</option>
                        @foreach ($warehouses as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-2 grid grid-cols-2 gap-2">
                    <!-- Clear -->
                    <button wire:click="clearCart"
                        class="bg-red-500 hover:bg-red-600 text-white font-medium px-1 py-2 rounded-xl shadow-md transition">
                        <i class="fa-solid fa-trash-can mr-1"></i> Clear
                    </button>

                    <!-- Purchase -->
                    @if (Auth::user()->hasPermission('purchasing.purchase'))
                        <button onclick="openGrnModal()"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium px-1 py-2 rounded-xl shadow-md transition">
                            <i class="fa-solid fa-cart-plus mr-1"></i> Purchase
                        </button>
                    @endif

                    {{-- Preview needs NO permission: it prints the cart as it stands
                         and posts nothing, so a user who may not receive stock can
                         still put the figures in front of a vendor. Spans the row
                         so it keeps its place whether or not Purchase is shown. --}}
                    @if ($count_cart > 0)
                        <button wire:click="openPurchasePreview"
                            class="col-span-2 bg-slate-600 hover:bg-slate-700 text-white font-medium px-1 py-2 rounded-xl shadow-md transition">
                            <i class="fa-solid fa-eye mr-1"></i> Preview
                        </button>
                    @endif
                </div>
            </div>
        </div>




        <div id="invoice">
            <div class="print-only">
                <input type="text" id="count_cart_input" value="{{ $count_cart }}" hidden>
                @php
                    $companyLogoUrl = \App\Http\Controllers\PosProfileController::logoUrl();
                @endphp

                <div id="logo" style="flex: 0 0 auto; margin-right:15px;">
                    @if ($companyLogoUrl)
                        <img class="logo" style="width: 80px;" src="{{ $companyLogoUrl }}" alt="Logo">
                    @endif
                </div>

                <div id="logo_80mm" style="flex: 0 0 auto; margin-right:15px;">
                    @if ($companyLogoUrl)
                        <img class="logo" style="width: 80px;" src="{{ $companyLogoUrl }}" alt="Logo">
                    @endif
                </div>
                <div id="document_title">
                    <h1> </h1>
                </div>

                <div id="table_footer">
                    <div>
                        <div id="table_footer_description"></div>
                        <!-- CURRENCY RATE -->

                    </div>
                </div>

                <div id="invoice-table">
                    <!-- INVOICE TABLE -->
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th>{{ __('No.') }}</th>
                                <th class="text-left">{{ __('Item') }}</th>
                                <th>{{ __('Qty') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Total') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($cart as $item)
                                <tr style="background-color: {{ $loop->even ? '#ffffff' : '#f9f9f9' }};">
                                    <td style="text-align:center;">{{ $item['order_no'] }}</td>
                                    <td style="text-align:left;">{{ $item['name'] }}</td>
                                    <td style="text-align:center;">{{ $qtyFmt($item['qty']) }}</td>
                                    <td style="text-align:center;">{{ $item['unit'] }}</td>
                                    <td style="text-align:right;">{{ $priceFmt($item['cost_price']) }}</td>
                                    <td style="text-align:right;">{{ $fmtLine($item['cost_price'], $item['qty']) }}
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Grand total in display currency (WYSIWYG: equals the lines above added up) --}}
                            <tr class="total_print">
                                <td colspan="5" style="text-align:right; font-weight:bold;">
                                    សរុប/Total ({{ $currency }})
                                </td>
                                <td style="text-align:right; font-weight:bold;">
                                    {{ $money($displayTotal) }}
                                </td>
                            </tr>

                            {{-- USD base total --}}
                            @if ($factor != 1)
                                <tr class="total_print">
                                    <td colspan="5" style="text-align:right; font-weight:bold;">
                                        សរុប/Total (USD)
                                    </td>
                                    <td style="text-align:right; font-weight:bold;">
                                        {{ $fmtUsd($this->totals['total_amount']) }} $
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>




            </div>

        </div>



    </div>




</div>
