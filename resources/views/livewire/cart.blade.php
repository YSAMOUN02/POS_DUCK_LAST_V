<div class="h-full flex flex-col min-h-0">
    <div class="screen-only h-full flex flex-col min-h-0">
        <div class="flex-1 min-h-0 overflow-y-auto">
        <div id="header_invoice"
            class="border-b bg-white border-default pb-2 p-2 flex items-center justify-between sticky top-0">

            <div class="flex items-center justify-between gap-3 flex-nowrap">

                {{-- Title + mode badge --}}
                <div class="flex items-center gap-2 min-w-0">
                    <h2 class="text-2xl font-bold text-green-500 whitespace-nowrap truncate">Sales Order</h2>

                </div>

                {{-- Control group --}}
                <div class="hd-controls">

                    {{-- Refresh --}}
                    <button type="button" id="refreshBtn" class="hd-item" data-popover-target="popover-user-profile"
                        title="Refresh">
                        <i id="refresh-icon" class="fa-solid fa-arrows-rotate"></i>
                    </button>

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
            // CURRENCY DISPLAY RULES
            //  - $decimal : decimals for the FINAL formatted amount
            //
            // KEY RULE:
            //   unit prices and line amounts are the converted value, truncated
            //   to whole riel (250.51 shows 250, never 251). Only the payable
            //   grand total snaps to 100៛, because riel has no smaller coin.
            // =========================================================
            $factor = (float) ($this->factor ?: 1);
            $truncate = $factor >= 4000; // riel is never quoted in fractions of a riel

            if ($factor == 1) {
                $decimal = 2;
                $thousands = ''; // USD
            } elseif ($factor >= 4000) {
                $decimal = 0;
                $thousands = ','; // KHR
            } elseif ($factor >= 100) {
                $decimal = 3;
                $thousands = ''; // mid-rate
            } else {
                $decimal = 2;
                $thousands = ''; // fallback
            }

            // a BASE (USD) per-unit value in display currency — never stepped
            $unitDisp = function ($baseUnit) use ($factor, $decimal, $truncate) {
                $v = (float) $baseUnit * $factor;
                // round() first so float dust (250.9999999) truncates as 251, not 250
                return $truncate ? floor(round($v, 6)) : round($v, $decimal);
            };

            // format a FINAL display-currency amount (lines / totals) — no step rounding
            $money_no_format = function ($v) use ($decimal) {
                $s = number_format((float) $v, $decimal, '.', '');
                return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
            };

            // format a FINAL display-currency amount (lines / totals) — no step rounding
            $money = function ($v) use ($decimal, $thousands) {
                $s = number_format((float) $v, $decimal, '.', $thousands);
                // trim trailing decimals only (won't touch the thousands separator)
    return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
};
// PER-UNIT price display e.g. 72150
$priceFmt = function ($baseUnit) use ($unitDisp, $money) {
    return $money($unitDisp($baseUnit));
};

$priceInput = function ($baseUnit) use ($unitDisp, $money_no_format) {
    return $money_no_format($unitDisp($baseUnit));
};

// line: unit × qty  e.g. 72150 × 3 = 216450
$fmtLine = function ($baseUnit, $qty) use ($unitDisp, $money) {
    return $money($unitDisp($baseUnit) * (float) $qty);
};

// USD base — never stepped (for saving / USD readout)
$fmtUsd = fn($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

// qty — never money-rounded
$qtyFmt = fn($v) => rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');

        @endphp
        {{-- ============================================================
     Cart Item List — restyled to match "Sale Invoice" mockup
     Logic preserved: toggleItem / removeItem / recalcLine /
     expense mode / Deposit & locked doc types / lot management
     ============================================================ --}}

        {{-- Put once in your layout <head> if not already there:
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&family=Noto+Sans+Khmer:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
--}}



        @php
            $locked = in_array($this->document_type, ['Deposit', 'Completed', 'Cancelled', 'Returned']);
        @endphp

        <div class="flex flex-col gap-2.5 pt-2">

            {{-- ============================================================
     Cart Item List v2 — mobile-friendly fixes
     • Total + currency on ONE line (no more stacked "5.4 / $")
     • Editor grid: 3 clean columns, Manage Lot on its own full row
     • Header doesn't squeeze the amount (flex-shrink:0)
     • Panel no longer clips when labels wrap
     Logic preserved: toggleItem / removeItem / recalcLine /
     expense mode / locked doc types / lot management
     ============================================================ --}}

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

                /* ===== Header row ===== */
                .ci-header {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 10px;
                    padding: 12px 14px;
                    cursor: pointer;
                }

                .ci-header.ci-locked {
                    cursor: default;
                }

                .ci-left {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    min-width: 0;
                    /* allow name to wrap instead of pushing total */
                    flex: 1 1 auto;
                }

                .ci-controls {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 8px;
                    padding-top: 2px;
                    flex-shrink: 0;
                }

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
                    font-size: 12px;
                    font-weight: 500;
                }

                .ci-name {
                    margin: 0;
                    font-weight: 600;
                    font-size: 14px;
                    line-height: 1.4;
                    overflow-wrap: anywhere;
                    /* long product names wrap cleanly */
                }

                .ci-qty {
                    color: var(--ci-muted);
                    font-weight: 500;
                    white-space: nowrap;
                }

                /* Header qty stepper — adjust a line without opening the row. */
                /* Qty stepper: sits under the line total in the right column. */
                .ci-qty-step {
                    display: flex;
                    justify-content: flex-end;
                    gap: 6px;
                    margin-top: 6px;
                }

                .ci-step {
                    width: 24px;
                    height: 22px;
                    line-height: 1;
                    border: 1px solid var(--ci-border);
                    border-radius: 6px;
                    background: #fff;
                    color: var(--ci-ink);
                    font-size: 14px;
                    font-weight: 700;
                    cursor: pointer;
                    padding: 0;
                }

                .ci-step:hover { background: oklch(0.96 0.01 95); }
                .ci-step:active { transform: scale(0.92); }




                .ci-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    color: #fff;
                    font-size: 11px;
                    font-weight: 600;
                    padding: 2px 7px;
                    border-radius: 5px;
                    margin-top: 5px;
                }

                .ci-badge-danger {
                    background: oklch(0.63 0.19 20);
                }

                .ci-badge-ok {
                    background: var(--ci-accent);
                }

                .ci-price-line {
                    margin: 5px 0 0;
                    font-size: 12.5px;
                    color: oklch(0.6 0.01 90);
                }

                .ci-price-line del {
                    opacity: 0.7;
                }

                .ci-disc-price {
                    color: var(--ci-accent);
                    font-weight: 600;
                }

                /* ===== Right total — never wraps, never squeezed ===== */
                .ci-total {
                    text-align: right;
                    white-space: nowrap;
                    padding-top: 2px;
                    flex-shrink: 0;
                    /* the amount always keeps its space */
                }

                .ci-total del {
                    display: block;
                    font-size: 11.5px;
                    color: oklch(0.65 0.01 90);
                    font-family: var(--ci-mono);
                }

                .ci-total-amount {
                    font-weight: 700;
                    font-size: 15px;
                    font-family: var(--ci-mono);
                }

                .ci-total-currency {
                    font-size: 12px;
                    color: var(--ci-faint);
                    font-weight: 500;
                    margin-left: 3px;
                    /* currency sits inline: "5.40 $" */
                }

                /* ===== Dropdown panel ===== */
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

                /* room for wrapped rows */

                .ci-panel-inner {
                    border-top: 1px solid var(--ci-border-soft);
                    background: var(--ci-panel-bg);
                    padding: 12px 14px 14px;
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 10px;
                }

                .ci-field-full {
                    grid-column: 1 / -1;
                }

                .ci-field-two {
                    grid-column: span 2;
                }

                .ci-label {
                    display: block;
                    font-size: 11.5px;
                    font-weight: 500;
                    color: var(--ci-muted);
                    margin-bottom: 4px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .ci-input {
                    width: 100%;
                    min-width: 0;
                    border: 1px solid oklch(0.88 0.008 95);
                    border-radius: 8px;
                    padding: 8px 10px;
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

                .ci-input[type=text] {
                    font-family: 'Public Sans', 'Noto Sans Khmer', system-ui, sans-serif;
                }

                /* Manage Lot — full-width row, label + buttons inline */
                .ci-lot-row {
                    grid-column: 1 / -1;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    background: #fff;
                    border: 1px solid oklch(0.9 0.008 95);
                    border-radius: 8px;
                    padding: 7px 10px;
                }

                .ci-lot-label {
                    font-size: 12.5px;
                    font-weight: 500;
                    color: var(--ci-muted);
                }

                .ci-lot-btns {
                    display: flex;
                    gap: 6px;
                }

                .ci-lot-btn {
                    background: var(--ci-accent);
                    color: #fff;
                    font-weight: 600;
                    min-width: 34px;
                    padding: 6px 10px;
                    border-radius: 8px;
                    border: none;
                    cursor: pointer;
                    font-size: 13px;
                    line-height: 1;
                }

                .ci-lot-btn:hover {
                    filter: brightness(0.93);
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

                    /* totals + action buttons */
                    #total { padding: 6px; gap: 4px; }
                    #total p { font-size: 11.5px; }
                    #total p.font-semibold { font-size: 12.5px; }

                    #total select,
                    #total #customerSearch {
                        height: 34px;
                        padding-top: 5px;
                        padding-bottom: 5px;
                        font-size: 12px;
                    }

                    #total .mt-5 { margin-top: 8px; }
                    #total .mt-5 button {
                        padding: 5px 6px;
                        font-size: 11.5px;
                        border-radius: 10px;
                    }
                    /* currency select + customer search: stack vertically */
                    #total .w-full.flex.items-end.justify-between.gap-2 {
                        flex-direction: column;
                        align-items: stretch;
                        gap: 6px;
                    }

                    #total .relative.w-full.min-w-\[180px\] {
                        min-width: 0;
                    }

                    #total #list_main {
                        width: 100% !important;
                        min-width: 0;
                    }

                    #total #list_main input#customerSearch {
                        width: 100%;
                        height: 34px;
                        font-size: 12px;
                    }

                    /* action buttons: 2 per row instead of 4 crushed */
                    .cart-actions {
                        grid-template-columns: repeat(2, 1fr);
                        gap: 6px;
                    }
                    /* hide field icons, reclaim padding */
                    #total .fa-dollar-sign,
                    #total #list_main .fa-user-tie {
                        display: none;
                    }

                    #total select {
                        padding-left: 10px !important;
                    }

                    #total #list_main input#customerSearch {
                        padding-left: 10px !important;
                    }
                }

                /* Placeholder row shown between the tap on a product and the
                   add-product round-trip returning. Same box as .ci-card so the
                   list does not jump when the real row replaces it. */
                .ci-skeleton {
                    background: #fff;
                    border: 1px solid var(--ci-border);
                    box-shadow: 0 1px 2px oklch(0.7 0.02 95 / 0.18);
                    padding: 12px 14px;
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 10px;
                    animation: ci-row-in 0.25s ease both;
                }

                .ci-skeleton .sk {
                    background: linear-gradient(90deg,
                            oklch(0.93 0.006 95) 25%,
                            oklch(0.97 0.004 95) 37%,
                            oklch(0.93 0.006 95) 63%);
                    background-size: 400% 100%;
                    animation: ci-sk-shimmer 1.2s ease-in-out infinite;
                    border-radius: 4px;
                    height: 11px;
                }

                @keyframes ci-sk-shimmer {
                    from { background-position: 100% 50%; }
                    to   { background-position: 0 50%; }
                }

                @media (prefers-reduced-motion: reduce) {
                    .ci-skeleton .sk { animation: none; }
                }

                /* Actions ride the bottom of the cart column so Clear / Order /
                   Preview stay reachable without scrolling past a long list.
                   The scroll container is the cart's own wrapper in pos.blade. */
                /* A flex footer, not sticky: the bar is the last thing in the
                   column, so bottom:0 had no travel and never lifted off the
                   page. Sitting outside the scroll region keeps it in view. */
                .cart-actions {
                    flex-shrink: 0;
                    background: #fff;
                    padding: 10px 8px 8px;
                    border-top: 1px solid var(--ci-border);
                }

            </style>

            @php
                $locked = in_array($this->document_type, ['Deposit', 'Completed', 'Cancelled', 'Returned']);
            @endphp

            <div id="cartLines" class="flex flex-col gap-1">

                @forelse ($cart as $item)
                    <div class="w-full mx-auto animate-add">
                        {{-- data-* drive the right-click quantity stepper (qty_stepper.js):
                             it needs the row index to adjust, and the name/qty to show. --}}
                        <div class="ci-card" data-cart-index="{{ $loop->index }}"
                            data-cart-id="{{ $item['id'] }}" data-cart-stock="{{ $item['stock'] ?? '' }}"
                            data-cart-name="{{ $item['name'] ?? '' }}" data-cart-qty="{{ $item['qty'] ?? 0 }}"
                            data-cart-locked="{{ $locked ? '1' : '0' }}">

                            {{-- ===== Header (clickable) ===== --}}
                            {{-- Expand/collapse is browser-only: nothing but this panel
                                 ever read $openIndex, so a Livewire round-trip per tap
                                 bought nothing. The open row is re-applied after each
                                 morph, keyed on the item id. --}}
                            <div class="ci-header {{ $locked ? 'ci-locked' : '' }}"
                                @unless ($locked) onclick="toggleCartRowInstant(this)" @endunless>

                                <div class="ci-left">
                                    <div class="ci-controls">
                                        @unless ($locked)
                                            <span
                                                class="ci-chevron">▾</span>
                                            {{-- Double-click, not single: a stray tap beside the qty
                                                 field used to drop the line outright. The onclick only
                                                 swallows the bubble, so one click neither removes the
                                                 line nor toggles the row open. --}}
                                            <button class="ci-remove" title="Double-click to remove"
                                                onclick="event.stopPropagation()"
                                                wire:dblclick.stop="removeItem({{ $item['id'] }})">
                                                <i class="fa-solid fa-delete-left fa-flip-horizontal"></i>
                                            </button>
                                        @endunless
                                    </div>

                                    <div class="text-left" style="min-width:0;">
                                        <p class="ci-name number-change">
                                            <span class="ci-order-no">{{ $item['order_no'] }}.</span>
                                            <span class="ci-description ">
                                                {{ $item['name'] }}
                                            </span>

                                            {{-- qty lives in the right column, not here: a long
                                                 name wraps and would push it to a ragged position
                                                 mid-sentence instead of lining up with the total. --}}
                                        </p>

                                        @if ($this->document_type == 'Deposit')
                                            <span class="ci-badge ci-badge-ok">
                                                <i class="fa-solid fa-boxes-stacked"></i>
                                                កាត់ស្តុរូច
                                            </span>
                                        @else
                                            @if ($item['stock'] < $item['qty'] && strtolower($item['type']) == 'product')
                                                <span class="ci-badge ci-badge-danger">
                                                    <i class="fa-solid fa-boxes-stacked"></i>
                                                    ស្តុកមិនគ្រប់
                                                </span>
                                            @endif
                                        @endif

                                        <p class="ci-price-line number-change">
                                            តម្លៃ:
                                            @if ($item['discount_percent'] != 0)
                                                <del>{{ $priceFmt($item['price']) }}</del>
                                                <span class="ci-disc-price">
                                                    {{ $priceFmt($item['discount_price']) }}</span>
                                                {{ $this->currency_name }}
                                            @else
                                                {{ $priceFmt($item['price']) }} {{ $this->currency_name }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                {{-- amount + currency on ONE line --}}
                                <div class="ci-total">
                                    @if ($cart_mode != 'expence')
                                        <div class="ci-qty">× <span
                                                class="ci-qty-val">{{ $qtyFmt($item['qty']) }}</span>
                                            {{ $item['unit'] }}</div>
                                    @endif

                                    @if ($item['discount_percent'] != 0)
                                        <del class="number-change">{{ $fmtLine($item['price'], $item['qty']) }}
                                            {{ $this->currency_name }}</del>
                                        <span
                                            class="ci-total-amount number-change">{{ $fmtLine($item['discount_price'], $item['qty']) }}</span><span
                                            class="ci-total-currency">{{ $this->currency_name }}</span>
                                    @else
                                        <span
                                            class="ci-total-amount number-change">{{ $fmtLine($item['price'], $item['qty']) }}</span><span
                                            class="ci-total-currency">{{ $this->currency_name }}</span>
                                    @endif

                                    @unless ($locked || $cart_mode == 'expence')
                                        {{-- stopPropagation so stepping does not also toggle the row --}}
                                        <div class="ci-qty-step" onclick="event.stopPropagation()">
                                            <button type="button" class="ci-step"
                                                onclick="stepCartQty(this, -1)" aria-label="Decrease">-</button>
                                            <button type="button" class="ci-step"
                                                onclick="stepCartQty(this, 1)" aria-label="Increase">+</button>
                                        </div>
                                    @endunless
                                </div>
                            </div>

                            {{-- ===== Dropdown editor ===== --}}
                            <div class="ci-panel bonus">
                                <div class="ci-panel-inner">

                                    @if ($item['type'] == 'product' || $item['type'] == 'service')
                                        <div>
                                            <label class="ci-label">ចំនួន · Qty</label>
                                            <input type="number" min="0.01" max="{{ $item['stock'] }}"
                                                id="qty_order_{{ $loop->index }}" step="0.01"
                                                wire:model.lazy="cart.{{ $loop->index }}.qty"
                                                wire:change="recalcLine({{ $loop->index }}, 'qty')"
                                                class="ci-input" />
                                        </div>

                                        <div>
                                            <label class="ci-label">តម្លៃ · Price</label>
                                            <input type="number" min="0" step="0.000001"
                                                wire:key="price-{{ $loop->index }}-{{ $this->cart[$loop->index]['price'] }}-{{ $this->factor }}-{{ $this->priceInputNonce }}"
                                                value="{{ $priceInput($item['price']) }}"
                                                wire:change="recalcLine({{ $loop->index }}, 'price', $event.target.value)"
                                                class="ci-input" />
                                        </div>

                                        <div>
                                            <label class="ci-label">បញ្ចុះ · Disc %</label>
                                            <input type="number" min="0" max="100"
                                                wire:key="discount-{{ $loop->index }}-{{ $this->cart[$loop->index]['discount_percent'] ?? 0 }}-{{ $this->priceInputNonce }}"
                                                value="{{ $item['discount_percent'] ?? 0 }}"
                                                wire:change="recalcLine({{ $loop->index }}, 'discount_percent', $event.target.value)"
                                                @disabled(!($item['allow_discount'] ?? true))
                                                title="{{ ($item['allow_discount'] ?? true) ? '' : 'ទំនិញនេះមិនអនុញ្ញាតឲ្យបញ្ចុះតម្លៃទេ · Discount not allowed for this item' }}"
                                                class="ci-input @if (!($item['allow_discount'] ?? true)) opacity-50 cursor-not-allowed @endif" />
                                        </div>
                                    @else
                                        <div>
                                            <label class="ci-label">ចំនួន · Qty</label>
                                            <input type="number" min="0.01" step="0.01"
                                                id="qty_order_{{ $loop->index }}"
                                                wire:model.lazy="cart.{{ $loop->index }}.qty"
                                                wire:change="recalcLine({{ $loop->index }}, 'qty')"
                                                class="ci-input" />
                                        </div>
                                        <div>
                                            <label class="ci-label">តម្លៃ · Price</label>
                                            <input type="number" min="0" step="0.000001"
                                                wire:key="price-{{ $loop->index }}-{{ $this->cart[$loop->index]['price'] }}-{{ $this->factor }}-{{ $this->priceInputNonce }}"
                                                value="{{ $priceInput($item['price']) }}"
                                                wire:change="recalcLine({{ $loop->index }}, 'price', $event.target.value)"
                                                class="ci-input" />
                                        </div>
                                        <div class="ci-field-full">
                                            <label class="ci-label">សម្រាប់ · For</label>
                                            <input type="text" maxlength="255"
                                                wire:model.lazy="cart.{{ $loop->index }}.expence_for"
                                                class="ci-input" />
                                        </div>
                                    @endif

                                    @if ($item['track_stock'] == 1 && $item['type'] != 'expence')
                                        <div class="ci-lot-row">
                                            <span class="ci-lot-label"><i class="fa-solid fa-layer-group"></i>&nbsp;
                                                Manage Lot</span>
                                            <div class="ci-lot-btns">
                                                <button type="button" class="ci-lot-btn"
                                                    onclick="openLotModal({{ $loop->index }}, {{ $item['id'] }}, '{{ $item['name'] }}', {{ $item['qty'] }})">
                                                    +
                                                </button>
                                                <button type="button" class="ci-lot-btn"
                                                    wire:click="viewLots({{ $loop->index }})">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                </div>
                            </div>

                        </div>
                    </div>


                @empty

                    {{-- data-cart-empty: hidden while the add placeholder shows, so
                         the first item does not sit under "No items in cart". --}}
                    <div data-cart-empty
                        class="flex flex-col items-center justify-center py-6 px-6 text-center">

                        <img src="{{ asset('assets/defult/cart.png') }}" alt="Empty Cart"
                            class="w-24 h-24 lg:w-28 lg:h-28 object-contain opacity-80">

                        <h3 class="mt-4 text-lg font-bold text-slate-800">
                            {{ __('No items in cart') }}
                        </h3>

                        <p class="mt-1 text-sm text-slate-500 max-w-xs">
                            {{ __('Scan a barcode or select a product to start building the order.') }}
                        </p>

                    </div>
                @endforelse
                {{-- Totals --}}

                <div id="total" class="grid grid-cols-1 gap-1 p-2">
                    <div class="flex items-end flex-col justify-between">
                        @if ($cart_mode == 'expence')

                            <p class="font-semibold">
                                តម្លៃសរុប :
                                {{ $money($this->totalsDisplay['grand_total']) }}

                                {{ $this->currency_name }}
                            </p>

                            @if ($this->factor != 1)
                                <p class="font-semibold">
                                    តម្លៃសរុប ជា USD : {{ $fmtUsd($this->totals['grand_total']) }} $
                                </p>
                            @endif
                        @else
                            <p class="text-sm">
                                សរុបរង:
                                {{ $money($this->totalsDisplay['total_original']) }}
                                {{ $this->currency_name }}
                            </p>

                            <p class="text-sm">
                                បញ្ចុះតម្លៃ:
                                {{ $money($this->totalsDisplay['total_discount']) }}
                                {{ $this->currency_name }}
                            </p>

                            @if ($this->totalsDisplay['vat_status'] > 0)
                                <p class="text-sm">
                                    VAT {{ (int) $this->totalsDisplay['vat_status'] }}%:
                                    {{ $money($this->totalsDisplay['total_vat_amount']) }}
                                    {{ $this->currency_name }}
                                </p>
                            @endif

                            <p class="font-semibold">
                                តម្លៃសរុប:
                                {{ $money($this->totalsDisplay['grand_total']) }}
                                {{ $this->currency_name }}
                            </p>

                            @if ($this->factor != 1)
                                <p class="font-semibold">
                                    តម្លៃសរុប USD:
                                    {{ $fmtUsd($this->totals['grand_total']) }} $
                                </p>
                            @endif
                        @endif


                        <input type="hidden" id="total_amount" value="{{ $fmtUsd($this->totals['grand_total']) }}">
                        <input type="hidden" id="currency_name" value="{{ $currency_name }}">
                        <input type="hidden" id="currency_display_symbol" value="{{ $this->currency }}">
                        <input type="hidden" id="riel_factor" value="{{ $this->getRielCurrency()->factor }}">
                        <input type="hidden" id="document_type" value="{{ $this->document_type }}">

                        @if ($this->factor != 1)
                            <div class="w-full flex justify-between">

                                <div class="flex items-center">


                                    <span
                                        class="inline-flex items-center bg-brand-softer border border-brand-subtle text-fg-brand-strong text-xs font-medium px-1.5 py-0.5 rounded-sm">

                                        1$ : {{ (float) $factor }}&ensp;{{ $currency }}
                                    </span>
                                </div>



                            </div>


                            <input type="hidden" id="converted_total_amount"
                                value="{{ $money_no_format($this->totalsDisplay['grand_total']) }}">
                        @else
                            <input type="hidden" id="converted_total_amount"
                                value="{{ $money_no_format($this->totalsDisplay['grand_total']) }}">
                        @endif

                    </div>
                    <div class="w-full flex  items-end justify-between gap-2">
                        <div class="relative w-full min-w-[180px]">

                            <!-- Icon -->
                            <i
                                class="fa-solid fa-dollar-sign  absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>

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

                        </div>
                        @if ($cart_mode == 'expence')
                        @else
                            {{-- fluid on phones, fixed once there's room for it --}}
                            <div id="list_main" class="relative col-span-2 w-full sm:w-[300px]">

                                <!-- Icon -->
                                <i
                                    class="fa-solid fa-user-tie absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>

                                <!-- Input -->
                                <input type="text" id="customerSearch"
                                    placeholder="ភ្ញៀវទូទៅ / Search Customer..." autocomplete="off"
                                    class="w-full border border-gray-300 rounded-xl
                                       pl-10 pr-4 py-2 h-[42px]
                                       shadow-sm focus:ring-2 focus:ring-blue-300
                                       focus:outline-none text-gray-700">

                                <!-- Hidden -->
                                <input type="hidden" id="customerValue" wire:model.live="customer_id">

                                <!-- Dropdown -->
                                <ul id="customerList"
                                    class="hidden absolute left-0 right-0 top-full mt-1 z-50
                                       bg-white border border-gray-200
                                       rounded-xl shadow-lg max-h-60 overflow-auto">
                                </ul>

                            </div>
                        @endif
                    </div>

                    {{-- Only shown when the user actually has a choice of sale warehouse.
                         Locks once the cart has lines: one document, one warehouse. --}}
                    @if (count($saleWarehouses) > 1)
                        <div class="mt-3">
                            <div class="relative">
                                <i
                                    class="fa-solid fa-warehouse absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <select id="saleWarehouseSelect" wire:model.live="sale_warehouse_id"
                                    @disabled($this->saleWarehouseLocked)
                                    class="w-full border border-gray-300 rounded-xl
                                           pl-10 pr-8 py-2 h-[42px] appearance-none
                                           shadow-sm focus:ring-2 focus:ring-blue-300
                                           focus:outline-none text-gray-700
                                           disabled:bg-gray-100 disabled:text-gray-500
                                           disabled:cursor-not-allowed">
                                    <option value="">{{ __('Select Warehouse') }}...</option>
                                    @foreach ($saleWarehouses as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @if ($this->saleWarehouseLocked)
                                    <i
                                        class="fa-solid fa-lock absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($this->saleWarehouseLocked)
                                    {{ __('Locked while the cart has items. Clear the cart to change warehouse.') }}
                                @else
                                    {{ __('All items on this document are sold from one warehouse.') }}
                                @endif
                            </p>
                        </div>
                    @endif

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


                    <div id="customer_info">
                        <div class="text-left">
                            @if ($customer_name != 'Walk-in Customer')
                                @if (filled($this->customer_name) ||
                                        filled($this->customer_address1) ||
                                        filled($this->customer_address2) ||
                                        filled($this->customer_contact_name) ||
                                        filled($this->customer_contact_phone))

                                    @if (filled($this->customer_name))
                                        <div id="sell_to_name" class="bold">
                                            {{ $this->customer_name }}
                                        </div>
                                    @endif

                                    @if (filled($this->customer_address1))
                                        <div id="sell_to_address1">
                                            {{ $this->customer_address1 }}
                                        </div>
                                    @endif

                                    @if (filled($this->customer_address2))
                                        <div id="sell_to_address2">
                                            {{ $this->customer_address2 }}
                                        </div>
                                    @endif

                                    @if (filled($this->customer_contact_name))
                                        <div id="sell_to_contact_name">
                                            ATT To: {{ $this->customer_contact_name }}
                                        </div>
                                    @endif

                                    @if (filled($this->customer_contact_phone))
                                        <div id="sell_to_phone">
                                            Mobile: {{ $this->customer_contact_phone }}
                                        </div>
                                    @endif

                                @endif
                            @else
                                <div id="sell_to_name" class="bold">Walk-in Customer</div>
                            @endif

                        </div>
                    </div>
                    <div id="table_footer">
                        <div>
                            <div id="table_footer_description"></div>
                            <!-- CURRENCY RATE -->

                        </div>
                    </div>

                    <div id="invoice-table">
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th style="max-width=5%;">{{ __('No.') }}</th>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('QTY') }}</th>
                                    <th>{{ __('Unit') }}</th>

                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Dis') }}</th>
                                    <th>{{ __('Total') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($cart as $item)
                                    @if ($item['type'] == 'service')
                                        <tr>
                                            <td colspan="5" style="text-align:end; font-weight:bold ">
                                                {{ $item['name'] }}
                                            </td>
                                            <td style="text-align:right; ">
                                                {{ $fmtLine($item['discount_price'], $item['qty']) }}
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td style="text-align:center;">{{ $item['order_no'] }}</td>
                                            <td style="text-align:start">{{ $item['name'] }}</td>
                                            <td style="text-align:center;">{{ $qtyFmt($item['qty']) }}</td>
                                            {{-- QTY --}}
                                            <td style="text-align:center">{{ $item['unit'] }}</td>
                                            {{-- Unit --}}
                                            <td style="text-align:right;">{{ $priceFmt($item['price']) }}</td>
                                            <td style="text-align:right;">{{ $item['discount_percent'] }}%</td>
                                            <td style="text-align:right;">
                                                {{ $fmtLine($item['discount_price'], $item['qty']) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                {{-- Sub Total --}}
                                <tr class="total_print">
                                    <td colspan="6" style="text-align:right; ">
                                        Sub Total ({{ $currency }})
                                    </td>
                                    <td style="text-align:right; ">
                                        {{ $this->totalsDisplay['total_original'] }}
                                    </td>
                                </tr>
                                {{-- Sub Total --}}
                                <tr class="total_print">
                                    <td colspan="6" style="text-align:right; ">
                                        Discount({{ $currency }})
                                    </td>
                                    <td style="text-align:right; ">
                                        {{ $this->totalsDisplay['total_discount'] }}
                                    </td>
                                </tr>
                                {{-- @if ($this->document_type == 'Completed') --}}


                                <tr class="total_print">
                                    <td colspan="6" style="text-align:right; ">
                                        Grand Total ({{ $currency }})
                                    </td>
                                    <td style="text-align:right; ">
                                        {{ $this->totalsDisplay['grand_total'] }}
                                    </td>
                                </tr>




                            </tbody>
                        </table>
                    </div>

                </div>


                {{-- ===== Customer Display data bridge ===== --}}
                @php
                    $displayPayload = [
                        'mode' => $cart_mode,
                        'doc_no' => $this->document_no,
                        'currency' => $this->currency_name,
                        'customer' => $customer_name ?? '',
                        'items' => collect($cart)
                            ->map(
                                fn($i) => [
                                    'name' => $i['name'],
                                    'qty' => $qtyFmt($i['qty']),
                                    'unit' => $i['unit'] ?? '',
                                    'price' => $priceFmt(
                                        $i['discount_percent'] != 0 ? $i['discount_price'] : $i['price'],
                                    ),
                                    'line' => $fmtLine(
                                        $i['discount_percent'] != 0 ? $i['discount_price'] : $i['price'],
                                        $i['qty'],
                                    ),
                                    'disc' => (float) $i['discount_percent'],
                                ],
                            )
                            ->values(),
                        'sub' => $money($this->totalsDisplay['total_original']),
                        'discount' => $money($this->totalsDisplay['total_discount']),
                        'grand' => $money($this->totalsDisplay['grand_total']),
                        'grand_usd' => $this->factor != 1 ? $fmtUsd($this->totals['grand_total']) : null,
                    ];
                @endphp
                <div id="cart-sync" hidden data-payload="{{ json_encode($displayPayload, JSON_UNESCAPED_UNICODE) }}">
                </div>



            </div>

        </div>
        </div>

        {{-- Action bar: a flex footer, NOT sticky. It is the last
             thing in the column, so bottom:0 had no travel and never
             lifted. Outside the scroll region it is always visible. --}}
        <div class="grid grid-cols-4 gap-2 cart-actions">




            <!-- Clear — double-click, same guard as removing a single line -->
            <button wire:dblclick="clearCart" title="Double-click to clear the cart"
                class="bg-red-500 hover:bg-red-600 text-white font-small px-4 py-2 rounded-xl shadow-md transition">
                <i class="fa-solid fa-trash-can mr-1"></i> Clear
            </button>

            @if ($cart_mode == 'expence')
                <!-- Expense -->
                <button onclick="openExpenseModal()"
                    class="bg-orange-500 hover:bg-orange-600 text-white font-small px-4 py-2 rounded-xl shadow-md transition">
                    <i class="fa-solid fa-wallet mr-1"></i> Pay Expense
                </button>
            @else
                <!-- Saved Order -->
                <button onclick="openSaleOrderModal()"
                    class="bg-indigo-500 hover:bg-indigo-600 text-white font-small px-4 py-2 rounded-xl shadow-md transition">
                    <i class="fa-solid fa-cart-shopping"></i> View
                </button>
                @if (Auth::user()->hasPermission('pos_sale.sell'))
                    @if ($this->document_no != 'NA')
                        <!-- Update Sale Order -->
                        <button onclick="update_sale_order()"
                            class="bg-blue-500 hover:bg-blue-600 text-white font-small px-2 py-2 rounded-xl shadow-md transition">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> Update
                        </button>
                    @else
                        <!-- Save Order -->
                        <button onclick="Save_Sale_Order()"
                            class="bg-blue-500 hover:bg-blue-600 text-white font-small px-4 py-2 rounded-xl shadow-md transition">
                            <i class="fa-solid fa-hand-holding-dollar"></i> Order
                        </button>
                    @endif
                @endif
                @if ($this->document_id != 0)
                    <button onclick="openSaleLine()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-small px-4 py-2 rounded-xl shadow-md transition">
                        <i class="fa-solid fa-circle-info"></i> Info
                    </button>
                @endif
                {{-- Preview needs NO permission: it prints this cart as it
                     stands, writes nothing and issues no number. --}}
                @if ($this->count_cart > 0)
                    <button wire:click="openDocumentPreview"
                        class="bg-slate-600 hover:bg-slate-700 text-white font-small px-4 py-2 rounded-xl shadow-md transition">
                        <i class="fa-solid fa-eye"></i> Preview
                    </button>
                @endif

            @endif


        </div>



    </div>




</div>
