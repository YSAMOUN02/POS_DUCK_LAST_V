@extends('backend.master')

@section('content')
    <div id="container" class="w-full grid grid-cols-1 m lg:grid-cols-8 gap-2 h-screen overflow-hidden">
        <div id="mainContent"
            class=" tab_control  lg:col-span-6 md:col-span-4 col-span-2  border-1 border-default border-dashed rounded-base">

            <div id="category_show"
                class=" flex justify-between  mb-2 border-b border-default  mx-5 sticky top-0 bg-amber-400 z-10">
                <div class="flex items-center px-4 py-3">
                    @csrf

                    <!-- Search group: select + input joined -->
                    <div
                        class="flex items-stretch h-10 rounded-full bg-white shadow-sm
                                border border-gray-300 overflow-hidden
                                focus-within:ring-2 focus-within:ring-brand focus-within:border-brand
                                transition-all duration-200">

                        <!-- Field select -->
                        <select id="field-select"
                            class="h-full pl-4 pr-8 text-sm font-medium text-gray-600 bg-gray-50
                                   border-0 border-r border-gray-200 rounded-none
                                   focus:ring-0 focus:outline-none cursor-pointer hover:bg-gray-100 transition">
                            <option value="bar_code" selected>Barcode</option>
                            <option value="code">Code</option>
                            <option value="name">Name</option>
                            <option value="description">Description</option>
                        </select>

                        <!-- Input -->
                        <div class="relative flex items-center">
                            <i
                                class="fa-solid fa-magnifying-glass absolute left-3 text-gray-400 text-sm pointer-events-none"></i>
                            <input type="text" id="search-dropdown" placeholder="Scan or search..." autocomplete="off"
                                class="h-full w-56 lg:w-64 pl-9 pr-3 text-sm border-0
                                       focus:ring-0 focus:outline-none placeholder:text-gray-400">
                        </div>

                    </div>

                    @if (($productViewMode ?? 'grid') === 'list')
                        <div class="relative ml-2">
                            <button type="button" id="listColumnsBtn"
                                class="h-10 px-3 rounded-full bg-white shadow-sm border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-table-columns"></i> Columns
                            </button>
                            <div id="listColumnsPanel"
                                class="hidden absolute left-0 top-12 z-50 w-56 rounded-xl border border-gray-200 bg-white shadow-lg p-3">
                                <div class="text-xs font-semibold text-gray-400 uppercase mb-2">Show columns</div>
                                <div id="listColumnsOptions" class="space-y-1.5"></div>
                            </div>
                        </div>
                    @endif
                </div>


                <ul class="tab-track flex items-center gap-1.5 overflow-x-auto px-2 py-2" id="category-tabs">

                    <li>
                        <button data-category="topsale" class="tab-pill">
                            Top Product
                        </button>
                    </li>


                    @foreach ($categories as $categoryName => $products)
                        <li>
                            <button class="tab-pill" data-category="{{ $categoryName }}">
                                {{ $categoryName }}
                            </button>
                        </li>
                    @endforeach
                </ul>


            </div>

            <div id="default-styled-tab-content">
                <div class="hidden rounded-base bg-neutral-secondary-soft" id="styled-profile" role="tabpanel"
                    aria-labelledby="profile-tab">
                    {{-- Tab Control  --}}
                    <div class="w-full grid grid-cols-5 gap-2 p-3 bg-[#F6F5FF]  mb-12 pb-16">
                        Top
                    </div>
                </div>
            </div>
            <div class="overflow-auto" id="tab-content">


            </div>

        </div>



        {{-- Drag divider --}}
        <div id="resizer"
            class="w-1.5 shrink-0 cursor-col-resize bg-gray-200 hover:bg-blue-400 transition-colors relative z-20">
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                        h-10 w-1 rounded-full bg-gray-400">
            </div>
        </div>

        <div id="sidebar" class="flex flex-col max-h-full shrink-0 w-full lg:w-[380px]">
            <div id="inner-sidebar" class="sticky top-0 bg-slate-100 border-l border-default h-full">
                <div class="overflow-y-auto bg-white w-full h-full">
                    @livewire('cart')
                </div>
            </div>
        </div>
        {{-- Mobile: floating cart toggle --}}
        <button id="mobileCartToggle" type="button" aria-label="Toggle cart">
            <i class="fa-solid fa-cart-shopping"></i>
            <span id="mobileCartBadge">0</span>
        </button>
    </div>

    <script>
        let factor = @json($factor);
        let currency_name = @json($currency_name);
        const is_admin = @json(Auth::user()->role == 'admin');
        // Decided per-user via the pos_sale.view_grid / pos_sale.view_list
        // permission pair (Manage Permissions) — not a manual toggle here.
        const productViewMode = @json($productViewMode ?? 'grid');
        // Warehouse-to-warehouse "transfer" and same-warehouse bin-to-bin
        // "movement" share one modal/endpoint — show the "Transfer" trigger
        // if either is granted; the backend enforces exactly which one this
        // particular request actually needs.
        const canTransferStock = @json(Auth::user()->hasPermission('warehouse.transfer'));
        const canMoveStock = @json(Auth::user()->hasPermission('warehouse.movement'));
        const canSellPos = @json(Auth::user()->hasPermission('pos_sale.sell'));



        window.addEventListener("change-currency", (e) => {
            factor = Number(e.detail[0].factor);
            currency_name = e.detail[0].currency_name;

            document.querySelectorAll(".pricing").forEach((element) => {
                const basePrice = parseFloat(element.getAttribute("data-base-price")) || 0;

                element.textContent = fmtMoney(
                    basePrice,
                    factor,
                    currency_name,
                    'មិនមានតម្លៃ'
                );
            });
        });






        document.addEventListener('click', function(e) {
            const card = e.target.closest('.card_style');
            if (!card) return;

            // ✅ Sale order: allow every item, no "No Stock" block

            const count = 8;
            const burst = document.createElement('div');

            burst.className = 'cart-burst';
            burst.style.left = e.pageX + 'px';
            burst.style.top = e.pageY + 'px';

            for (let i = 0; i < count; i++) {
                const icon = document.createElement('span');

                const isCart = Math.random() > 0.5;
                icon.className = `cart-icon ${isCart ? 'cart' : 'plus'}`;
                icon.textContent = isCart ? '🛒' : '✅';

                const angle = Math.random() * Math.PI * 2;
                const distance = 100;

                icon.style.setProperty('--x', `${Math.cos(angle) * distance}px`);
                icon.style.setProperty('--y', `${Math.sin(angle) * distance}px`);
                icon.style.animationDelay = `${i * 0.03}s`;

                burst.appendChild(icon);
            }

            document.body.appendChild(burst);
            setTimeout(() => burst.remove(), 1000);
        });
        // function closeAllItems() {
        //     document.querySelectorAll('.bonus').forEach(b => {
        //         b.classList.add('hidden');
        //     });

        //     document.querySelectorAll('.arrow').forEach(a => {
        //         a.classList.remove('rotate-180');
        //     });
        // }

        function openItem(card) {
            const body = card.querySelector('.bonus');
            const arrow = card.querySelector('.arrow');

            body.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        }

        const tabs = document.querySelectorAll('#category-tabs button');
        const tabContent = document.getElementById('tab-content');

        // Convert Blade categories JSON into JS object
        let productsByCategory = @json($categories);
        let topProducts = @json($top_products ?? []);

        // Columns beyond the always-shown "Product" (name) column — the
        // cashier picks which of these they want via the Columns button,
        // saved per-user so it's remembered across logins. Declared this
        // early (before restoreActiveTab() below triggers the first render)
        // so activeListColumns is already initialized by the time it's read.
        // width is a cap (not a stretch target) — Product always absorbs
        // whatever space is left, these stay compact instead of spreading
        // out to fill the table.
        const LIST_COLUMN_DEFS = {
            price:       { label: 'Price',       align: 'text-right',   width: '90px' },
            stock:       { label: 'Stock',       align: 'text-center',  width: '90px' },
            category:    { label: 'Category',    align: 'text-left',    width: '120px' },
            barcode:     { label: 'Barcode',     align: 'text-left',    width: '110px' },
            code:        { label: 'Code',        align: 'text-left',    width: '90px' },
            unit:        { label: 'Unit',        align: 'text-center',  width: '70px' },
            description: { label: 'Description', align: 'text-left',    width: '200px' },
        };
        const DEFAULT_LIST_COLUMNS = ['price', 'stock', 'category'];
        const LIST_COLUMNS_KEY = 'pos_list_columns_{{ Auth::id() }}';

        function loadListColumns() {
            try {
                const saved = JSON.parse(localStorage.getItem(LIST_COLUMNS_KEY));
                if (Array.isArray(saved)) {
                    return saved.filter((c) => LIST_COLUMN_DEFS[c]);
                }
            } catch (e) {}
            return DEFAULT_LIST_COLUMNS.slice();
        }

        let activeListColumns = loadListColumns();

        function saveListColumns() {
            localStorage.setItem(LIST_COLUMNS_KEY, JSON.stringify(activeListColumns));
        }

        // Column order is always the fixed LIST_COLUMN_DEFS order (Price,
        // Stock, Category, Barcode, Code, Unit, Description) regardless of
        // the order the cashier happened to check them in.
        function orderedActiveColumns() {
            return Object.keys(LIST_COLUMN_DEFS).filter((key) => activeListColumns.includes(key));
        }
        // Keeps stock + the live exchange rate in sync across POS terminals —
        // polled on a timer (see setInterval below) rather than tied to any
        // one action, so it picks up *any* stock-changing transaction
        // (sale, return, purchase/GRN, adjustment) no matter who made it.
        async function reloadProducts(opts = {}) {
            const silent = opts.silent === true;
            try {
                const res = await fetch('/pos/products');
                const data = await res.json();

                productsByCategory = data.categories;

                const newFactor = Number(data.factor);
                const rateChanged = newFactor !== Number(factor) || data.currency_name !== currency_name;
                if (rateChanged) {
                    factor = newFactor;
                    currency_name = data.currency_name;

                    document.querySelectorAll(".pricing").forEach((element) => {
                        const basePrice = parseFloat(element.getAttribute("data-base-price")) || 0;
                        element.textContent = fmtMoney(basePrice, factor, currency_name, 'មិនមានតម្លៃ');
                    });

                    // Push the fresh rate into the cart too, so a sale
                    // already in progress reflects it instead of the stale
                    // rate it was opened with.
                    if (window.Livewire) {
                        Livewire.dispatch('refreshCurrency');
                    }
                }

                // Refresh whatever's actually on screen — active search
                // results or the active category tab — without the
                // "Loading..." flash so it doesn't interrupt the cashier.
                const query = searchInput_product.value.trim();
                if (query) {
                    await doSearch({ silent: true });
                } else {
                    await renderCategoryProducts(current_tab, { silent });
                }
            } catch (err) {
                console.error("Failed to reload products:", err);
            }
        }

        // Background sync: covers stock changes from any transaction
        // (sale/return/purchase/adjustment) made by any user/terminal, and
        // exchange-rate changes made by an admin — not just this session's
        // own actions.
        const PRODUCT_SYNC_INTERVAL_MS = 25000;
        setInterval(() => reloadProducts({ silent: true }), PRODUCT_SYNC_INTERVAL_MS);

        // Browsers heavily throttle setInterval in background/unfocused tabs
        // (can stretch to once a minute or more) — a "standby" terminal that
        // isn't the active window/tab may not see the 25s poll fire on time.
        // Refresh immediately the moment the cashier actually looks back at
        // the screen, instead of waiting on a throttled timer.
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible") {
                reloadProducts({ silent: true });
            }
        });
        window.addEventListener("focus", () => reloadProducts({ silent: true }));


        // Helper: sort products by total_stock DESC
        function sortByStock(products) {
            return products.sort((a, b) => b.total_stock - a.total_stock);
        }
        let current_tab = 'NA';

        // ── remember user's active tab ──
        const TAB_KEY = 'pos_active_tab_{{ Auth::id() }}';


        // Event listener for tabs
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const category = tab.dataset.category;
                current_tab = category;
                renderCategoryProducts(category);

                // Update active pill styling
                tabs.forEach(t => t.classList.remove('tab-active'));
                tab.classList.add('tab-active');
                localStorage.setItem(TAB_KEY, category);
            });
        });
        // Restore user's last tab (fall back to first tab = Top Product)
        (function restoreActiveTab() {
            if (!tabs.length) return;
            const saved = localStorage.getItem(TAB_KEY);
            const target = saved
                ? [...tabs].find(t => t.dataset.category === saved)
                : null;
            (target || tabs[0]).click();
        })();

        function round2(value) {
            return Number(Math.round((value + Number.EPSILON) * 100) / 100);
        }
        // Render Category Products
        // Render Category Products
        async function renderCategoryProducts(category, opts = {}) {
            const silent = opts.silent === true;
            if (!silent) {
                tabContent.innerHTML = '<p class="p-4">Loading...</p>';
                document.body.style.cursor = 'wait';
            }

            try {
               let products = [];
                    if (category === 'top') {
                        products = sortByStock(Object.values(productsByCategory).flat()).slice(0, 30);
                    } else if (category === 'topsale') {
                        products = topProducts;                         // keep server sales order — no re-sort
                    } else {
                        products = sortByStock(productsByCategory[category] || []);
                    }

                tabContent.innerHTML = productViewMode === 'list'
                    ? renderProductListTable(products)
                    : renderProductGrid(products);

                initAddToCartButtons();

            } catch (err) {
                if (!silent) {
                    tabContent.innerHTML = '<p class="p-4 text-red-500">Failed to load products.</p>';
                }
                console.error(err);
            } finally {
                if (!silent) document.body.style.cursor = 'default';
            }
        }

        // Grid card markup — extracted so both the category-tab renderer and
        // (via renderProductListTable's sibling) the list-mode renderer can
        // share the same product-fetch/sort logic above.
        function renderProductGrid(products) {
                let html =
                    '<div class="min_heigh_70 w-full  product-grid p-3  bg-[#F6F5FF] mb-12 pb-16">';

                products.forEach((product, index) => {
                    const imageSrc = product.image ?
                        `/assets/startic_img/${encodeURIComponent(product.image)}` :
                        '/assets/defult/placeholder.png';

                    const price = Number(product.sell_price || 0);
                    const finalPrice = price;

                    const discountPercent = Number(product.discount_percent || 0);
                    const discountedPrice = round2(finalPrice - (finalPrice * discountPercent / 100));

                    let stockColor = 'text-gray-400';
                    if (product.total_stock > 0) {
                        const stockPercent = (product.total_stock / product.max_stock) * 100;
                        if (product.total_stock > product.max_stock) {
                            stockColor = 'text-green-600';
                        } else if (stockPercent < 50) {
                            stockColor = 'text-red-500';
                        } else {
                            stockColor = 'text-green-600';
                        }
                    }
                    let style_click = `card_style_success`;


                    html += `
                    <div class="card_style ${style_click} bg-neutral-primary-soft block max-w-sm border border-default shadow-xs relative">
                        <button class="add-to-cart-btn w-full flex flex-col h-full" data-product="${escAttr(JSON.stringify(product))}">

                            <!-- IMAGE -->
                            <div class="relative w-full">
                                <img id="product-image${product.id}" class="object-cover w-full" loading="lazy"
                                    style="max-height:150px;min-height:150px;"
                                    src="${imageSrc}" onerror="this.onerror=null;this.src='/assets/defult/placeholder.png'"
                                    alt="${escAttr(product.name)}" />

                                <div class="info-wrap"
                                    data-name="${escAttr(product.name)}"
                                    data-stock="${escAttr(product.total_stock + ' ' + (product.unit || ''))}"
                                    data-barcode="${escAttr(product.bar_code || '-')}"
                                    data-category="${escAttr(product.category_name || '-')}"
                                    data-desc="${escAttr(product.description || '')}"
                                    >
                                    <i class="info fa-solid fa-circle-info text-blue-500 text-sm"></i>
                                </div>

                                ${product.discount_percent != 0 ? `
                                            <span class="absolute top-1 left-1 inline-flex items-center bg-red-500 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-sm shadow-md">
                                                <i class="fa-solid fa-tag mr-0.5"></i>${product.discount_percent}% Off
                                            </span>` : ''}
                            </div>

                            <!-- TEXT CONTENT -->
                            <div class="flex flex-col justify-between p-2 mt-2 h-[130px]">

                                <div class="product-name-card">
                                    ${escAttr(product.name)}
                                </div>

                                <div class="text-center mt-1">
                                    <p>
                                        ${product.track_stock ? `
                                                    <i class="${stockColor} fa-solid fa-boxes-stacked product-text-card"></i>
                                                    <span class="${stockColor} product-text-card">
                                                        ${
                                                            product.total_stock > 0
                                                                ? parseFloat(product.total_stock).toFixed(6).replace(/\.?0+$/, '') + ' ' + product.unit
                                                                : 'No stock'
                                                        }
                                                    </span>
                                                    &ensp;` : ''}
                                        ${product.discount_percent != 0
                                            ? `<br>
                                                    <del data-base-price="${finalPrice.toFixed(2)}" class="pricing text-gray-400 text-sm product-text-card">
                                                        ${fmtMoney(finalPrice, factor, currency_name, 'មិនទាន់កំណត់តម្លៃ')}
                                                    </del>
                                                    →
                                                    <span data-base-price="${discountedPrice.toFixed(2)}" class="${stockColor} pricing font-semibold text-sm product-text-card">
                                                        ${fmtMoney(discountedPrice, factor, currency_name, 'មិនមានតម្លៃ')}
                                                    </span>`
                                            : `<span data-base-price="${finalPrice.toFixed(2)}" class="pricing font-semibold text-sm product-text-card">
                                                        ${fmtMoney(finalPrice, factor, currency_name, 'មិនមានតម្លៃ')}
                                                    </span>`}
                                    </p>
                                </div>

                            </div>

                        </button>
                    </div>
                    `;
                }); // ← THIS closes forEach — the line you were missing

                html += '</div>';
                return html;
        }

        function renderListColumnsPanel() {
            const container = document.getElementById('listColumnsOptions');
            if (!container) return;
            container.innerHTML = Object.entries(LIST_COLUMN_DEFS).map(([key, def]) => `
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
                    <input type="checkbox" data-column="${key}" class="w-4 h-4 accent-amber-500" ${activeListColumns.includes(key) ? 'checked' : ''}>
                    ${def.label}
                </label>
            `).join('');
        }

        // List-mode row — same product fields as the grid card, laid out as
        // a table row: thumbnail+name (always shown) plus whichever optional
        // columns the cashier picked via the Columns button.
        function renderProductRow(product) {
            const imageSrc = product.image ?
                `/assets/startic_img/${encodeURIComponent(product.image)}` :
                '/assets/defult/placeholder.png';

            const price = Number(product.sell_price || 0);
            const finalPrice = price;
            const discountPercent = Number(product.discount_percent || 0);
            const discountedPrice = round2(finalPrice - (finalPrice * discountPercent / 100));

            let stockColor = 'text-gray-400';
            if (product.total_stock > 0) {
                const stockPercent = (product.total_stock / product.max_stock) * 100;
                if (product.total_stock > product.max_stock) {
                    stockColor = 'text-green-600';
                } else if (stockPercent < 50) {
                    stockColor = 'text-red-500';
                } else {
                    stockColor = 'text-green-600';
                }
            }

            const stockText = product.track_stock
                ? (product.total_stock > 0
                    ? parseFloat(product.total_stock).toFixed(6).replace(/\.?0+$/, '') + ' ' + (product.unit || '')
                    : 'No stock')
                : '—';

            const priceHtml = discountPercent != 0
                ? `<del data-base-price="${finalPrice.toFixed(2)}" class="pricing text-gray-400 text-xs block">${fmtMoney(finalPrice, factor, currency_name, 'មិនទាន់កំណត់តម្លៃ')}</del>
                   <span data-base-price="${discountedPrice.toFixed(2)}" class="${stockColor} pricing font-semibold text-sm">${fmtMoney(discountedPrice, factor, currency_name, 'មិនមានតម្លៃ')}</span>`
                : `<span data-base-price="${finalPrice.toFixed(2)}" class="pricing font-semibold text-sm">${fmtMoney(finalPrice, factor, currency_name, 'មិនមានតម្លៃ')}</span>`;

            const cellByColumn = {
                price: `<td class="px-3 py-2 text-right whitespace-nowrap" style="max-width:${LIST_COLUMN_DEFS.price.width};">${priceHtml}</td>`,
                stock: `<td class="px-3 py-2 text-center whitespace-nowrap" style="max-width:${LIST_COLUMN_DEFS.stock.width};"><span class="${stockColor}">${stockText}</span></td>`,
                category: `<td class="px-3 py-2 text-gray-500 truncate" style="max-width:${LIST_COLUMN_DEFS.category.width};">${escAttr(product.category_name || '-')}</td>`,
                barcode: `<td class="px-3 py-2 text-gray-500 truncate" style="max-width:${LIST_COLUMN_DEFS.barcode.width};">${escAttr(product.bar_code || '-')}</td>`,
                code: `<td class="px-3 py-2 text-gray-500 truncate" style="max-width:${LIST_COLUMN_DEFS.code.width};">${escAttr(product.code || '-')}</td>`,
                unit: `<td class="px-3 py-2 text-center text-gray-500 truncate" style="max-width:${LIST_COLUMN_DEFS.unit.width};">${escAttr(product.unit || '-')}</td>`,
                description: `<td class="px-3 py-2 text-gray-500 truncate" style="max-width:${LIST_COLUMN_DEFS.description.width};" title="${escAttr(product.description || '')}">${escAttr(product.description || '-')}</td>`,
            };
            const cells = orderedActiveColumns().map((key) => cellByColumn[key] || '').join('');

            return `
                <tr class="add-to-cart-btn border-b border-gray-100 hover:bg-amber-50 cursor-pointer transition-colors" data-product="${escAttr(JSON.stringify(product))}">
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                            <img class="w-10 h-10 rounded object-cover shrink-0" loading="lazy"
                                src="${imageSrc}" onerror="this.onerror=null;this.src='/assets/defult/placeholder.png'" alt="${escAttr(product.name)}">
                            <span class="font-medium text-gray-800">${escAttr(product.name)}</span>
                            ${discountPercent != 0 ? `<span class="inline-flex items-center bg-red-500 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-sm shrink-0"><i class="fa-solid fa-tag mr-0.5"></i>${discountPercent}%</span>` : ''}
                        </div>
                    </td>
                    ${cells}
                </tr>
            `;
        }

        function renderProductListTable(products) {
            const columns = orderedActiveColumns();
            const headerCells = columns.map((key) => {
                const def = LIST_COLUMN_DEFS[key];
                if (!def) return '';
                return `<th class="px-3 py-2.5 whitespace-nowrap ${def.align}" style="width:${def.width};max-width:${def.width};">${def.label}</th>`;
            }).join('');
            const colCount = columns.length + 1;

            return `
                <div class="min_heigh_70 w-full p-3 bg-[#F6F5FF] mb-12 pb-16">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2.5 text-left">{{ __('Product') }}</th>
                                    ${headerCells}
                                </tr>
                            </thead>
                            <tbody>
                                ${products.length ? products.map(renderProductRow).join('') : `<tr><td colspan="${colCount}" class="px-3 py-6 text-center text-gray-400">No products found</td></tr>`}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Columns picker button — only rendered in list mode (see markup),
        // so these listeners are optional-chained in case the button/panel
        // aren't on the page at all (grid mode).
        document.getElementById('listColumnsBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('listColumnsPanel')?.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('listColumnsPanel');
            const btn = document.getElementById('listColumnsBtn');
            if (!panel || panel.classList.contains('hidden')) return;
            if (!panel.contains(e.target) && e.target !== btn && !btn?.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });
        document.getElementById('listColumnsOptions')?.addEventListener('change', (e) => {
            const key = e.target.dataset.column;
            if (!key) return;
            if (e.target.checked) {
                if (!activeListColumns.includes(key)) activeListColumns.push(key);
            } else {
                activeListColumns = activeListColumns.filter((c) => c !== key);
            }
            saveListColumns();

            const query = searchInput_product.value.trim();
            if (query) {
                doSearch();
            } else {
                renderCategoryProducts(current_tab);
            }
        });
        renderListColumnsPanel();
        // Escape a string for safe use inside an HTML attribute (double-quoted)
        function escAttr(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
        let lastClick = 0;

        tabContent.addEventListener('click', e => {
            const btn = e.target.closest('.add-to-cart-btn');
            if (!btn) return;

            const now = Date.now();
            if (now - lastClick < 300) return; // block fast double clicks
            lastClick = now;

            const productJson = btn.dataset.product;

            Livewire.dispatch('add-product', productJson); // ONLY this
        });

        const searchInput_product = document.getElementById('search-dropdown');
        const fieldSelect = document.getElementById('field-select');


        // ── remember search field per user ──
        const FIELD_KEY = 'pos_search_field_{{ Auth::id() }}';

        // restore saved choice on load (fall back to the HTML `selected` = bar_code)
        const savedField = localStorage.getItem(FIELD_KEY);
        if (savedField && [...fieldSelect.options].some(o => o.value === savedField)) {
            fieldSelect.value = savedField;
        }

        // persist whenever it changes
        fieldSelect.addEventListener('change', () => {
            localStorage.setItem(FIELD_KEY, fieldSelect.value);
        });




        let searchTimer = null;

        // reset grid back to the currently active tab
        function resetToActiveTab() {
            const tab = document.querySelector('#category-tabs button.border-brand') ||
                document.querySelector('#category-tabs button');
            if (tab) tab.click();
        }

        searchInput_product.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(doSearch, 250);
        });

        // barcode scanners send Enter at the end → search immediately
        searchInput_product.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimer);
                doSearch();
            }
        });

        async function doSearch(opts = {}) {
            const silent = opts.silent === true;
            const query = searchInput_product.value.trim();
            const field = fieldSelect.value || 'name';

            if (!query) {
                if (!silent) resetToActiveTab();
                return;
            }

            try {
                if (!silent) {
                    tabContent.innerHTML = `
            <div class="min_heigh_70 w-full grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-2 p-3 bg-[#F6F5FF] mb-12 pb-16">
                <div class="col-span-full text-center">Loading...</div>
            </div>
        `;
                }

                const response = await fetch('/products/category/search', {
                    method: 'POST',
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                        Accept: "application/json",
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        field,
                        query
                    })
                });

                if (!response.ok) throw new Error(response.status);

                const products = await response.json();

                // 🔥 BARCODE MODE: exact single match → add to cart directly.
                // Only for a live user search — a background refresh must
                // never trigger this, or a barcode still sitting in the
                // field could get silently re-added to the cart on the next
                // poll tick.
                if (!silent && field === 'bar_code' && products.length === 1) {
                    Livewire.dispatch('add-product', JSON.stringify(products[0]));

                    searchInput_product.value = '';
                    resetToActiveTab();
                    searchInput_product.focus();
                    return;
                }

                tabContent.innerHTML = productViewMode === 'list'
                    ? renderProductListTable(products)
                    : `
            <div class="min_heigh_70 w-full  product-grid p-3  bg-[#F6F5FF] mb-12 pb-16">
                ${
                    products.length
                        ? products.map(p => renderProductCard(p)).join('')
                        : `<div class="col-span-full text-center text-gray-500">No products found</div>`
                }
            </div>
        `;

            } catch (err) {
                console.error(err);
                if (!silent) {
                    tabContent.innerHTML = `
            <div class="col-span-full p-4 text-red-500">Search failed.</div>
        `;
                }
            }
        }


















        function renderProductCard(product) {

            const imageSrc = product.image ?
                `/assets/startic_img/${encodeURIComponent(product.image)}` :
                '/assets/defult/placeholder.png';

            const price = Number(product.sell_price || 0);
            const vatRate = Number(product.vat || 0);
            const finalPrice = price;

            // Discounted price
            const discountPercent = Number(product.discount_percent || 0);
            const discountedPrice = round2(finalPrice - (finalPrice * discountPercent / 100));

            // Stock color logic
            let stockColor = 'text-gray-400'; // default out of stock
            if (product.total_stock > 0) {
                const stockPercent = (product.total_stock / product.max_stock) * 100;

                if (product.total_stock > product.max_stock) {
                    stockColor = 'text-green-600'; // overstock
                } else if (stockPercent < 50) {
                    stockColor = 'text-red-500'; // low stock

                } else {
                    stockColor = 'text-green-600'; // enough stock
                }
            }
            let style_click = `card_style_success`;



            return `
                            <div class="card_style ${style_click} bg-neutral-primary-soft block max-w-sm border border-default shadow-xs relative">
                                <button class="  add-to-cart-btn w-full flex flex-col h-full" data-product='${JSON.stringify(product)}'>
                                    <!-- IMAGE -->
                                    <div class="relative w-full">
                                        <img id="product-image${product.id}" class="object-cover w-full" loading="lazy" style="max-height:150px;min-height:150px;"
                                            src="${imageSrc}" onerror="this.onerror=null;this.src='/assets/defult/placeholder.png'" alt="${product.name}" />

                                        <div class="info-wrap"
                                            data-name="${(product.name || '').replace(/"/g, '&quot;')}"
                                            data-stock="${product.total_stock} ${product.unit || ''}"
                                            data-barcode="${product.bar_code || '-'}"
                                            data-category="${product.category_name || '-'}"
                                            data-desc="${(product.description || '').replace(/"/g, '&quot;')}">
                                            <i class="info fa-solid fa-circle-info text-blue-500 text-sm"></i>
                                        </div>

                                        ${product.discount_percent != 0 ? `...keep your discount badge here...` : ''}
                                    </div>
                                    <!-- TEXT CONTENT -->
                                    <div class="flex flex-col justify-between p-2 mt-2 h-[130px]">
                                        <div class="product-name-card">

                                           ${product.name}
                                        </div>

                                        <div class="text-center mt-1">
                                            <p >
                                               ${product.track_stock ? `
                                                                                                <i class="${stockColor} fa-solid fa-boxes-stacked product-text-card"></i>
                                                                                                <span class="${stockColor} product-text-card">
                                                                                                    ${
                                                                                                        product.total_stock > 0
                                                                                                            ? parseFloat(product.total_stock)
                                                                                                                .toFixed(6)
                                                                                                                .replace(/\.?0+$/, '') + ' ' + product.unit
                                                                                                            : 'No stock'
                                                                                                    }
                                                                                                </span>
                                                                                                &ensp;
                                                                                            ` : ''}

                                                ${product.discount_percent != 0
                                                    ? `<br>
                                                                                                    <del data-base-price="${finalPrice.toFixed(2)}" class="pricing text-gray-400 text-sm product-text-card">
                                                                                                        ${fmtMoney(finalPrice, factor, currency_name, 'មិនទាន់កំណត់តម្លៃ')}
                                                                                                    </del>
                                                                                                    →
                                                                                                    <span data-base-price="${discountedPrice.toFixed(2)}" class="${stockColor} pricing font-semibold text-sm product-text-card">
                                                                                                        ${fmtMoney(discountedPrice, factor, currency_name, 'មិនមានតម្លៃ')}
                                                                                                    </span>`
                                                    : `<span data-base-price="${finalPrice.toFixed(2)}" class="pricing font-semibold text-sm product-text-card">
                                                                                                        ${fmtMoney(finalPrice, factor, currency_name, 'មិនមានតម្លៃ')}
                                                                                                </span>`
}

                                            </p>
                                        </div>
                                    </div>

                                </button>

                            </div>`;
        }



        // KHR → round to nearest 100; shared by card display AND cart
        function fmtMoney(base, factor, currency_name, zeroLabel = '') {
            let value = Number(base) * Number(factor);
            if (!value) return zeroLabel;

            const f = Number(factor);
            let roundTo = 1,
                decimal;
            if (f === 1) {
                decimal = 3;
            } else if (f >= 4000) {
                decimal = 0;
                roundTo = 100;
            } // 🔥 drop last 2 digits
            else if (f >= 100) {
                decimal = 3;
            } else {
                decimal = 2;
            }

            if (roundTo > 1) value = Math.round(value / roundTo) * roundTo;

            return value.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: decimal,
            }) + ' ' + currency_name;
        }






        function initAddToCartButtons() {

            document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
                btn.removeEventListener('click', btn
                    ._addToCartListener); // remove old listener if exists
                btn._addToCartListener = () => {
                    const productJson = btn.dataset.product; // keep JSON string
                    Livewire.dispatch('add-product', productJson);
                };
                btn.addEventListener('click', btn._addToCartListener);
            });
        }

        window.addEventListener('stock-alert', event => {
            alert(event.detail.message);
        });



        function normalizePrice(value) {
            const num = Number(value);

            // Count decimal digits safely
            const decimalPart = num.toString().split('.')[1] || '';

            if (decimalPart.length > 3) {
                return Number(num.toFixed(3));
            }

            return num;
        }






        const input = document.getElementById("customerSearch");
        const list = document.getElementById("customerList");
        const hiddenInput = document.getElementById("customerValue");
        let current_discount = 0;
        input.addEventListener("input", async () => {
            const value = input.value.trim();

            if (value.length === 0) {
                list.classList.add("hidden");
                return;
            }

            try {
                const res = await fetch(
                    `{{ route('customers.search') }}?q=${encodeURIComponent(value)}`);
                const data = await res.json();

                // Clear previous list
                list.innerHTML = '';

                if (data.length === 0) {
                    list.innerHTML =
                        '<li class="px-3 py-2 text-sm text-gray-500">No results found</li>';
                } else {
                    data.forEach(customer => {

                        const li = document.createElement('li');
                        li.textContent = parseFloat(customer.discount_percent || 0) > 0 ?
                            `${customer.customer_code} - ${customer.name} (${parseFloat(customer.discount_percent)}%)` :
                            `${customer.customer_code} - ${customer.name}`;
                        li.dataset.value = customer.customer_code;
                        li.className = 'px-3 py-2 cursor-pointer hover:bg-gray-100 text-sm';
                        li.addEventListener('click', () => {

                            input.value = li.textContent;

                            const customerDiscount = parseFloat(customer
                                .discount_percent || 0);

                            hiddenInput.value = customer.customer_code;
                            list.classList.add('hidden');

                            hiddenInput.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));

                            // ✅ Fill Customer Info
                            document.getElementById('so_customer_name_info').value =
                                customer.name ?? '';
                            document.getElementById('so_customer_id_info').value =
                                customer.id ?? '';
                            document.getElementById('so_customer_address_info')
                                .value =
                                customer.address1 ?? '';

                            document.getElementById('so_customer_phone_info')
                                .value =
                                customer.phone ?? '';


                            // Discount Logic
                            if (current_discount !== customerDiscount) {

                                let title = '';
                                let message = '';

                                if (customerDiscount > 0) {
                                    title = 'Apply customer discount?';
                                    message =
                                        `Customer has ${customerDiscount}% discount. Do you want to overwrite current cart discount?`;
                                } else {
                                    title = 'Remove customer discount?';
                                    message =
                                        `This customer has no discount. Do you want to remove current discount (${current_discount}%) and keep normal price?`;
                                }

                                openCustomerDiscountModal({
                                    title,
                                    message,
                                    onConfirm: () => {
                                        current_discount =
                                            customerDiscount;

                                        Livewire.dispatch(
                                            'applyCustomerDiscountEvent', {
                                                discount: customerDiscount
                                            });
                                    }
                                });
                            }
                        });
                        list.appendChild(li);
                    });
                }

                list.classList.remove("hidden");
            } catch (err) {
                console.error(err);
            }
        });
        const customerDiscountModal = document.getElementById('customerDiscountModal');

        if (customerDiscountModal) {
            customerDiscountModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');

                    document.getElementById('customerDiscountModalConfirm').onclick = null;
                    document.getElementById('customerDiscountModalCancel').onclick = null;
                }
            });
        }

        function openCustomerDiscountModal({
            title,
            message,
            onConfirm
        }) {
            const modal = document.getElementById('customerDiscountModal');
            const titleEl = document.getElementById('customerDiscountModalTitle');
            const messageEl = document.getElementById('customerDiscountModalMessage');
            const confirmBtn = document.getElementById('customerDiscountModalConfirm');
            const cancelBtn = document.getElementById('customerDiscountModalCancel');

            titleEl.textContent = title;
            messageEl.textContent = message;

            modal.classList.remove('hidden');

            const closeModal = () => {
                modal.classList.add('hidden');
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
            };

            confirmBtn.onclick = () => {
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
                closeModal();
            };

            cancelBtn.onclick = () => {
                closeModal();
            };
        }


        // Hide list when clicking outside
        document.addEventListener("click", (e) => {
            if (!e.target.closest(".relative")) {
                list.classList.add("hidden");
            }
        });


        function setPage(page) {
            // send event to Livewire

            Livewire.dispatch('pageSelected', {
                page: page
            });

        }



        function getTotalStock(product) {
            if (!Array.isArray(product.warehouses)) return 0;

            return product.warehouses.reduce(
                (sum, wh) => sum + (Number(wh.stock_qty) || 0),
                0
            );
        }
    </script>
@endsection







@push('modals')
        {{-- <ADD CURRENCY> --}}
        {{-- Always rendered (even if the user lacks exchange_rate.view — the drawer trigger in
             master.blade.php is likewise always rendered, just visually hidden via a CSS class,
             so Flowbite always finds a matching trigger+target pair and never logs a "modal has
             not been initialized" warning). Real enforcement is the exchange_rate.* route middleware. --}}
        <div id="static-modal-currency-exchange" data-modal-backdrop="static" tabindex="-1" aria-hidden="true"
            class="modal-overlay-sale overflow-y-auto hidden">
            <div class="relative w-full max-w-2xl">
                <!-- Modal content -->
                <div class="modal-card-sale">
                    <!-- Modal header -->
                    <div class="modal-header-sale">
                        <h3 class="text-lg font-bold text-white">
                            Currency Exchange
                        </h3>
                        <button type="button"
                            class="modal-close-btn"
                            data-modal-hide="static-modal-currency-exchange">
                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18 17.94 6M18 18 6.06 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>
                    <!-- Modal body -->
                    <form id="currencyForm" class="p-4 md:p-6">
                        @csrf
                        <div id="main_currency_box" class="grid grid-cols-1 space-y-4 md:space-y-6 py-4 md:py-6">


                            @foreach ($currency as $item)
                                <div
                                    class=" space-x-0 space-y-4 sm:space-y-0 sm:space-x-4 rtl:space-x-reverse flex items-center flex-col sm:flex-row mb-4">
                                    <input type="hidden" name="currency[{{ $item->id }}][id]"
                                        value="{{ $item->id }}">

                                    <div class="flex -space-x-px">

                                        <div class="relative w-full">
                                            <input type="number" value="1" disabled
                                                class="block w-full bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-s-base focus:ring-brand focus:border-brand px-3 py-2.5 placeholder:text-body"
                                                placeholder="1 USD" required />
                                        </div>
                                        <button
                                            class="inline-flex items-center shrink-0 z-10 text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-fg-brand focus:ring-4 focus:ring-neutral-tertiary font-medium leading-5 rounded-e-base text-sm px-4 py-2.5 focus:outline-none"
                                            type="button">
                                            USD &ensp;
                                        </button>
                                    </div>
                                    <svg class="mx-2 w-4 h-4 text-body" aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                        viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m16 10 3-3m0 0-3-3m3 3H5v3m3 4-3 3m0 0 3 3m-3-3h14v-3" />
                                    </svg>
                                    <div class="flex -space-x-px">

                                        <div class="relative w-full">


                                            <input type="number" name="currency[{{ $item->id }}][factor]"
                                                value="{{ (float) $item->factor }}"
                                                class="block w-full bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-l-sm focus:ring-brand focus:border-brand px-3 py-2.5 placeholder:text-body">
                                        </div>
                                        <div>
                                            <input type="text" name="currency[{{ $item->id }}][name]" readonly
                                                class="block w-full bg-gray-100 border border-default-medium text-body text-sm cursor-not-allowed px-3 py-2.5 placeholder:text-body"
                                                value="{{ $item->name }}">
                                        </div>
                                        <div>



                                            <input type="text" name="currency[{{ $item->id }}][code]" readonly
                                                value="{{ $item->code }}"
                                                class="block w-full bg-gray-100 border border-default-medium text-body text-sm rounded-e-sm cursor-not-allowed px-3 py-2.5 placeholder:text-body">
                                        </div>

                                    </div>

                                </div>
                                <br>
                            @endforeach


                        </div>


                    </form>
                    <!-- Modal footer -->
                    <div class="flex items-center border-t border-slate-200 space-x-4 pt-4 md:pt-5">
                        <button onclick="saveCurrencies()" {{-- data-modal-hide="static-modal-currency-exchange" --}} type="button"
                            class="text-white bg-slate-900 hover:bg-slate-800 font-medium rounded-xl text-sm px-4 py-2.5 transition focus:outline-none">
                            Save</button>
                        &ensp;
                        <button data-modal-hide="static-modal-currency-exchange" type="button"
                            class="text-gray-700 bg-gray-200 hover:bg-gray-300 font-medium rounded-xl text-sm px-4 py-2.5 transition focus:outline-none mx-2">Close</button>
                    </div>
                </div>
            </div>
        </div>


    {{-- <UPDATE CUSTOMER> --}}
    <div id="confirm-update-cust"
        class="hight_index modal-overlay-sale hidden">

        <div class="w-full max-w-3xl max-h-[92vh] flex flex-col modal-card-sale">

            <!-- Header -->
            <div class="modal-header-sale shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-white">Update Customer</h2>
                    <p class="text-sm text-slate-300">Edit customer information below</p>
                </div>

                <button type="button" onclick="closeUpdateCustModal()"
                    class="modal-close-btn">
                    ✕
                </button>
            </div>

            <!-- Body -->
            <form id="updateCustomerForm" class="p-6 overflow-y-auto flex-1 min-h-0">
                @csrf
                <input type="hidden" id="cust-id" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Name</label>
                        <input id="cust-name" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Phone</label>
                        <input id="cust-phone" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Email</label>
                        <input id="cust-email" type="email"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Address 1</label>
                        <input id="cust-address1" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Address 2</label>
                        <input id="cust-address2" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">City</label>
                        <input id="cust-city" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Country</label>
                        <input id="cust-country" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Type</label>
                        <select id="cust-type"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                            <option value="walk_in">Walk-in</option>
                            <option value="member">Member</option>
                            <option value="vip">VIP</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Discount (%)</label>
                        <input id="cust-discount_percent" type="number" step="0.01"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Points</label>
                        <input id="cust-point" type="number"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Contact</label>
                        <input id="cust-contact" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Contact Phone</label>
                        <input id="cust-contact_phone" type="text"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600">Status</label>
                        <select id="cust-status"
                            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                </div>
            </form>

            <!-- Footer -->
            <div class="flex justify-end gap-3 border-t bg-gray-50 px-6 py-4 shrink-0">
                <button type="button" onclick="closeUpdateCustModal()"
                    class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-gray-700 hover:bg-gray-100 transition">
                    Cancel
                </button>

                <button type="button" onclick="confirmUpdateCustomer()"
                    class="rounded-xl bg-sky-500 px-6 py-2.5 font-semibold text-white shadow-md hover:bg-sky-600 transition">
                    Update Customer
                </button>
            </div>

        </div>
    </div>

    {{-- <CONFIRM > --}}
    <div id="confirmModal"
        class="modal-overlay-alert z-[100] hidden">
        <div class="modal-card-alert text-center animate-scaleUp">
            <h2 id="confirmModalTitle" class="text-2xl font-bold mb-3 text-gray-800">Are you sure?</h2>
            <p id="confirmModalMessage" class="text-gray-600 mb-6">This action cannot be undone.</p>
            <div class="flex justify-center space-x-4">
                <button data-modal-close
                    class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">Cancel</button>
                <button id="confirmModalAction"
                    class="px-5 py-2 bg-red-500 text-white rounded-xl hover:bg-red-600 transition">Confirm</button>
            </div>
        </div>
    </div>


    {{-- <PRINT CONFIRM> --}}
    <div id="printConfirmModal"
        class="modal-overlay-alert z-[100] hidden">
        <div class="modal-card-alert text-center animate-scaleUp">
            <div
                class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                <i class="fa-solid fa-print text-2xl"></i>
            </div>
            <h2 id="printConfirmTitle" class="text-2xl font-bold mb-3 text-gray-800">Print now?</h2>
            <p id="printConfirmMessage" class="text-gray-600 mb-6">Print this document now?</p>
            <div class="flex justify-center space-x-4">
                <button id="printConfirmSkip"
                    class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                    Skip
                </button>
                <button id="printConfirmYes"
                    class="px-5 py-2 bg-sky-600 text-white rounded-xl hover:bg-sky-700 transition inline-flex items-center gap-2">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    {{-- <PRINT OPTIONS — pick which forms + how many copies of each> --}}
    <div id="printOptionsModal"
        class="modal-overlay-alert z-[100] hidden">
        <div class="modal-card-alert max-w-md animate-scaleUp">
            <div
                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                <i class="fa-solid fa-print text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold mb-1 text-gray-800 text-center">Print Documents</h2>
            <p class="text-gray-500 mb-4 text-center text-sm">
                Choose which forms to print, and how many copies of each.
            </p>

            <div class="space-y-3 mb-5 text-left">
                <div class="rounded-xl border border-gray-200 px-3 py-2.5">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" id="po_receipt_checked"
                                class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-400">
                            Receipt
                        </label>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-400">Qty</span>
                            <input type="number" id="po_receipt_qty" min="1" value="1"
                                class="w-14 rounded-lg border border-gray-300 px-2 py-1 text-sm text-center">
                        </div>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2 pl-6">
                        <span class="min-w-0 flex-1 truncate text-[11px] text-gray-400">
                            Printer: <span id="po_receipt_printer_name" class="text-gray-600">-</span>
                        </span>
                        <button type="button" id="po_receipt_change_printer"
                            class="shrink-0 text-[11px] font-semibold text-sky-600 hover:text-sky-700">
                            Change Printer
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 px-3 py-2.5">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" id="po_invoice_checked"
                                class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-400">
                            Invoice (A4)
                        </label>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-400">Qty</span>
                            <input type="number" id="po_invoice_qty" min="1" value="1"
                                class="w-14 rounded-lg border border-gray-300 px-2 py-1 text-sm text-center">
                        </div>
                    </div>
                    <div class="mt-1.5 pl-6">
                        <span class="text-[11px] text-gray-400">Opens the browser's print dialog — pick printer, adjust margins, etc. there.</span>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 px-3 py-2.5">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" id="po_delivery_checked"
                                class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-400">
                            Delivery Note
                        </label>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-400">Qty</span>
                            <input type="number" id="po_delivery_qty" min="1" value="1"
                                class="w-14 rounded-lg border border-gray-300 px-2 py-1 text-sm text-center">
                        </div>
                    </div>
                    <div class="mt-1.5 pl-6">
                        <span class="text-[11px] text-gray-400">Opens the browser's print dialog — pick printer, adjust margins, etc. there.</span>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 px-3 py-2.5">
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" id="po_picking_list_checked"
                            class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-400">
                        Picking List
                    </label>
                    <p class="mt-1 pl-6 text-[11px] text-gray-400">
                        Shows which bin to pull each item from — opens the browser's print dialog.
                    </p>
                </div>

                <p class="text-[11px] text-gray-400 px-1">
                    Receipt prints silently to your saved thermal printer; Invoice, Delivery Note, and Picking List
                    each open the browser's print dialog so you can pick a printer and adjust margins yourself.
                </p>
            </div>

            <div class="flex justify-center space-x-4">
                <button id="printOptionsSkip"
                    class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                    Skip
                </button>
                <button id="printOptionsConfirm"
                    class="px-5 py-2 bg-sky-600 text-white rounded-xl hover:bg-sky-700 transition inline-flex items-center gap-2">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    {{-- <CUSTOMER DISCOUNT CONFIRM> --}}
    <div id="customerDiscountModal"
        class="modal-overlay-alert hidden">
        <div class="modal-card-alert text-center animate-scaleUp">
            <h2 id="customerDiscountModalTitle" class="text-2xl font-bold mb-3 text-gray-800">
                Apply customer discount?
            </h2>
            <p id="customerDiscountModalMessage" class="text-gray-600 mb-6">
                Customer discount will overwrite current cart discount.
            </p>
            <div class="flex justify-center space-x-4">
                <button id="customerDiscountModalCancel"
                    class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                    Cancel
                </button>
                <button id="customerDiscountModalConfirm"
                    class="px-5 py-2 bg-green-500 text-white rounded-xl hover:bg-green-600 transition">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    {{-- <REFRESH> --}}

    <div id="unsaveModal" class="modal-overlay-alert hidden">
        <div class="modal-card-alert text-center animate-scaleUp">
            <h2 class="text-2xl font-bold mb-3 text-gray-800">Resfresh Page.</h2>
            <p class="text-gray-600 mb-6">Warning: Unsaved work might be lost. Do you want to continue?</p>
            <div class="flex justify-center space-x-4">
                <button data-modal-close
                    class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">Cancel</button>
                &ensp;
                <button data-modal-action
                    class="px-5 py-2 bg-sky-500 text-white rounded-xl hover:bg-sky-600 transition">Continue</button>
            </div>
        </div>
    </div>


    {{-- <LIST CUSTOMER> --}}
    <div id="default-modal-customer-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale items-start md:items-center !p-1 hidden">

        {{-- width Custom  --}}
        <div class="relative w-full max-w-[98vw]">
            <!-- Modal content -->
            <div class="h-[98vh] min-h-[98vh] max-h-[98vh] modal-card-sale">

                <!-- Modal header -->
                <div class="modal-header-sale flex-col items-stretch gap-2 !py-2">
                    <div class="flex w-full items-center justify-between">
                        <h3 class="text-sm font-bold text-white">
                            Customer Information
                        </h3>
                        <button type="button"
                            class="modal-close-btn shrink-0"
                            data-modal-hide="default-modal-customer-list">
                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18 17.94 6M18 18 6.06 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Active checkbox -->
                        <div class="flex items-center gap-2">
                            <label for="customerSearchCheckbox" class="text-sm font-semibold text-slate-200">
                                Active
                            </label>
                            <input type="checkbox" checked id="customerSearchCheckbox"
                                class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-400">
                        </div>

                        <!-- Type select -->
                        <div class="flex items-center gap-2">
                            <input type="text" id="customerSearchInput"
                                placeholder="Search by code, name, phone, email..."
                                class="px-3 py-1.5 border border-gray-300 text-sm w-64
                                   focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">

                            <select id="customerTypeSelect"
                                class="px-3 py-1.5 border border-gray-300 text-sm w-44 bg-white
                                   focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                                <option value="">All Types</option>
                                <option value="walk_in">Walk In</option>
                                <option value="member">Member</option>
                                <option value="vip">VIP</option>
                            </select>

                            {{-- Hidden from cashiers: they are already restricted to their
                                 own customers server-side, so the dropdown would do
                                 nothing. Mirrors the Seller filter on the Sale Order list. --}}
                            @if (Auth::user()->role != 'cashier')
                                <select id="customerUserSelect" onchange="loadCustomers(1)"
                                    class="px-3 py-1.5 border border-gray-300 text-sm w-44 bg-white
                                       focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                                    <option value="">{{ __('All Sellers') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->username }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Modal body -->
                <div class="flex-1 overflow-y-auto min-h-0 p-4 md:p-6">
                    <div class="scroll_content_70 overflow-x-auto rounded-2xl border border-sky-100">

                        <table id="customer-list" class="w-full text-sm text-left">
                            <thead class="bg-sky-50 text-sky-700">
                                <tr>
                                    <th class="px-3 py-2">{{ __('Select') }}</th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="id">
                                        No. <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="customer_code">
                                        Code <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="name">
                                        Name <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="address1">
                                        Address <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="phone">
                                        Phone <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="email">
                                        Email <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="type">
                                        Type <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="credit_limit">
                                        Discount % <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="point">
                                        Points <span class="sort-icon">↕</span>
                                    </th>

                                    <th class="px-3 py-2 cursor-pointer" data-column="status">
                                        Status <span class="sort-icon">↕</span>
                                    </th>
                                </tr>
                            </thead>

                            <tbody id="customer-table-body" class="divide-y divide-sky-50">
                                <!-- async rows -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal footer -->
                <div
                    class="flex items-center justify-between border-t border-sky-100 space-x-4 pt-4 md:pt-5 mt-4 bg-gray-50/60">
                    <div>
                        <button type="button" id="btnEditCustomer"
                            class="text-white bg-sky-500 hover:bg-sky-600 focus:ring-4 focus:ring-sky-200
                               shadow-md font-semibold rounded-xl text-sm px-4 py-2.5 transition">
                            Edit
                        </button>
                        &ensp;

                        {{-- <button type="button" id="btnDeleteCustomer"
                        class="text-white bg-sky-500 hover:bg-sky-600 focus:ring-4 focus:ring-sky-200
                               shadow-md font-semibold rounded-xl text-sm px-4 py-2.5 transition">
                        Delete
                    </button> --}}

                        <button type="button" data-modal-target="default-modal-customer"
                            data-modal-toggle="default-modal-customer"
                            class="text-white bg-sky-500 hover:bg-sky-600 focus:ring-4 focus:ring-sky-200
                               shadow-md font-semibold rounded-xl text-sm px-4 py-2.5 transition">
                            New
                        </button>

                        <button type="button" id="btnPrintCustomer"
                            class="text-white bg-sky-500 hover:bg-sky-600 focus:ring-4 focus:ring-sky-200
                               shadow-md font-semibold rounded-xl text-sm px-4 py-2.5 transition">
                            Print
                        </button>
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        <div class="flex items-center justify-center gap-1 mt-4 mx-2" id="paginationContainer">
                            <!-- JS will render buttons here -->
                        </div>
                        &ensp;
                        <span id="pageInfo" class="text-sm text-gray-600"></span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- <ADD CUSTOMER> --}}
    <div id="default-modal-customer" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale hidden">

        <div class="w-full max-w-3xl max-h-[92vh] modal-card-sale">

            <form id="AddcustomerForm" class="flex flex-col min-h-0 flex-1">
                @csrf

                <!-- Header -->
                <div class="modal-header-sale shrink-0">
                    <div>
                        <h2 class="text-xl font-bold text-white">Customer Information</h2>
                        <p class="text-sm text-slate-300">Create new customer below</p>
                    </div>

                    <button type="button" data-modal-hide="default-modal-customer"
                        class="modal-close-btn">
                        ✕
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 overflow-y-auto flex-1 min-h-0">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                        <div>
                            <label class="text-sm font-semibold text-gray-600">
                                Customer Name <span class="text-rose-600">*</span>
                            </label>
                            <input type="text" name="name" placeholder="" required
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Phone</label>
                            <input type="tel" name="phone" placeholder=""
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Email</label>
                            <input type="email" name="email" placeholder=""
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Customer Type</label>
                            <select name="type"
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                                <option value="walk_in">Walk-in</option>
                                <option value="member">Member</option>
                                <option value="vip">VIP</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Discount (%)</label>
                            <input type="number" name="discount_percent" id="discount_percent" step="0.01"
                                value="0"
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Address</label>
                            <input type="text" name="address1" placeholder=""
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Address 2</label>
                            <input type="text" name="address2" placeholder=""
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Contact Name</label>
                            <input type="text" name="contact_name" placeholder="Contact Name"
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Contact Phone</label>
                            <input type="text" name="contact_phone" placeholder="Contact Phone"
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">City</label>
                            <input type="text" name="city" placeholder="City"
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-600">Country</label>
                            <input type="text" name="country" placeholder="Country"
                                class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none">
                        </div>

                        <div class="md:col-span-2 flex items-center pt-2">
                            <input type="checkbox" name="status" checked
                                class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-400">
                            &ensp;
                            <label class="text-sm font-medium text-gray-700">Active Customer</label>
                        </div>

                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 border-t bg-gray-50 px-6 py-4 shrink-0">
                    <button type="button" data-modal-hide="default-modal-customer"
                        class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </button>

                    <button type="submit"
                        class="rounded-xl bg-sky-500 px-6 py-2.5 font-semibold text-white shadow-md hover:bg-sky-600 transition">
                        Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- <LIST Warehouse > --}}
    <div id="default-modal-warehouse" data-modal-backdrop="static" tabindex="-1" aria-hidden="true"
        class="modal-overlay-sale !p-1 hidden">

        <div class="relative w-full max-w-[98vw] mx-auto h-[98vh] flex items-center justify-center">

            <div class="modal-card-sale h-full">

                <!-- Header -->
                <div class="modal-header-sale !py-2">
                    <h3 id="wh_name" class="text-sm font-bold text-white flex items-center gap-2 whitespace-nowrap">
                        គ្រប់គ្រង ស្តុក
                        <span class="font-normal text-sky-50">· Warehouse stock management</span>
                    </h3>

                    <div class="flex items-center gap-2 shrink-0">
                        <select id="warehouseTypeSelect"
                            class="px-3 py-1.5 border border-white/30 bg-white/95 text-sm text-gray-700 shadow-sm
                               focus:outline-none focus:ring-2 focus:ring-white/50">
                            <option value="All">All Warehouse</option>
                        </select>

                        <button type="button" onclick="openManageBinsModal()"
                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-sm font-semibold shadow-sm transition">
                            <i class="fa-solid fa-layer-group"></i>
                            Manage Bins
                        </button>

                        @if (Auth::user()->hasPermission('warehouse.view'))
                            <button type="button" onclick="openWarehouseListModal()"
                                class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-sm font-semibold shadow-sm transition">
                                <i class="fa-solid fa-warehouse"></i>
                                Manage Warehouses
                            </button>
                        @endif

                        <button type="button"
                            class="modal-close-btn"
                            data-modal-hide="default-modal-warehouse">
                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="border-b border-sky-100 bg-gray-50 px-3 py-2">
                    <div class="flex flex-wrap items-center gap-2">

                        <div class="relative">
                            <i
                                class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-sky-400"></i>
                            <input type="text" id="search-stock" placeholder="Search product"
                                class="pl-10 pr-3 py-1.5 border border-gray-300 text-sm w-64 bg-white shadow-sm
                                   focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        </div>

                        <select id="category-filter2"
                            class="px-3 py-1.5 border border-gray-300 text-sm w-44 bg-white shadow-sm
                               focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        </select>

                        <select id="limit-filter"
                            class="px-3 py-1.5 border border-gray-300 text-sm w-36 bg-white shadow-sm
                               focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                            <option value="All">All</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="30">30</option>
                            <option selected value="50">50</option>
                            <option value="100">100</option>
                        </select>

                        <select id="status-filter"
                            class="px-3 py-1.5 border border-gray-300 text-sm w-40 bg-white shadow-sm
                               focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                            <option value="All">All Status</option>
                            <option value="0">Inactive</option>
                            <option selected value="1">Active</option>
                        </select>

                        <select id="stock-filter"
                            class="px-3 py-1.5 border border-gray-300 text-sm w-44 bg-white shadow-sm
                               focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                            <option value="All">All</option>
                            <option selected value="has">Has Stock</option>
                            <option value="no">Out of Stock</option>
                        </select>

                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 min-h-0 flex flex-col p-1 bg-gray-50">
                    <div class="rounded-2xl bg-white border border-sky-100 shadow-sm overflow-hidden flex-1 min-h-0 flex flex-col">
                        <div class="scroll_content_70 overflow-auto flex-1 min-h-0">
                            <table id="wh-product" class="w-full text-left text-sm table-auto">
                                <thead class="bg-sky-50 text-sky-700 sticky top-0 z-10">
                                    <tr class="text-nowrap">

                                        <th class="px-3 py-3 font-bold">{{ __('No.') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Product Code') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Product Name') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Category') }}</th>
                                        <th class="px-3 py-3 font-bold text-right">{{ __('Total Quantity') }}</th>
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Warehouses') }}</th>
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Lots') }}</th>
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Status') }}</th>
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Actions') }}</th>

                                    </tr>
                                </thead>

                                <tbody id="warehouse-stock-tbody" class="divide-y divide-sky-50 text-gray-700">
                                    <!-- Dynamic rows inserted here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex flex-wrap items-center justify-between border-t border-sky-100 px-6 py-4 bg-white">
                      @if (Auth::user()->hasPermission('warehouse.adjustment'))
                            <button type="button" id="btnStockAdjustment"
                                class="px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-500 text-amber-950 text-sm font-bold shadow-sm inline-flex items-center gap-2 transition"
                                onclick="Livewire.dispatch('open-stock-adjustment')">
                                <i class="fa-solid fa-sliders"></i>
                                Adjustment Stock
                            </button>
                        @else
                            <span></span>
                        @endif
                    <div class="flex items-center gap-2" id="paginationContainer_stock">
                        <!-- JS will render buttons here -->
                    </div>

                    <span id="pageInfo_stock" class="text-sm text-gray-500"></span>
                </div>

            </div>
        </div>
    </div>

    {{-- <Manage Warehouses (list) - Create/Update/Delete/Disable> --}}
    <div id="default-modal-warehouse-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale !p-1 hidden">

        <div class="relative w-full max-w-3xl mx-auto max-h-[90vh] flex items-center justify-center">

            <div class="modal-card-sale max-h-[90vh]">

                <!-- Header -->
                <div class="modal-header-sale">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-warehouse"></i>
                        Manage Warehouses
                    </h3>
                    <div class="flex items-center gap-2">
                        @if (Auth::user()->hasPermission('warehouse.create'))
                            <button type="button" onclick="openWarehouseFormModal()"
                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold transition">
                                <i class="fa-solid fa-plus"></i>
                                New Warehouse
                            </button>
                        @endif
                        <button type="button" class="modal-close-btn" onclick="closeWarehouseListModal()">
                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 min-h-0 flex flex-col p-1 bg-gray-50">
                    <div
                        class="rounded-2xl bg-white border border-sky-100 shadow-sm overflow-hidden flex-1 min-h-0 flex flex-col">
                        <div class="overflow-auto flex-1 min-h-0">
                            <table id="warehouse-crud-table" class="w-full text-sm text-left">
                                <thead class="bg-sky-50 text-sky-700 sticky top-0 z-10">
                                    <tr class="text-nowrap">
                                        <th class="px-3 py-3 font-bold">{{ __('Name') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Location') }}</th>
                                        @if (Auth::user()->role == 'admin')
                                            <th class="px-3 py-3 font-bold">{{ __('Note') }}</th>
                                        @endif
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Status') }}</th>
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="warehouse-crud-tbody" class="divide-y divide-sky-50 text-gray-700">
                                    <!-- async rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- <Manage Warehouses - Add / Edit form (stacked on top of the list)> --}}
    <div id="default-modal-warehouse-form" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale-stacked !p-1 hidden">

        <div class="relative w-full max-w-md mx-auto flex items-center justify-center">

            <div class="modal-card-sale">

                <!-- Header -->
                <div class="modal-header-sale">
                    <h3 id="warehouseFormTitle" class="text-lg font-bold text-white">New Warehouse</h3>
                    <button type="button" class="modal-close-btn" onclick="closeWarehouseFormModal()">
                        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-4">
                    <input type="hidden" id="warehouse_form_id" value="">

                    <div>
                        <label class="text-sm font-semibold text-gray-600 mb-1 block">Name</label>
                        <input type="text" id="warehouse_form_name" placeholder="Warehouse name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-600 mb-1 block">Location</label>
                        <input type="text" id="warehouse_form_location" placeholder="Location"
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    </div>

                    @if (Auth::user()->role == 'admin')
                        <div>
                            <label class="text-sm font-semibold text-gray-600 mb-1 block">
                                Note <span class="text-xs text-amber-600 font-normal">(admin only)</span>
                            </label>
                            <textarea id="warehouse_form_note" rows="3" placeholder="Internal note..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-100"></textarea>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-2 border-t border-sky-100 px-5 py-3">
                    <button type="button" onclick="closeWarehouseFormModal()"
                        class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-semibold">
                        Cancel
                    </button>
                    <button type="button" id="warehouseFormSaveBtn" onclick="saveWarehouseForm()"
                        class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold">
                        Save
                    </button>
                </div>

            </div>
        </div>
    </div>


    @if (Auth::user()->hasPermission('warehouse.adjustment'))
        <div id="stock-adjustment-modal" tabindex="-1" aria-hidden="true"
            class="modal-overlay-sale-stacked !p-1 hidden">
            <div class="relative w-full max-w-[98vw] mx-auto h-[98vh] flex items-center justify-center">
                <div class="modal-card-sale h-full">

                    <!-- Header -->
                    <div class="modal-header-sale">
                            <div class="flex items-center gap-3">
                                <div class="modal-icon-badge-sale">
                                    <i class="fa-solid fa-sliders text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white leading-tight">Stock Adjustment</h3>
                                    <p class="text-[11px] text-slate-300">
                                        Document <span class="font-semibold">ADJ (auto)</span> · same cost &amp; supplier
                                        kept · lot / expire editable
                                    </p>
                                </div>
                            </div>
                            <button type="button" onclick="closeStockAdjustment()"
                                class="modal-close-btn">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 min-h-0 flex flex-col lg:flex-row">

                        <!-- LEFT: find items -->
                        <div class="lg:w-[42%] flex flex-col border-r border-gray-100 min-h-0">
                            <div class="p-4 border-b border-gray-100 bg-gray-50 space-y-3">
                                <div class="relative">
                                    <i
                                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-amber-400"></i>
                                    <input type="text" id="adj-search-term"
                                        placeholder="Search name, code, lot, description…"
                                        class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl text-sm bg-white
                                       focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                                </div>
                                <div class="flex gap-3">
                                    <select id="adj-search-wh"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white
                                       focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"></select>
                                    <select id="adj-search-cat"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white
                                       focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"></select>
                                </div>
                            </div>
                            <div id="adj-results" class="flex-1 overflow-y-auto min-h-0 bg-white"></div>
                        </div>

                        <!-- RIGHT: adjustment lines -->
                        <div class="flex-1 flex flex-col min-h-0">
                            <div class="p-3 border-b border-gray-100 bg-gray-50 flex flex-wrap items-end gap-3">
                                <div class="flex flex-col">
                                    <label class="text-[11px] font-semibold text-gray-500 mb-1">Date</label>
                                    <input type="date" id="adj-date" min="2000-01-01" max="2060-12-31"
                                        class="px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white">
                                </div>
                                <div class="flex flex-col flex-1 min-w-[200px]">
                                    <label class="text-[11px] font-semibold text-gray-500 mb-1">Reason / Remark</label>
                                    <input type="text" id="adj-remark"
                                        placeholder="e.g. Physical count, damage, correction"
                                        class="px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white w-full">
                                </div>
                            </div>

                            <div class="flex-1 overflow-auto min-h-0">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-amber-50 text-amber-800 sticky top-0 z-10">
                                        <tr class="text-nowrap">
                                            <th class="px-3 py-2 font-bold">{{ __('Product') }}</th>
                                            <th class="px-2 py-2 font-bold">{{ __('Warehouse') }}</th>
                                            <th class="px-2 py-2 font-bold">{{ __('Bin') }}</th>
                                            <th class="px-2 py-2 font-bold">{{ __('Lot') }}</th>
                                            <th class="px-2 py-2 font-bold">{{ __('Expire') }}</th>
                                            <th class="px-2 py-2 font-bold text-center">+/−</th>
                                            <th class="px-2 py-2 font-bold text-right">{{ __('Qty') }}</th>
                                            <th class="px-2 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="adj-lines" class="divide-y divide-gray-100 text-gray-700 bg-white"></tbody>
                                </table>
                            </div>

                            <div class="border-t border-gray-100 px-4 py-3 bg-white flex items-center justify-between">
                                <span class="text-sm text-gray-500"><span id="adj-line-count"
                                        class="font-bold text-gray-700">0</span> line(s)</span>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="closeStockAdjustment()"
                                        class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-100">Cancel</button>
                                    <button type="button" id="adj-confirm" disabled
                                        class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-bold inline-flex items-center gap-2">
                                        <i class="fa-solid fa-check"></i> Confirm Adjustment
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- <Manage Bins> --}}
    <div id="manageBinsModal"
        class="modal-overlay-sale-stacked hidden">
        <div class="w-full max-w-lg max-h-[85vh] modal-card-sale">

            {{-- Header --}}
            <div class="modal-header-sale">
                <div class="flex items-center gap-3">
                    <div class="modal-icon-badge-sale">
                        <i class="fa-solid fa-layer-group text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Manage Bins</h2>
                        <p class="text-sm text-slate-300">Bins belong to one warehouse each</p>
                    </div>
                </div>
                <button type="button" onclick="closeManageBinsModal()"
                    class="modal-close-btn">
                    &times;
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto min-h-0 bg-slate-50 p-6 space-y-5">

                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase">Warehouse</label>
                    <select id="manage-bins-warehouse"
                        class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                           focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition"></select>
                </div>

                @if (Auth::user()->hasPermission('warehouse.create'))
                    <div class="flex gap-2">
                        <input type="text" id="new-bin-name" placeholder="New bin name, e.g. A1"
                            class="flex-1 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                        <button type="button" onclick="addBin()"
                            class="inline-flex items-center gap-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 text-sm font-semibold shadow-md transition">
                            <i class="fa-solid fa-plus"></i> Add
                        </button>
                    </div>
                @endif

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <ul id="manage-bins-list" class="divide-y divide-slate-100 text-sm"></ul>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end border-t border-slate-200 bg-white px-6 py-4 shrink-0">
                <button type="button" onclick="closeManageBinsModal()"
                    class="rounded-xl border border-slate-300 px-5 py-2 text-slate-600 font-medium transition hover:bg-slate-100">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- <Stock Detail> --}}
    <div id="stockDetailModal" tabindex="-1" aria-hidden="true"
        class="modal-overlay-sale-stacked !p-1 hidden">
        <div class="relative w-full max-w-[98vw] mx-auto h-[98vh] flex items-center justify-center">
            <div class="modal-card-sale h-full">

                <!-- Header -->
                <div class="modal-header-sale">
                        <div class="flex items-center gap-3">
                            <div class="modal-icon-badge-sale">
                                <i class="fa-solid fa-boxes-stacked text-white text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white leading-tight">Stock Detail</h3>
                                <p id="stockDetailProductName" class="text-[12px] text-slate-300"></p>
                            </div>
                        </div>
                        <button type="button" onclick="closeStockDetailModal()"
                            class="modal-close-btn">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                </div>

                <!-- Body -->
                <div class="flex-1 min-h-0 flex flex-col p-1 bg-gray-50">
                    <div class="rounded-2xl bg-white border border-sky-100 shadow-sm overflow-hidden flex-1 min-h-0 flex flex-col">
                        <div class="scroll_content_70 overflow-auto flex-1 min-h-0">
                            <table class="w-full text-left text-sm table-auto">
                                <thead class="bg-sky-50 text-sky-700 sticky top-0 z-10">
                                    <tr class="text-nowrap">
                                        <th class="px-3 py-3 font-bold">{{ __('No.') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Warehouse') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Bin') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Lot') }}</th>
                                        <th class="px-3 py-3 font-bold">{{ __('Expire') }}</th>
                                        <th class="px-3 py-3 font-bold text-right">{{ __('Quantity') }}</th>
                                        <th class="px-3 py-3 font-bold text-right">{{ __('Cost') }}</th>
                                        <th class="px-3 py-3 font-bold text-right">{{ __('Sell Price') }}</th>
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Status') }}</th>
                                        <th class="px-3 py-3 font-bold text-center">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="stock-detail-tbody" class="divide-y divide-sky-50 text-gray-700">
                                    <!-- Dynamic rows inserted here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- <Company Profile / Print Settings> --}}
    <div id="posProfileModal"
        class="modal-overlay-sale hidden">
        <div class="w-full max-w-lg max-h-[90vh] modal-card-sale">

            {{-- Header --}}
            <div class="modal-header-sale">
                <div class="flex items-center gap-3">
                    <div class="modal-icon-badge-sale">
                        <i class="fa-solid fa-building text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Company Profile</h2>
                        <p class="text-sm text-slate-300">Shown on Quotation / Invoice / Delivery Note prints</p>
                    </div>
                </div>
                <button type="button" onclick="closePosProfileModal()"
                    class="modal-close-btn">
                    &times;
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto min-h-0 bg-slate-50 p-6 space-y-4">

                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase">Company Logo</label>
                    <p class="text-[11px] text-slate-400 mb-1">One shared logo, used on the receipt and every printed document.</p>
                    <div class="mt-1 flex items-center gap-4">
                        <div id="pp-logo-preview-box"
                            class="h-16 w-16 rounded-xl border border-slate-300 bg-white flex items-center justify-center overflow-hidden shrink-0">
                            <img id="pp-logo-preview" src="" alt="Logo"
                                class="hidden max-h-full max-w-full object-contain">
                            <i id="pp-logo-placeholder" class="fa-solid fa-image text-slate-300 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="pp-logo-file" accept="image/png,image/jpeg,image/gif,image/webp"
                                onchange="uploadPosProfileLogo(this)"
                                class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0
                                   file:bg-indigo-600 file:px-3.5 file:py-2 file:text-sm file:font-semibold
                                   file:text-white hover:file:bg-indigo-700 cursor-pointer">
                            <p class="text-[11px] text-slate-400 mt-1">PNG/JPG/GIF/WEBP, up to 2MB. Uploads immediately.</p>

                            {{-- Hidden until a logo exists (toggled by
                                 setPosProfileLogoPreview). For companies without a
                                 logo — prints then omit it entirely. --}}
                            <button type="button" id="pp-logo-remove" onclick="removePosProfileLogo()"
                                class="hidden mt-2 inline-flex items-center gap-1.5 rounded-lg border border-rose-200
                                       bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600
                                       hover:bg-rose-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-trash-can"></i>
                                {{ __('Remove logo') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase">Company Name *</label>
                    <input type="text" id="pp-company" placeholder="e.g. Spare Part Supply Co., Ltd"
                        class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                           focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Address Line 1</label>
                        <input type="text" id="pp-address1"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Address Line 2</label>
                        <input type="text" id="pp-address2"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Phone 1</label>
                        <input type="text" id="pp-phone1"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Phone 2</label>
                        <input type="text" id="pp-phone2"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Email</label>
                        <input type="email" id="pp-email"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Telegram</label>
                        <input type="text" id="pp-telegram"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Social (Facebook, etc.)</label>
                        <input type="text" id="pp-social"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Default Seller Name</label>
                        <input type="text" id="pp-seller"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase">Description / Slogan</label>
                    <textarea id="pp-description" rows="2"
                        class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                           focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition"></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4 shrink-0">
                <button type="button" onclick="closePosProfileModal()"
                    class="rounded-xl border border-slate-300 px-5 py-2 text-slate-600 font-medium transition hover:bg-slate-100">
                    Cancel
                </button>
                <button type="button" onclick="savePosProfile()"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 text-sm font-semibold shadow-md transition">
                    <i class="fa-solid fa-floppy-disk"></i> Save
                </button>
            </div>
        </div>
    </div>

    {{-- <Manage Categories> --}}
    <div id="manageCategoriesModal"
        class="modal-overlay-sale hidden">
        <div class="w-full max-w-2xl max-h-[85vh] modal-card-sale">

            {{-- Header --}}
            <div class="modal-header-sale">
                <div class="flex items-center gap-3">
                    <div class="modal-icon-badge-sale">
                        <i class="fa-solid fa-tags text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Manage Categories</h2>
                        <p class="text-sm text-slate-300">Create, rename, or remove product categories</p>
                    </div>
                </div>
                <button type="button" onclick="closeManageCategoriesModal()"
                    class="modal-close-btn">
                    &times;
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto min-h-0 bg-slate-50 p-6 space-y-5">

                <div class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                    <label class="text-xs font-semibold text-slate-500 uppercase">New Category</label>
                    <div class="flex gap-2">
                        <input type="text" id="new-category-name" placeholder="Category name"
                            class="flex-1 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                        <button type="button" onclick="addCategory()"
                            class="inline-flex items-center gap-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 text-sm font-semibold shadow-md transition">
                            <i class="fa-solid fa-plus"></i> Add
                        </button>
                    </div>
                    <input type="text" id="new-category-description" placeholder="Description (optional)"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                           focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <ul id="manage-categories-list" class="divide-y divide-slate-100 text-sm"></ul>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end border-t border-slate-200 bg-white px-6 py-4 shrink-0">
                <button type="button" onclick="closeManageCategoriesModal()"
                    class="rounded-xl border border-slate-300 px-5 py-2 text-slate-600 font-medium transition hover:bg-slate-100">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="logoutModal" class="modal-overlay-alert hidden">
        <div id="modalContent" class="modal-card-alert text-center animate-fadeIn">
            <h2 class="text-lg font-semibold mb-4">Are you sure you want to log out?</h2>
            <div class="flex justify-center gap-4">
                <button id="confirmLogout"
                    class="bg-amber-500 hover:bg-amber-600 text-white font-medium px-4 py-2 rounded-xl transition">Yes</button>
                <button id="cancelLogout"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium px-4 py-2 rounded-xl transition">No</button>
            </div>
        </div>
    </div>
    {{-- Toast  --}}
    <div id="toastMessage"
        class="fixed top-5 right-5 z-50 hidden w-[360px] max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white/90 p-4 shadow-2xl backdrop-blur-xl">

        <div class="flex items-start gap-3">
            <div id="toastIconBox"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                <i id="toastIcon" class="fa-solid fa-check"></i>
            </div>

            <div class="min-w-0 flex-1">
                <p id="toastTitle" class="text-sm font-bold text-slate-800">Success</p>
                <p id="toastText" class="mt-0.5 text-sm text-slate-500"></p>
            </div>

            <button onclick="hideToast()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>













    {{-- <LIST PRODUCT > --}}
    <div id="default-modal-product-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale hidden">

        <div class="flex min-h-full items-center justify-center">
            <div class="w-full max-w-[96vw] h-[92vh] modal-card-sale">

                {{-- Header --}}
                <div class="modal-header-sale flex-col items-stretch gap-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="modal-icon-badge-sale">
                                <i class="fa-solid fa-boxes-stacked text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">ព័ត៌មានផលិតផល</h3>
                                <p class="text-sm text-slate-300">Search, filter and manage product records</p>
                            </div>
                        </div>

                        <button type="button" data-modal-hide="default-modal-product-list"
                            class="modal-close-btn">
                            ✕
                        </button>
                    </div>

                    {{-- Search tools --}}
                    <div class="grid gap-3 lg:grid-cols-5">
                        <div class="relative lg:col-span-2">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="ProductSearchInput" placeholder="Search name, code, barcode..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-0 text-sm text-gray-800 shadow-sm focus:ring-2 focus:ring-sky-300 outline-none">
                        </div>

                        <select id="productSearchCheckbox"
                            class="px-4 py-2.5 rounded-xl border-0 text-sm text-gray-800 shadow-sm focus:ring-2 focus:ring-sky-300 outline-none">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>

                        <select id="productTypeSelect"
                            class="px-4 py-2.5 rounded-xl border-0 text-sm text-gray-800 shadow-sm focus:ring-2 focus:ring-sky-300 outline-none">
                        </select>

                        <select id="productLimitSelect"
                            class="px-4 py-2.5 rounded-xl border-0 text-sm text-gray-800 shadow-sm focus:ring-2 focus:ring-sky-300 outline-none">
                            <option value="10">10 rows</option>
                            <option value="15">15 rows</option>
                            <option selected value="25">25 rows</option>
                            <option value="30">30 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>

                    <label class="inline-flex w-fit items-center gap-2 px-3.5 py-2 rounded-xl bg-white/10 text-sm text-slate-100 cursor-pointer select-none transition hover:bg-white/15">
                        <input type="checkbox" id="exportWithImages" checked
                            class="w-4 h-4 rounded border-white/30 bg-white/10 text-sky-400 focus:ring-sky-400">
                        Export images
                    </label>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-auto bg-slate-50 p-4">
                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="overflow-auto max-h-[calc(92vh-230px)]">

                            <table id="product_table" class="w-full text-sm text-left">
                                <thead
                                    class="sticky top-0 z-20 bg-slate-100 text-[11px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                                    <tr class="text-nowrap">
                                        <th class="px-4 py-3.5 w-10"></th>
                                        <th class="px-4 py-3.5">{{ __('Product') }}</th>
                                        <th class="px-4 py-3.5">{{ __('Category / Unit') }}</th>
                                        <th class="px-4 py-3.5 text-right">{{ __('Price') }}</th>
                                        <th class="px-4 py-3.5 text-right">{{ __('Stock Range') }}</th>
                                        <th class="px-4 py-3.5 text-center">{{ __('Options') }}</th>
                                        <th class="px-4 py-3.5 text-center">{{ __('Status') }}</th>
                                    </tr>
                                </thead>

                                <tbody id="product-table-body"
                                    class="divide-y divide-slate-100 bg-white [&>tr:hover]:bg-sky-50/60 [&>tr]:transition">
                                    {{-- async rows --}}
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 border-t bg-white flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        @if (Auth::user()->hasPermission('product.edit'))
                            <button type="button" id="btnEditProduct"
                                class="px-5 py-2.5 rounded-2xl bg-amber-500 text-white font-semibold hover:bg-amber-600 shadow-sm transition">
                                Edit
                            </button>
                        @endif

                        <button type="button" id="btnAddProduct" data-modal-target="default-modal-add-product"
                            data-modal-toggle="default-modal-add-product"
                            class="px-5 py-2.5 rounded-2xl bg-blue-600 text-white font-semibold hover:bg-blue-700 shadow-sm transition {{ Auth::user()->hasPermission('product.create') ? '' : 'hidden' }}">
                            + New
                        </button>
                        <button type="button" onclick="exportProducts()"
                            class="px-5 py-2.5 rounded-2xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 shadow-sm transition">
                            <i class="fa-regular fa-file-excel mr-1"></i> Export All
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <div id="paginationContainerProduct" class="flex items-center gap-1"></div>
                        <span id="pageInfo" class="text-sm text-slate-500"></span>
                    </div>
                </div>

            </div>
        </div>
    </div>









    {{-- ADD PRODUCT --}}
    <div id="default-modal-add-product" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale items-start md:items-center overflow-y-auto hidden">

        <div class="relative w-full max-w-6xl my-8">
            <form id="AddProductForm" enctype="multipart/form-data"
                class="modal-card-sale">
                @csrf

                {{-- Header --}}
                <div class="modal-header-sale">
                    <div>
                        <h3 class="text-xl font-bold text-white">Add Product</h3>
                        <p class="text-sm text-slate-300">Create product, service, or expense item</p>
                    </div>

                    <button type="button" data-modal-hide="default-modal-add-product"
                        class="modal-close-btn">
                        ✕
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-5 space-y-5 max-h-[78vh] overflow-y-auto">

                    {{-- Basic Info --}}
                    <div class="rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-semibold text-gray-800 mb-4">Basic Information</h4>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Product Code <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="code" required placeholder="PRD001"
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Barcode</label>
                                <input type="text" name="bar_code" placeholder="123456789"
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Product Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" required placeholder="Coca Cola"
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Variant</label>
                                <input type="text" name="variant" placeholder="Can / 330ml"
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" id="description" rows="3" placeholder="Product detail..."
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Stock + Price --}}
                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200 p-5">
                            <h4 class="font-semibold text-gray-800 mb-4">Stock Settings</h4>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Min Stock</label>
                                    <input type="number" name="min_stock" value="0" id="min_stock"
                                        class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-gray-700">Max Stock</label>
                                    <input type="number" name="max_stock" value="0"
                                        class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-sm font-medium text-gray-700">Product Type</label>
                                    <select name="type" id="type"
                                        class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                        <option value="product">Product</option>
                                        <option value="service">Service</option>
                                        <option value="expence">Expense</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <h4 class="font-semibold text-gray-800 mb-4">Pricing</h4>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Unit Price</label>
                                    <input type="number" step="0.01" name="sell_price" value="0"
                                        class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-gray-700">Cost</label>
                                    <input type="number" step="0.01" name="cost" value="0"
                                        class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-gray-700">VAT (%)</label>
                                    <input type="number" step="0.01" name="vat" value="0"
                                        class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-gray-700">Discount %</label>
                                    <input type="number" step="0.01" name="discount_percent" value="0"
                                        class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Category --}}
                    <div class="rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-semibold text-gray-800 mb-4">Category & Unit</h4>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Category</label>
                                <select name="category_id" id="categorySelect"
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                                    <option value="">Loading categories...</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Display On</label>
                                <input name="category_name" id="category_name"
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Unit</label>
                                <input type="text" name="unit" id="unit" placeholder="pcs, kg, box"
                                    class="mt-1 w-full rounded-xl border-gray-300 px-4 py-2.5">
                            </div>
                        </div>
                    </div>

                    {{-- Image + Options --}}
                    <div class="rounded-2xl border border-gray-200 p-5">
                        <h4 class="font-semibold text-gray-800 mb-4">Image & Options</h4>

                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Product Image</label>
                                <input type="file" name="image" id="productImage" accept="image/*"
                                    class="mt-1 block w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center gap-2 rounded-xl border border-gray-200 p-3">
                                    <input type="checkbox" name="status" checked>
                                    <span class="text-sm">Active</span>
                                </label>

                                <label class="flex items-center gap-2 rounded-xl border border-gray-200 p-3">
                                    <input type="checkbox" name="allow_discount" checked>
                                    <span class="text-sm">Allow Discount</span>
                                </label>

                                <label class="flex items-center gap-2 rounded-xl border border-gray-200 p-3">
                                    <input type="checkbox" name="allow_return" checked>
                                    <span class="text-sm">Allow Return</span>
                                </label>

                                <label id="trackStockField" class="flex items-center gap-2 rounded-xl border border-gray-200 p-3">
                                    <input type="checkbox" name="track_stock" id="trackStockCheckbox" checked>
                                    <span class="text-sm">Track Stock</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 border-t">
                    <button type="button" data-modal-hide="default-modal-add-product"
                        class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>

                    <button type="submit"
                        class="px-6 py-2.5 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 shadow">
                        Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
    {{-- <UPDATE PRODUCT> --}}
    <div id="confirm-update-product"
        class="hight_index fixed inset-0 hidden flex items-start md:items-center justify-center backdrop-blur-sm bg-black/50 overflow-y-auto p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col my-8">

            <h2 class="text-2xl font-bold pt-6 text-gray-800 text-center shrink-0">Update Product</h2>

            <form id="updateProductForm"
                class="grid gap-3 grid-cols-4 text-left overflow-y-auto flex-1 px-6 py-4">

                @csrf

                <!-- Hidden ID -->

                <!-- Hidden ID -->
                <input type="hidden" id="prod-id" />

                <div class="col-span-2">
                    <div class="img_400">
                        <img style="border-radius: 10px" id="preview_img" src="" alt="">
                    </div>



                    <input type="file" id="update_image" accept="image/*">
                </div>

                <div class="grid grid-cols-1 col-span-2">
                    <div>
                        <label>Product Code</label>
                        <input id="prod-code" type="text" class="w-full border rounded px-3 py-2" />
                    </div>

                    <div>
                        <label>Barcode</label>
                        <input id="prod-barcode" type="text" class="w-full border rounded px-3 py-2" />
                    </div>
                    <div>
                        <label>Product Name</label>
                        <input id="prod-name" type="text" class="w-full border rounded px-3 py-2" />
                    </div>
                    <div>
                        <label>Varaint</label>
                        <input id="prod-variant" type="text" class="w-full border rounded px-3 py-2" />
                    </div>
                </div>

                <div class="col-span-4">
                    <label>Description</label>
                    <input id="prod-description" type="text" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label>Min Stock</label>
                    <input id="prod-min-stock" type="number" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label>Max Stock</label>
                    <input id="prod-max-stock" type="number" class="w-full border rounded px-3 py-2" />
                </div>

                <div>
                    <label>Cost Price</label>
                    <input id="prod-cost" type="number" step="0.01" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label>Unit Price</label>
                    <input id="prod-sell-price" type="number" step="0.01" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label>Vat</label>
                    <input id="prod-vat" type="number" step="0.01" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label>Discount (%)</label>
                    <input id="prod-discount" type="number" step="0.01" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label>Sell Price</label>
                    <input id="sell_price-final" disabled type="number" step="0.01"
                        class="w-full border rounded px-3 py-2" />
                </div>
                <div></div>
                <div>
                    <label>Category</label>

                    </input>
                    <select id="prod-category-id" class="w-full border rounded px-3 py-2">
                        <!-- fill dynamically -->
                    </select>
                </div>

                <div>
                    <label>Unit</label>
                    <input id="prod-unit" type="text" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label>Display On</label>
                    <input id="prod-category-name" type="text" class="w-full border rounded px-3 py-2" />
                </div>




                <div class="grid grid-cols-4 gap-2  col-span-4">
                    <!-- Track Stock -->
                    <label class="flex items-center flex-col cursor-pointer gap-3">
                        <span class="text-sm">Track Stock</span>
                        <div class="relative">
                            <input id="prod-track-stock" type="checkbox" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer-checked:bg-sky-500 transition">
                            </div>
                            <div
                                class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5">
                            </div>
                        </div>
                    </label>

                    <!-- Allow Discount -->
                    <label class="flex items-center flex-col cursor-pointer gap-3 mt-2">
                        <span class="text-sm">Allow Discount</span>
                        <div class="relative">
                            <input id="prod-allow-discount" type="checkbox" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer-checked:bg-sky-500 transition">
                            </div>
                            <div
                                class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5">
                            </div>
                        </div>
                    </label>

                    <!-- Allow Return -->
                    <label class="flex items-center flex-col cursor-pointer gap-3 mt-2">
                        <span class="text-sm">Allow Return</span>
                        <div class="relative">
                            <input id="prod-allow-return" type="checkbox" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer-checked:bg-sky-500 transition">
                            </div>
                            <div
                                class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5">
                            </div>
                        </div>
                    </label>

                    <!-- Status -->
                    <label class="flex items-center flex-col cursor-pointer gap-3 mt-2">
                        <span class="text-sm">Status</span>
                        <div class="relative">
                            <input id="prod-status" type="checkbox" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer-checked:bg-sky-500 transition">
                            </div>
                            <div
                                class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5">
                            </div>
                        </div>
                    </label>
                </div>
            </form>

            <div class="flex justify-center space-x-4 px-6 py-4 border-t border-gray-200 shrink-0">
                <button type="button" onclick="confirmUpdateProduct()"
                    class="px-5 py-2 bg-sky-500 text-white rounded-xl">
                    Update
                </button>
                <button type="button" onclick="closeUpdateProductModal()"
                    class="px-5 py-2 bg-gray-200 rounded-xl">
                    Cancel
                </button>
            </div>
        </div>
    </div>





    {{-- <LIST SALE DATA> --}}
    <div id="default-modal-sale-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale !p-1 hidden">

        <div class="relative mx-auto w-full max-w-[98vw] h-[98vh] flex items-center justify-center">

            <div class="modal-card-sale h-full">

                {{-- Header --}}
                <div class="modal-header-sale !py-2">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2 whitespace-nowrap">
                        <i class="fa-solid fa-chart-line text-sky-300"></i>
                        របាយការណ៍ការលក់
                        <span class="font-normal text-slate-300">· ត្រង់ទិន្នន័យវិក្កយបត្រ និងព័ត៌មានទំនិញ</span>
                    </h3>

                    <button type="button"
                        class="modal-close-btn shrink-0"
                        data-modal-hide="default-modal-sale-list">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                {{-- Filters --}}
                <div class="shrink-0 bg-slate-50 border-b border-slate-200 px-3 py-2">
                    <div class="flex items-center gap-2 overflow-x-auto">

                        <input type="text" id="document_search_invoice" placeholder="Document No" autocomplete="off"
                            class="min-w-[130px] px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">

                        <input type="date" id="from_date"
                            class="px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none">

                        <input type="date" id="to_date"
                            class="px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none">

                        <select id="invoice_paymentMethod"
                            class="min-w-[110px] px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none">
                            <option value="">💳 All</option>
                        </select>

                        <div class="relative">
                            <input type="text" id="customer_search" placeholder="ស្វែងរកអតិថិជន"
                                autocomplete="off"
                                class="min-w-[140px] px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                            <input type="hidden" id="customer_filter">
                            <ul id="customer_list"
                                class="absolute z-50 bg-white border border-slate-200 w-full mt-1 max-h-60 overflow-y-auto hidden shadow-xl">
                            </ul>
                        </div>

                        <input type="text" id="product_search" list="product_datalist"
                            placeholder="ស្វែងរកទំនិញ" autocomplete="off"
                            class="min-w-[130px] px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">

                        <input type="hidden" id="ProductSearchInput_sale_invoice">
                        <datalist id="product_datalist"></datalist>

                        <select id="category_filter"
                            class="min-w-[120px] px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none">
                            <option value="">📂 All Category</option>
                        </select>

                        <select id="sale_view_limit"
                            class="min-w-[110px] px-3 py-1.5 bg-white border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                            <option selected value="75">75 វិក្កយបត្រ</option>
                            <option value="10">10 វិក្កយបត្រ</option>
                            <option value="20">20 វិក្កយបត្រ</option>
                            <option value="30">30 វិក្កយបត្រ</option>
                            <option value="50">50 វិក្កយបត្រ</option>
                            <option value="100">100 វិក្កយបត្រ</option>
                            <option value="200">200 វិក្កយបត្រ</option>
                            <option value="All">ទាំងអស់</option>
                        </select>

                        <button type="button" onclick="clear_filter_invoice()"
                            class="shrink-0 px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold transition">
                            <i class="fa-solid fa-filter mr-1"></i>
                            Clear
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-auto min-h-0">
                    <table id="Table-sale-list" class="min-w-max w-full text-sm text-left">
                        <thead class="sticky top-0 z-20 bg-slate-900 text-white text-xs">
                            <tr class="text-nowrap">
                                <th class="sticky left-0 z-30 bg-slate-900 px-3 py-2">{{ __('No.') }}</th>
                                <th class="px-3 py-2">{{ __('Invoice No.') }}</th>
                                <th class="px-3 py-2">{{ __('Source Document No.') }}</th>
                                <th class="px-3 py-2">{{ __('Posting Date') }}</th>
                                <th class="px-3 py-2">{{ __('Customer Name') }}</th>
                                <th class="px-3 py-2">{{ __('Phone No.') }}</th>
                                <th class="px-3 py-2">{{ __('Address') }}</th>
                                <th class="px-3 py-2">{{ __('Invoice Date') }}</th>
                                <th class="px-3 py-2">{{ __('Payment Method') }}</th>
                                <th class="px-3 py-2">{{ __('Customer Type') }}</th>
                                <th class="px-3 py-2">{{ __('Item Name') }}</th>
                                <th class="px-3 py-2">{{ __('Variant') }}</th>
                                <th class="px-3 py-2">{{ __('Description') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Quantity') }}</th>
                                <th class="px-3 py-2">{{ __('Unit') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Cost Price') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Sell Price') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Subtotal') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Discount (%)') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Discount Amount') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('VAT (%)') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('VAT Amount') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Net Amount') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Grand Total') }}</th>
                            </tr>
                        </thead>

                        <tbody id="salesTableBody" class="divide-y divide-slate-100 text-slate-700">
                            {{-- async rows --}}
                        </tbody>
                    </table>
                </div>

                {{-- Footer --}}
                <div
                    class="shrink-0 px-3 py-2 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">

                    <div class="flex items-center gap-2">
                        <button type="button" id="downloadSales" onclick="downloadSales()"
                            class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white shadow-sm transition text-sm font-semibold">
                            <i class="fa-regular fa-file-excel mr-1"></i>
                            Excel
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div id="paginationContainer_sale_invoice" class="flex items-center justify-center gap-1">
                            {{-- JS render --}}
                        </div>

                        <span id="pageInfo_sale_invoice" class="text-sm text-slate-500"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>







    @if (Auth::user()->hasPermission('user.view'))
        {{-- <LIST User > --}}
        <div id="default-modal-user-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
            class="modal-overlay-sale items-start md:items-center !p-1 hidden">

            {{-- width Custom  --}}
            <div class="relative w-full max-w-[98vw]">
                <!-- Modal content -->
                <div class="h-[98vh] min-h-[98vh] max-h-[98vh] modal-card-sale">

                    <!-- Modal header -->
                    <div class="modal-header-sale flex-col items-stretch gap-3">
                        <div class="flex w-full items-center justify-between">
                            <h3 id="wh_name" class="text-lg font-bold text-white">
                                User List
                            </h3>
                            <button type="button"
                                class="modal-close-btn"
                                data-modal-hide="default-modal-user-list">
                                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                    width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
                                </svg>
                                <span class="sr-only">Close modal</span>
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Active checkbox -->
                            <select id="active"
                                class="px-3 py-2 border rounded-xl text-sm w-44 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="All">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>

                            </select>
                            <select id="role_filter"
                                class="px-3 py-2 border rounded-xl text-sm w-44 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="All">All Roles</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Supervisor">Supervisor</option>

                                <option value="Admin">Admin</option>
                            </select>


                            <!-- Type select -->
                            <div class="flex items-center gap-2">
                                <input type="text" id="userSearchInput"
                                    placeholder="Search by name ,email..."
                                    class="px-3 py-2 border rounded-xl text-sm w-64 focus:outline-none focus:ring-1 focus:ring-blue-500">

                            </div>
                        </div>
                    </div>
                    <!-- Modal body -->
                    <div class="flex-1 overflow-y-auto min-h-0 p-4 md:p-6">
                        <div class="scroll_content_70 overflow-x-auto">
                            <table id="user-table" class=" w-full text-sm text-left border border-default rounded-base">
                                <thead class="sticky_top text-xs uppercase bg-neutral-secondary">

                                    <tr class="text-nowrap">
                                        <th class="px-4 py-3 text-center">{{ __('Select') }}</th>
                                        <th class="px-4 py-3">{{ __('ID') }}</th>
                                        <th class="px-4 py-3">{{ __('Name') }}</th>
                                        <th class="px-4 py-3">{{ __('Email') }}</th>
                                        <th class="px-4 py-3">{{ __('Phone') }}</th>
                                        <th class="px-4 py-3">{{ __('Role') }}</th>
                                        <th class="px-4 py-3 text-center">{{ __('Active') }}</th>
                                    </tr>
                                    </tr>

                                </thead>
                                <tbody id="user-table-body">
                                    <!-- async rows -->
                                </tbody>
                            </table>
                        </div>

                    </div>
                    <!-- Modal footer -->

                    <div class="flex items-center justify-between border-t border-slate-200 px-4 py-2 bg-white shrink-0">
                        <div>
                            {{-- <button type="button" data-modal-target="default-modal-customer"
                                data-modal-toggle="default-modal-customer"
                                class="text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium rounded-base text-sm px-4 py-2.5">
                                Product Category
                            </button> --}}
                            <button type="button" id="btnEditUser" onclick="openEditUser()"
                                class="text-white bg-slate-900 hover:bg-slate-800 font-medium rounded-xl text-sm px-4 py-2 transition">
                                Edit
                            </button>
                            &ensp;
                            {{-- <button type="button"
                             id="btnDeleteCustomer"
                                class="text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium rounded-base text-sm px-4 py-2.5">
                                Delete
                            </button> --}}


                            <button type="button" id="btnUser" data-modal-target="default-modal-add-user"
                                data-modal-toggle="default-modal-add-user"
                                class="text-white bg-slate-900 hover:bg-slate-800 font-medium rounded-xl text-sm px-4 py-2 transition">
                                New User
                            </button>
                        </div>


                    </div>

                </div>
            </div>
        </div>

    @endif

    {{-- Change Log is deliberately admin-only, not a grantable permission
         (see PermissionSeeder notes) — stays a hard role check. --}}
    @if (Auth::user()->role === 'admin')
        {{-- <Change Log > --}}
        <div id="default-modal-change-log" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
            class="modal-overlay-sale items-start md:items-center !p-1 hidden">

            <div class="relative w-full max-w-[98vw]">
                <div class="h-[98vh] min-h-[98vh] max-h-[98vh] modal-card-sale">

                    <!-- Modal header -->
                    <div class="modal-header-sale flex-col items-stretch gap-3">
                        <div class="flex w-full items-center justify-between">
                            <h3 class="text-lg font-bold text-white">
                                Change Log
                            </h3>
                            <button type="button"
                                class="modal-close-btn"
                                data-modal-hide="default-modal-change-log">
                                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                    width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
                                </svg>
                                <span class="sr-only">Close modal</span>
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <select id="change_log_user"
                                class="px-3 py-2 border rounded-xl text-sm w-48 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">All Users</option>
                            </select>

                            <select id="change_log_section"
                                class="px-3 py-2 border rounded-xl text-sm w-48 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">All Sections</option>
                            </select>

                            <select id="change_log_action"
                                class="px-3 py-2 border rounded-xl text-sm w-44 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">All Actions</option>
                                <option value="created">Created</option>
                                <option value="updated">Updated</option>
                                <option value="deleted">Deleted</option>
                                <option value="permissions_synced">Permissions Synced</option>
                                <option value="login">Login</option>
                                <option value="logout">Logout</option>
                            </select>

                            <div class="flex items-center gap-2">
                                <input type="date" id="change_log_date_from"
                                    class="px-3 py-2 border rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <span class="text-sm text-gray-500">to</span>
                                <input type="date" id="change_log_date_to"
                                    class="px-3 py-2 border rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Modal body -->
                    <div class="flex-1 overflow-y-auto min-h-0 p-4 md:p-6">
                        <div class="scroll_content_70 overflow-x-auto">
                            <table id="change-log-table" class="w-full text-sm text-left border border-default rounded-base">
                                <thead class="sticky_top text-xs uppercase bg-neutral-secondary">
                                    <tr class="text-nowrap">
                                        <th class="px-4 py-3">{{ __('Date/Time') }}</th>
                                        <th class="px-4 py-3">{{ __('User') }}</th>
                                        <th class="px-4 py-3">{{ __('Action') }}</th>
                                        <th class="px-4 py-3">{{ __('Section') }}</th>
                                        <th class="px-4 py-3">{{ __('Model') }}</th>
                                        <th class="px-4 py-3">{{ __('Old &rarr; New') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="change-log-table-body">
                                    <!-- async rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="flex items-center justify-between border-t border-slate-200 px-4 py-2 bg-white shrink-0">
                        <div id="change_log_pagination_info" class="text-sm text-gray-500"></div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="change_log_prev"
                                class="rounded-xl border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 transition">
                                Prev
                            </button>
                            <button type="button" id="change_log_next"
                                class="rounded-xl border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 transition">
                                Next
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    @endif

    @if (Auth::user()->hasPermission('user.create') || Auth::user()->hasPermission('user.edit'))
        {{-- <Manage Permissions (shared by Add + Edit User) > --}}
        <div id="default-modal-manage-permissions" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
            class="modal-overlay-sale-top items-start md:items-center !p-2 hidden">
            <div class="relative w-full max-w-5xl">
                <div class="h-[92vh] min-h-[92vh] max-h-[92vh] modal-card-sale">

                    <!-- Header -->
                    <div class="modal-header-sale flex-col items-stretch gap-3">
                        <div class="flex w-full items-center justify-between">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-shield-halved"></i>
                                Manage Permissions
                            </h3>
                            <button type="button" onclick="closePermissionsModal()" class="modal-close-btn">
                                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                    width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
                                </svg>
                                <span class="sr-only">Close modal</span>
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="relative flex-1 min-w-[220px]">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-white/50 text-sm"></i>
                                <input type="text" id="permissionSearchInput" placeholder="Search section, permission, key..."
                                    class="w-full rounded-xl border-0 bg-white/10 pl-9 pr-3 py-2 text-sm text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-white/30">
                            </div>
                            <div class="flex items-center rounded-xl bg-white/10 p-1 text-xs font-semibold text-white">
                                <button type="button" id="permissionViewCardBtn"
                                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition bg-white/20">
                                    <i class="fa-solid fa-grip"></i> Card
                                </button>
                                <button type="button" id="permissionViewListBtn"
                                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition">
                                    <i class="fa-solid fa-list"></i> List
                                </button>
                            </div>
                            <button type="button" onclick="setAllPermissions(true)"
                                class="rounded-xl bg-white/10 hover:bg-white/20 px-3 py-2 text-xs font-semibold text-white transition">
                                Select All
                            </button>
                            <button type="button" onclick="setAllPermissions(false)"
                                class="rounded-xl bg-white/10 hover:bg-white/20 px-3 py-2 text-xs font-semibold text-white transition">
                                Clear All
                            </button>
                            <span id="permissionModalCounter"
                                class="rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold text-sky-300 text-nowrap">
                                0 of 0 selected
                            </span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto min-h-0 p-4 md:p-6 bg-gray-50">
                        <div id="permissionModalGrid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                            <!-- Card view: section cards rendered by admin.js -->
                        </div>
                        <div id="permissionModalList" class="hidden rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
                            <table class="w-full min-w-[560px] text-sm">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0">
                                    <tr>
                                        <th data-sort="section" data-label="Section" class="px-3 py-2.5 text-left cursor-pointer select-none hover:text-gray-700">{{ __('Section') }}</th>
                                        <th data-sort="label" data-label="Permission" class="px-3 py-2.5 text-left cursor-pointer select-none hover:text-gray-700">{{ __('Permission') }}</th>
                                        <th data-sort="granted" data-label="Granted" class="px-3 py-2.5 text-center cursor-pointer select-none hover:text-gray-700">{{ __('Granted') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="permissionModalListBody">
                                    <!-- List view: permission rows rendered by admin.js -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-4 py-3 md:px-6">
                        <button type="button" onclick="closePermissionsModal()"
                            class="bg-slate-900 text-white font-medium rounded-xl text-sm px-5 py-2.5 hover:bg-slate-800 transition">
                            Done
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

    @if (Auth::user()->hasPermission('user.create'))
        {{-- <ADD User > --}}
        <div id="default-modal-add-user" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
            class="modal-overlay-sale overflow-y-auto hidden">
            <div class="relative w-full max-w-5xl">
                <!-- Modal content -->

                <div class="modal-card-sale">


                    <form id="AddUserForm" method="POST">
                        @csrf

                        <!-- Modal header -->
                        <div class="modal-header-sale">
                            <h3 class="text-lg font-bold text-white">
                                Add New User
                                <div id="formError" class="text-red-400 text-sm mt-2 hidden"></div>
                            </h3>

                            <button type="button"
                                class="modal-close-btn"
                                data-modal-hide="default-modal-add-user">
                                ✕
                            </button>
                        </div>

                        <!-- Modal body -->
                        <div class="space-y-4 md:space-y-6 p-4 md:p-6">

                            <div class="grid gap-6 md:grid-cols-1">

                                <!-- Display Name -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        Display Name <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" name="display_name" id="display_name" required
                                        placeholder="Candy"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                                <!-- Username -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        User Login <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" name="username" id="username" required
                                        placeholder="candy"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                                <!-- Role -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        User Role <span class="text-rose-600">*</span>
                                    </label>
                                    <select name="role" id="role" required
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                        <option value="cashier">Cashier</option>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        Email <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="email" name="email" id="email" required
                                        placeholder="johndoe@example.com"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        Password <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="password" name="password" id="password" required minlength="4"
                                        placeholder="••••••••"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                            </div>

                            <!-- Warehouses -->
                            <div class="mt-4">
                                <label class="block mb-2.5 text-sm font-medium text-heading">
                                    User Can Use Warehouses
                                </label>

                                <div id="warehouseList" class="grid grid-cols-2 gap-2">
                                    <!-- Example (your JS should generate like this) -->
                                    <!--
                                                                                                                                                                            <label>
                                                                                                                                                                                <input type="checkbox" name="warehouses[]" value="1"> Warehouse A
                                                                                                                                                                            </label>
                                                                                                                                                                            -->
                                </div>
                            </div>

                            <!-- Permissions -->
                            <div class="mt-4">
                                <label class="block mb-2.5 text-sm font-medium text-heading">
                                    User Permissions
                                </label>
                                <button type="button" id="openPermissionsBtn_add"
                                    onclick="openPermissionsModal('add')"
                                    class="w-full flex items-center justify-between gap-3 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm transition hover:border-amber-400 hover:bg-amber-50">
                                    <span class="flex items-center gap-2 font-medium text-gray-700">
                                        <i class="fa-solid fa-shield-halved text-amber-500"></i>
                                        Manage Permissions
                                    </span>
                                    <span id="permissionSummary_add"
                                        class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">
                                        0 selected
                                    </span>
                                </button>
                                <!-- Actual permissions[] checkboxes live here for form submission; moved
                                     in/out of the shared Manage Permissions modal by admin.js -->
                                <div id="permissionList" class="hidden"></div>
                            </div>

                        </div>

                        <!-- Modal footer -->
                        <div class="flex items-center border-t border-slate-200 space-x-4 pt-4 md:pt-5">
                            <button type="submit" id="submitBtn"
                                class="bg-slate-900 text-white font-medium rounded-xl text-sm px-4 py-2.5 hover:bg-slate-800 transition">
                                Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    @endif

    @if (Auth::user()->hasPermission('user.edit'))
        {{-- <EDIT User > --}}
        <div id="default-modal-edit-user" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
            class="modal-overlay-sale-stacked overflow-y-auto hidden">
            <div class="relative w-full max-w-5xl">
                <!-- Modal content -->

                <div class="modal-card-sale">

                    <form id="EditUserForm" method="POST">
                        @csrf
                        <input type="hidden" id="edit_user_id">

                        <!-- Modal header -->
                        <div class="modal-header-sale">
                            <h3 class="text-lg font-bold text-white">
                                Edit User
                                <div id="editFormError" class="text-red-400 text-sm mt-2 hidden"></div>
                            </h3>

                            <button type="button"
                                class="modal-close-btn"
                                onclick="closeEditUserModal()">
                                ✕
                            </button>
                        </div>

                        <!-- Modal body -->
                        <div class="space-y-4 md:space-y-6 p-4 md:p-6">

                            <div class="grid gap-6 md:grid-cols-1">

                                <!-- Display Name -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        Display Name <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" name="display_name" id="edit_display_name" required
                                        placeholder="Candy"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                                <!-- Username -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        User Login <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" name="username" id="edit_username" required
                                        placeholder="candy"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                                <!-- Role -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        User Role <span class="text-rose-600">*</span>
                                    </label>
                                    <select name="role" id="edit_role" required
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                        <option value="cashier">Cashier</option>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        Email
                                    </label>
                                    <input type="email" name="email" id="edit_email"
                                        placeholder="johndoe@example.com"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block mb-2.5 text-sm font-medium text-heading">
                                        Password
                                    </label>
                                    <input type="password" name="password" id="edit_password" minlength="4"
                                        placeholder="Leave blank to keep current password"
                                        class="bg-neutral-secondary-medium border border-default-medium rounded-base w-full px-3 py-2.5">
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-heading">
                                        <input type="checkbox" name="status" id="edit_status"
                                            class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-400">
                                        Active
                                    </label>
                                </div>

                            </div>

                            <!-- Warehouses -->
                            <div class="mt-4">
                                <label class="block mb-2.5 text-sm font-medium text-heading">
                                    User Can Use Warehouses
                                </label>

                                <div id="editWarehouseList" class="grid grid-cols-2 gap-2">
                                </div>
                            </div>

                            <!-- Permissions -->
                            <div class="mt-4">
                                <label class="block mb-2.5 text-sm font-medium text-heading">
                                    User Permissions
                                </label>
                                <button type="button" id="openPermissionsBtn_edit"
                                    onclick="openPermissionsModal('edit')"
                                    class="w-full flex items-center justify-between gap-3 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm transition hover:border-amber-400 hover:bg-amber-50">
                                    <span class="flex items-center gap-2 font-medium text-gray-700">
                                        <i class="fa-solid fa-shield-halved text-amber-500"></i>
                                        Manage Permissions
                                    </span>
                                    <span id="permissionSummary_edit"
                                        class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">
                                        0 selected
                                    </span>
                                </button>
                                <!-- Actual permissions[] checkboxes live here for form submission; moved
                                     in/out of the shared Manage Permissions modal by admin.js -->
                                <div id="editPermissionList" class="hidden"></div>
                            </div>

                        </div>

                        <!-- Modal footer -->
                        <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-4 py-3 md:px-6">
                            <button type="button" onclick="closeEditUserModal()"
                                class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 transition">
                                Cancel
                            </button>
                            <button type="button" id="editSubmitBtn" onclick="updateUser()"
                                class="bg-sky-600 text-white font-medium rounded-xl text-sm px-4 py-2.5 hover:bg-sky-700 transition">
                                Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif


    <div id="lotModal" class="modal-overlay-alert hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl p-4 animate-scaleUp">
            <!-- Header / Close -->
            <div class="flex justify-end mb-4">
                <button onclick="closeLotModal()"
                    class="text-gray-400 hover:text-gray-700 text-2xl font-bold">&times;</button>
            </div>

            <!-- Main content: image + info + lots -->
            <div class="flex gap-4">
                <!-- Product Image -->
                <div class="flex-shrink-0">
                    <img id="display_img" src="" alt="Product Image"
                        class="w-40 h-40 object-cover rounded-lg border shadow">
                </div>

                <!-- Right side: Name + lots table -->
                <div class="flex-1 flex flex-col">
                    <!-- Product Name / ID / Track Qty -->
                    <h2 id="item-id" class="text-2xl font-bold text-gray-800 mb-4">Loading...</h2>

                    <!-- Bin/lot selection is optional — default is "let the
                         system auto-pick", manual per-bin/lot split is opt-in. -->
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer select-none mb-2">
                        <input type="checkbox" id="lot-auto-pick" checked class="w-4 h-4 accent-emerald-500"
                            onchange="toggleLotAutoPick()">
                        Auto-pick (don't choose bin/lot manually)
                    </label>

                    <div id="lot-auto-fill-row" class="mb-2 flex items-center gap-2">
                        <label class="text-sm text-gray-600">Qty to sell</label>
                        <input type="number" id="lot-auto-qty" min="0.01" step="0.01"
                            class="border px-2 py-1 rounded w-32" oninput="updateLotWarning()">
                    </div>

                    <!-- Lots Table -->
                    <div id="lotModalBody" class="overflow-y-auto max-h-80 border rounded p-4 bg-gray-50 grid gap-2 hidden">
                        <!-- JS will inject lots rows here -->
                    </div>

                    <!-- Footer: warning + save -->
                    &ensp;
                    <div class="flex justify-between items-center mt-4 ">
                        <button id="save-lot-btn" onclick="saveLots()"
                            class="px-5 py-2 rounded-xl transition bg-gray-400" disabled>
                            Save
                        </button>
                        <p id="lot-warning" class="text-red-500 hidden text-sm"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <div id="viewLotModal"
        class="modal-overlay-alert hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-4xl max-w-5xl p-6 animate-scaleUp">

            <!-- Header: Image left, Title right -->
            <div class="flex items-start mb-4 gap-4">
                <!-- Product Image -->
                <div class="flex-shrink-0">
                    <img id="display_img2" src="" alt="Product Image"
                        class="w-32 h-32 object-cover rounded-lg border shadow">
                </div>

                <!-- Title & Info -->
                <div class="flex-1">
                    <h2 id="view-lot-title" class="text-xl font-bold text-gray-800 mb-2">Loading...</h2>
                    <p id="view-lot-info" class="text-gray-600 text-sm">
                        <!-- Optional: show product ID, stock, or other info -->
                    </p>
                </div>

                <!-- Close button -->
                <button onclick="closeViewLotModal()"
                    class="text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
            </div>

            <!-- Table Body -->
            <div id="viewLotModalBody" class=" overflow-y-auto max-h-72 space-y-2">
                <!-- JS injects rows here -->
            </div>

            <!-- Footer -->
            <div class="flex justify-end mt-4">
                <button onclick="closeViewLotModal()"
                    class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">Close</button>
            </div>
        </div>
    </div>


    <div id="transfer_modal" tabindex="-1" aria-hidden="true"
        class="modal-overlay-sale-stacked z-[70] hidden">
        <div class="relative w-full max-w-3xl mx-auto max-h-[94vh] flex items-center justify-center">
            <div class="w-full max-h-[94vh] modal-card-sale">

                @csrf

                <!-- Header -->
                <div class="modal-header-sale">
                        <div class="flex items-center gap-3">
                            <div class="modal-icon-badge-sale">
                                <i class="fa-solid fa-arrow-right-arrow-left text-white text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white leading-tight">Transfer Item</h3>
                                <p class="text-[12px] text-slate-300">Move stock between warehouses or bins</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeLotModal_transfer()"
                            class="modal-close-btn">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto min-h-0 p-5 bg-gray-50 space-y-5">

                    <!-- From Location -->
                    <div class="rounded-2xl bg-white border border-emerald-100 shadow-sm overflow-hidden">
                        <div class="px-4 py-2.5 bg-emerald-50 border-b border-emerald-100 flex items-center gap-2">
                            <i class="fa-solid fa-location-dot text-emerald-600"></i>
                            <span class="text-sm font-bold text-emerald-800">From</span>
                            <span id="location-display" class="text-sm text-emerald-700"></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="text-gray-500">
                                    <tr class="text-nowrap">
                                        <th class="px-4 py-2 font-semibold">{{ __('Product') }}</th>
                                        <th class="px-4 py-2 font-semibold">{{ __('Lot') }}</th>
                                        <th class="px-4 py-2 font-semibold text-right">{{ __('Available Qty') }}</th>
                                        <th class="px-4 py-2 font-semibold">{{ __('Unit') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="from_location_body" class="divide-y divide-gray-100 text-gray-700">
                                    <!-- JS will populate this -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- To Location -->
                    <div class="rounded-2xl bg-white border border-sky-100 shadow-sm p-4 space-y-4">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-flag-checkered text-sky-600"></i>
                            <span class="text-sm font-bold text-sky-800">To</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Destination Warehouse</label>
                                <select id="to_location_select"
                                    onchange="onTransferDestinationWarehouseChange(this.value)"
                                    class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                    <option value="">Select warehouse</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Destination Bin</label>
                                <select id="to_location_bin" onchange="validateTransferForm()"
                                    class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                    <option value="">No Bin</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-500 uppercase">Qty to Transfer</label>
                            <input id="transfer_qty" type="number" min="0.01" step="0.01" placeholder="0.00"
                                onchange="validateTransferForm()" onblur="clampTransferQty()"
                                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                   focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                            <p id="transfer_qty_error" class="hidden mt-1 text-xs font-medium text-rose-600"></p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4 bg-white shrink-0">
                    <button type="button" onclick="closeLotModal_transfer()"
                        class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="button" id="confirmTransferBtn" onclick="submitTransfer()" disabled
                        class="px-5 py-2 rounded-xl bg-gray-400 cursor-not-allowed text-white text-sm font-bold inline-flex items-center gap-2 transition">
                        <i class="fa-solid fa-check"></i> Confirm Transfer
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- <Quick Transfer (FEFO) - no lot picking, oldest-expiry-first> --}}
    <div id="fefoTransferModal" tabindex="-1" aria-hidden="true"
        class="modal-overlay-sale-stacked z-[70] hidden">
        <div class="relative w-full max-w-lg mx-auto flex items-center justify-center">
            <div class="w-full modal-card-sale">

                <!-- Header -->
                <div class="modal-header-sale">
                    <div class="flex items-center gap-3">
                        <div class="modal-icon-badge-sale">
                            <i class="fa-solid fa-arrow-right-arrow-left text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white leading-tight">Quick Transfer</h3>
                            <p class="text-[12px] text-slate-300">
                                <span id="fefo_transfer_product_name"></span> · oldest-expiry lots first
                            </p>
                        </div>
                    </div>
                    <button type="button" onclick="closeFefoTransferModal()"
                        class="modal-close-btn">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 bg-gray-50 space-y-4">
                    <input type="hidden" id="fefo_transfer_product_id" value="">

                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">From Warehouse</label>
                        <select id="fefo_from_warehouse" onchange="onFefoSourceWarehouseChange()"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                        </select>
                        <p class="mt-1 text-xs text-slate-500">
                            Available: <span id="fefo_from_available" class="font-semibold">0</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-500 uppercase">To Warehouse</label>
                            <select id="fefo_to_warehouse" onchange="onFefoDestinationWarehouseChange()"
                                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                   focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                <option value="">Select warehouse</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-500 uppercase">Destination Bin</label>
                            <select id="fefo_to_bin" onchange="validateFefoTransferForm()"
                                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                   focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                <option value="">No Bin</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Qty to Transfer</label>
                        <input id="fefo_transfer_qty" type="number" min="0.01" step="0.01" placeholder="0.00"
                            oninput="validateFefoTransferForm()"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                               focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                        <p id="fefo_transfer_qty_error" class="hidden mt-1 text-xs font-medium text-rose-600"></p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4 bg-white shrink-0">
                    <button type="button" onclick="closeFefoTransferModal()"
                        class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="button" id="confirmFefoTransferBtn" onclick="submitFefoTransfer()" disabled
                        class="px-5 py-2 rounded-xl bg-gray-400 cursor-not-allowed text-white text-sm font-bold inline-flex items-center gap-2 transition">
                        <i class="fa-solid fa-check"></i> Confirm Transfer
                    </button>
                </div>
            </div>
        </div>
    </div>














    <div id="default-modal-ledger-entry-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale !p-1 hidden">

        <div class="relative w-full max-w-[98vw] mx-auto h-[98vh] flex items-center justify-center">

            <div class="modal-card-sale h-full">

                <!-- Modal header -->
                <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 px-3 py-2 shrink-0">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-bold text-white whitespace-nowrap">
                            របាយការណ៍ ទំនិញ ចេញចូល <span class="font-normal text-sky-200">· Item ledger entry</span>
                        </h3>
                        <button type="button"
                            class="modal-close-btn shrink-0"
                            data-modal-hide="default-modal-ledger-entry-list">
                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2 items-center">

                        <!-- Global -->
                        <input type="text" id="filter_global"
                            placeholder="Doc / Source / Item / Desc / Category..."
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs w-56
               focus:border-sky-500 focus:ring-2 focus:ring-sky-100">

                        <!-- LOT -->
                        <input type="text" id="filter_lot" placeholder="Lot"
                            class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-28
               focus:border-sky-500 focus:ring-2 focus:ring-sky-100">

                        <!-- WAREHOUSE 🔥 -->
                        <input type="text" id="filter_warehouse" placeholder="Warehouse"
                            class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs w-36
               focus:border-sky-500 focus:ring-2 focus:ring-sky-100">

                        <!-- DATE -->
                        <input type="date" id="filter_date_from"
                            class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs
               focus:border-sky-500 focus:ring-2 focus:ring-sky-100">

                        <input type="date" id="filter_date_to"
                            class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs
               focus:border-sky-500 focus:ring-2 focus:ring-sky-100">

                        <!-- TYPE -->
                        <select id="filter_doc_type"
                            class="px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs bg-white
               focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                            <option value="">All Type</option>
                            <option value="Sales Invoice">Sales Invoice</option>
                            <option value="Sale Return">Sale Return</option>
                            <option value="Transfer Receipt">Transfer Receipt</option>
                            <option value="Transfer Shipment">Transfer Shipment</option>
                            <option value="Purchase">Purchase</option>
                            <option value="Adjustment">Adjustment</option>
                        </select>

                        <!-- CLEAR -->
                        <button onclick="clearItemLedgerFilters()"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs hover:bg-gray-200">
                            Clear
                        </button>

                    </div>
                </div>

                <!-- Modal body -->
                <div class="flex-1 min-h-0 flex flex-col p-1 bg-gray-50">

                    <div class="rounded-2xl bg-white border border-sky-100 shadow-sm overflow-hidden flex-1 min-h-0 flex flex-col">

                        <div class="scroll_content_70 overflow-auto flex-1 min-h-0">
                            <table id="Table-item-ledger-entry" class="w-full text-sm text-left border-collapse">

                                <thead class="bg-sky-50 text-sky-700 text-xs uppercase sticky top-0 z-10">
                                    <tr class="text-nowrap">
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Entry No.') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Posting Date') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Document Type') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Document No.') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Source No.') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Barcode') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Item Code') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Item Name') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Variant') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Description') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Unit') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Category') }}</th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Warehouse') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Lot No.') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Expiry Date') }}</th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Quantity') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Remaining Qty') }}
                                        </th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Entry Type') }}</th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Cost') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Unit Price') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Selling Price') }}
                                        </th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Discount (%)') }}
                                        </th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Discount Amount') }}
                                        </th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('VAT (%)') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('VAT Amount') }}</th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Line Amount') }}
                                        </th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Net Amount') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold text-right">{{ __('Total Amount') }}
                                        </th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Customer No.') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Customer Name') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Customer Phone') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Customer Address') }}</th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Vendor No.') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Vendor Name') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Payment Method') }}</th>

                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Created By') }}</th>
                                        <th class="border-b border-sky-100 px-2 py-1.5 font-bold">{{ __('Created At') }}</th>
                                    </tr>
                                </thead>

                                <tbody id="item_ledger_entry_table_body" class="divide-y divide-sky-50 text-gray-700">
                                    <!-- async rows -->
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

                <!-- Modal footer -->
                <div class="flex items-center justify-between border-t border-sky-100 px-3 py-2 bg-white">

                    <div class="flex items-center gap-2">
                        <div class="flex items-center justify-center gap-1"
                            id="paginationContainer_item_ledger_entry">
                        </div>

                        <span id="pageInfo_item_ledger_entry" class="text-sm text-gray-600"></span>
                    </div>
                    <div class="flex gap-2">

                    </div>

                </div>

            </div>
        </div>
    </div>

    {{-- Always rendered (even without expense.view) so the always-present drawer trigger in
         master.blade.php has a valid Flowbite target — see note on the currency modal above. --}}
    <div id="default-modal-expense-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-sale !p-1 hidden">

            <div class="relative w-full max-w-[98vw] mx-auto h-[98vh] flex items-center justify-center">

                <div class="modal-card-sale h-full">

                    <!-- HEADER -->
                    <div class="modal-header-sale flex-col items-stretch !py-1.5">

                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-xs font-bold text-white flex items-center gap-2 whitespace-nowrap">
                                <i class="fa-solid fa-wallet"></i>
                                របាយការណ៍ ចំណាយ
                                <span class="font-normal text-slate-300">· Expense list, filter, export and payment tracking</span>
                            </h3>

                            <button type="button" data-modal-hide="default-modal-expense-list"
                                class="modal-close-btn shrink-0">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <!-- FILTERS -->
                        <div class="mt-1.5 flex items-center gap-1.5 overflow-x-auto">

                            <input type="date" id="expense_from_date"
                                class="h-7 px-2 border border-slate-300 bg-slate-50 text-slate-700 text-xs outline-none focus:bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400">

                            <input type="date" id="expense_to_date"
                                class="h-7 px-2 border border-slate-300 bg-slate-50 text-slate-700 text-xs outline-none focus:bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400">

                            <input type="text" id="expense_search" placeholder="Search code / name / note"
                                class="h-7 min-w-[220px] px-2 border border-slate-300 bg-slate-50 text-slate-700 text-xs outline-none focus:bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400">

                            <select id="expense_limit"
                                class="h-7 px-2 border border-slate-300 bg-slate-50 text-slate-700 text-xs outline-none focus:bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400">

                                <option value="10">10 Rows</option>
                                <option value="25">25 Rows</option>
                                <option selected value="50">50 Rows</option>
                                <option value="100">100 Rows</option>
                                <option value="All">All Rows</option>
                            </select>

                        </div>
                    </div>

                    <!-- BODY -->
                    <div class="flex-1 min-h-0 bg-slate-100 p-3">

                        <div class="h-full bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">

                            <div class="overflow-auto flex-1">
                                <table id="Table-expense-list" class="min-w-full text-sm text-left text-slate-700">
                                    <thead
                                        class="sticky top-0 z-20 bg-slate-100 text-xs uppercase text-slate-600 border-b border-slate-200 shadow-sm">
                                        <tr class="text-nowrap">

                                            <th class="px-3 py-2 w-14 font-bold">{{ __('No.') }}</th>

                                            <th class="sortable px-3 py-2 font-bold" data-column="expense_date">
                                                {{ __('Expense Date') }} ↕
                                            </th>

                                            <th class="sortable px-3 py-2 font-bold" data-column="expense_code">
                                                {{ __('Code') }} ↕
                                            </th>

                                            <th class="sortable px-3 py-2 font-bold" data-column="expense_name">
                                                {{ __('Expense Name') }} ↕
                                            </th>

                                            <th class="sortable px-3 py-2 text-right font-bold" data-column="amount">
                                                {{ __('Amount') }} ↕
                                            </th>

                                            <th class="px-3 py-2 min-w-[350px] font-bold">
                                                {{ __('Remarks') }}
                                            </th>

                                        </tr>
                                    </thead>

                                    <tbody id="expense_table_body" class="divide-y divide-slate-100 bg-white">
                                        <!-- async rows -->
                                    </tbody>
                                </table>
                            </div>

                            <div id="expense_empty_state"
                                class="hidden flex-1 flex-col items-center justify-center text-slate-500 py-16">
                                <i class="fa-solid fa-wallet text-5xl mb-3 text-slate-300"></i>
                                <p class="text-lg font-bold">No expense found</p>
                                <p class="text-sm mt-1">Try changing filters or search keyword</p>
                            </div>

                        </div>
                    </div>

                    <!-- FOOTER -->
                    <div class="px-3 py-2 border-t bg-white flex flex-col lg:flex-row items-center justify-between gap-3">

                        <div class="flex items-center gap-2">
                            <button type="button" id="btnPrintExpense"
                                class="hidden px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold transition">
                                <i class="fa-solid fa-print mr-1"></i>
                                Print
                            </button>

                            <button type="button" id="downloadExpense"
                                class="hidden px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition">
                                <i class="fa-regular fa-file-excel mr-1"></i>
                                Excel
                            </button>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap justify-center">
                            <div id="paginationContainer_expense" class="flex items-center gap-1"></div>
                            <span id="pageInfo_expense" class="text-sm text-slate-500 font-medium"></span>
                        </div>

                    </div>

                </div>
            </div>
        </div>

    {{-- <LIST Sale Order DATA> --}}
    <div id="default-modal-sales-order-list" tabindex="-1" aria-hidden="true"
        class="modal-overlay-sale items-start md:items-center !p-1 hidden">

        <div class="relative w-full max-w-[98vw]">

            <div class="modal-card-sale p-5 h-[98vh] min-h-[98vh] max-h-[98vh]">

                <!-- HEADER -->
                <div class="modal-header-sale -mx-5 -mt-5 mb-2 !py-2">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2 whitespace-nowrap">
                        🧾 បញ្ជីបញ្ជាទិញអតិថិជន
                        <span class="font-normal text-slate-300">· ស្វែងរក និងត្រួតពិនិត្យ</span>
                    </h3>

                    <button type="button" onclick="closeSaleOrderModal()"
                        class="modal-close-btn shrink-0">
                        ✕
                    </button>
                </div>

                @csrf

                <!-- FILTERS -->
                @php
                    $users = \App\Models\User::orderBy('name')->get();
                @endphp

                <div class="bg-gray-50 border rounded-2xl p-2 mb-2">

                    <div class="flex items-center gap-2 overflow-x-auto">

                        <input id="sale_document_search" placeholder="Doc No"
                            class="min-w-[120px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm
               focus:ring-1 focus:ring-sky-300">

                        <input id="sale_order_search" placeholder="Customer / Phone"
                            class="min-w-[150px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm
               focus:ring-1 focus:ring-sky-300">
                        @if (Auth::user()->role != 'cashier')
                            <select id="sale_order_user_id" onchange="loadSaleOrders(1)"
                                class="min-w-[140px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                                <option value="">Seller</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->username }}</option>
                                @endforeach
                            </select>
                        @endif
                        <select id="sale_order_status"
                            class="min-w-[130px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                            <option value="">Status</option>
                            <option value="Quotation">Quotation</option>
                            <option value="Ordered">Ordered</option>
                            <option value="Deposit">Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Returned">Returned</option>
                        </select>

                        <select id="sale_order_payment_status"
                            class="min-w-[130px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                            <option value="">Payment</option>
                            <option value="Unpaid">Unpaid</option>
                            <option value="Partial">Partial</option>
                            <option value="Paid">Paid</option>
                            <option value="Refunded">Refunded</option>
                        </select>

                        <select id="sale_order_delivery_status"
                            class="min-w-[130px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                            <option value="">Delivery</option>
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Returned">Returned</option>
                            <option value="N/A">N/A</option>
                        </select>

                        <input type="date" id="so_from_posting_dateInput"
                            class="min-w-[130px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm">

                        <input type="date" id="so_to_posting_dateInput"
                            class="min-w-[130px] rounded-lg border border-gray-300 px-2 py-1.5 text-sm">

                        <button onclick="clearSaleOrderFilters()"
                            class="min-w-[90px] bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-sm">
                            {{ __('Clear') }}
                        </button>

                        {{-- Bulk-marks every order matching the CURRENT filters as
                             Delivered. Server re-applies the same visibility scope and
                             filters, so a cashier can only ever affect their own orders. --}}
                        @if (Auth::user()->hasPermission('pos_sale.mark_delivered'))
                            <button type="button" id="markAllDeliveredBtn" onclick="markAllDelivered()"
                                class="min-w-[150px] bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-sm
                                       disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-truck-fast"></i>
                                {{ __('Mark All Delivered') }}
                            </button>
                        @endif

                    </div>

                </div>

                <!-- TABLE -->
                <div class="flex-1 min-h-0 flex flex-col mt-4">
                    <div class="scroll_content_70 overflow-auto flex-1 min-h-0">

                        <table id="Table-sale-order" class="w-full text-sm">
                            <thead class="bg-gray-100 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-2"></th>
                                    <th class="px-3 py-2">{{ __('No.') }}</th>
                                    <th class="px-3 py-2">{{ __('Document No.') }}</th>
                                    <th class="px-3 py-2 text-center">{{ __('Posting Date') }}</th>
                                    <th class="px-3 py-2 text-center">{{ __('Order Date') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('VAT') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Discount') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Grand Total') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Paid') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Remaining') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Status') }}</th>
                                    <th class="px-3 py-2 text-right hidden">{{ __('Payment') }}</th>
                                    <th class="px-3 py-2 text-center">{{ __('Delivery') }}</th>
                                    <th class="px-3 py-2">{{ __('Customer') }}</th>
                                    <th class="px-3 py-2">{{ __('Phone') }}</th>
                                    <th class="px-3 py-2">{{ __('Address') }}</th>
                                    <th class="px-3 py-2">{{ __('Taxi Phone') }}</th>
                                    <th class="px-3 py-2">{{ __('Return') }}</th>
                                    <th class="px-3 py-2">{{ __('Seller') }}</th>
                                </tr>
                            </thead>

                            <tbody id="Table-sale-order-list"></tbody>
                        </table>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="flex justify-between items-center mt-4">

                    {{-- Hidden until a row is selected (selectSaleOrderRow() reveals
                         it) — same actions as the right-click menu, for anyone who
                         prefers clicking a row then a button over right-clicking. --}}
                    <div id="saleOrderRowActions" class="hidden flex-wrap items-center gap-3">

                        <!-- View Line Button -->
                        <button onclick="viewSelectedSaleOrderLine()"
                            class=" group relative overflow-hidden px-5 py-2.5 rounded-xl
                                bg-gradient-to-r from-sky-500 to-blue-600
                                hover:from-sky-600 hover:to-blue-700
                                text-white font-semibold shadow-md hover:shadow-xl
                                transition-all duration-300 active:scale-95">

                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-eye text-sm"></i>
                                View Line
                            </span>

                            <!-- glow -->
                            <span
                                class="absolute inset-0 bg-white/10 opacity-0
                                    group-hover:opacity-100 transition duration-300"></span>
                        </button>

                        <!-- Sale Return Button -->
                        <button onclick="SaleReturn()"
                            class="group relative overflow-hidden px-5 py-2.5 rounded-xl
                                bg-gradient-to-r from-rose-500 to-red-600
                                hover:from-rose-600 hover:to-red-700
                                text-white font-semibold shadow-md hover:shadow-xl
                                transition-all duration-300 active:scale-95
                                {{ Auth::user()->hasPermission('pos_sale.sell') ? '' : 'hidden' }}">

                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-arrow-rotate-left text-sm"></i>
                                Sale Return
                            </span>

                            <!-- glow -->
                            <span
                                class="absolute inset-0 bg-white/10 opacity-0
                                    group-hover:opacity-100 transition duration-300"></span>
                        </button>

                        <!-- Print Invoice Button -->
                        <button onclick="printSelectedSaleOrderInvoice()"
                            class="group relative overflow-hidden px-5 py-2.5 rounded-xl
                                bg-gradient-to-r from-slate-700 to-slate-900
                                hover:from-slate-800 hover:to-black
                                text-white font-semibold shadow-md hover:shadow-xl
                                transition-all duration-300 active:scale-95">

                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-file-invoice text-sm"></i>
                                Print Invoice
                            </span>

                            <!-- glow -->
                            <span
                                class="absolute inset-0 bg-white/10 opacity-0
                                    group-hover:opacity-100 transition duration-300"></span>
                        </button>

                        <!-- Print Delivery Note Button -->
                        <button onclick="printSelectedSaleOrderDeliveryNote()"
                            class="group relative overflow-hidden px-5 py-2.5 rounded-xl
                                bg-gradient-to-r from-teal-600 to-emerald-700
                                hover:from-teal-700 hover:to-emerald-800
                                text-white font-semibold shadow-md hover:shadow-xl
                                transition-all duration-300 active:scale-95">

                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-truck-fast text-sm"></i>
                                Print Delivery Note
                            </span>

                            <!-- glow -->
                            <span
                                class="absolute inset-0 bg-white/10 opacity-0
                                    group-hover:opacity-100 transition duration-300"></span>
                        </button>

                        <!-- Print Receipt Button -->
                        <button onclick="printSelectedSaleOrderReceipt()"
                            class="group relative overflow-hidden px-5 py-2.5 rounded-xl
                                bg-gradient-to-r from-amber-500 to-orange-600
                                hover:from-amber-600 hover:to-orange-700
                                text-white font-semibold shadow-md hover:shadow-xl
                                transition-all duration-300 active:scale-95">

                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-receipt text-sm"></i>
                                Print Receipt
                            </span>

                            <!-- glow -->
                            <span
                                class="absolute inset-0 bg-white/10 opacity-0
                                    group-hover:opacity-100 transition duration-300"></span>
                        </button>

                        <!-- Picking List -->
                        <button onclick="printSelectedSaleOrderPickingList()"
                            class="group relative overflow-hidden px-5 py-2.5 rounded-xl
                                bg-gradient-to-r from-indigo-600 to-violet-700
                                hover:from-indigo-700 hover:to-violet-800
                                text-white font-semibold shadow-md hover:shadow-xl
                                transition-all duration-300 active:scale-95">

                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-boxes-packing text-sm"></i>
                                Picking List
                            </span>

                            <!-- glow -->
                            <span
                                class="absolute inset-0 bg-white/10 opacity-0
                                    group-hover:opacity-100 transition duration-300"></span>
                        </button>

                    </div>

                    <div class="flex items-center gap-2">
                        <span id="pageInfo_sale_order" class="text-sm text-gray-500"></span>
                        <div id="paginationContainer_sale_order" class="flex gap-1"></div>
                    </div>

                </div>

            </div>
        </div>
    </div>






    {{-- <LIST Modal Print Order DATA> --}}
    <div id="default-modal-sales-order-save" tabindex="-1" aria-hidden="true"
        class="modal-overlay-sale hidden">

        <div class="relative w-full max-w-5xl">
            <div class="modal-card-sale max-h-[94vh]">

                {{-- ===== Header ===== --}}
                <div class="modal-header-sale items-start">
                        <div class="flex items-center gap-3">
                            <div class="modal-icon-badge-sale">
                                <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">
                                    រក្សាទុក ការកម្មង់អតិថិជន
                                </h3>
                                <p class="mt-0.5 text-xs text-slate-300">
                                    Save sale order · payment & delivery details
                                </p>
                            </div>
                        </div>

                        <button type="button"
                            onclick="document.getElementById('default-modal-sales-order-save').classList.add('hidden')"
                            class="modal-close-btn">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                </div>

                {{-- ===== Body ===== --}}
                <div class="flex-1 overflow-y-auto min-h-0 bg-slate-50 p-7 space-y-6">

                    {{-- ── Section: Order Info ── --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 text-sky-600 text-xs">
                                <i class="fa-solid fa-calendar-days"></i>
                            </span>
                            <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wide">
                                Order Info <span
                                    class="text-[11px] font-normal text-slate-400 normal-case">ព័ត៌មានកម្មង់</span>
                            </h4>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Posting
                                    Date</label>
                                <input type="date" id="so_document_dateInput"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                                <input type="hidden" id="so_sale_order_id">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Order
                                    Date</label>
                                <input type="date" id="so_order_dateInput"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Payment
                                    Method</label>
                                <select id="so_payment_method"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                                    <option value="ABA">ABA</option>
                                    <option value="CASH">Cash</option>
                                    <option value="CREDIT CARD">Credit Card</option>
                                    <option value="BANK TRANSFER">Bank Transfer</option>
                                    <option value="CHEQ">CHEQ</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Customer
                                    Type</label>
                                <select id="so_customer_type"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                                    <option value="Take-Away">Take Away</option>
                                    <option value="Dine-In">Dine-In</option>
                                    <option value="At-Delivery">At-Delivery</option>
                                </select>
                            </div>
                        </div>

                        {{-- ── Delivery (toggled by JS — keep .hidden) ── --}}
                        <div id="delivery_section"
                            class="hidden grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 rounded-xl border border-dashed border-indigo-200 bg-indigo-50/50 p-4">
                            <div
                                class="md:col-span-2 -mb-1 flex items-center gap-2 text-indigo-600 text-xs font-bold uppercase tracking-wide">
                                <i class="fa-solid fa-truck-fast"></i> Delivery Details
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Delivery
                                    Date</label>
                                <input type="date" id="so_delivery_dateInput"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Delivery
                                    Status</label>
                                <select id="so_delivery_status"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                                    <option value="Pending">Pending</option>
                                    <option value="Processing">Processing</option>
                                    <option value="Shipped">Shipped</option>
                                    <option value="Delivered">Delivered</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="Returned">Returned</option>
                                    <option value="N/A">N/A</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Driver
                                    Info</label>
                                <select id="so_delivery_info_status"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                                    <option value="" selected>Select Driver</option>
                                    <option value="OWN_DRIVER">Own Driver</option>
                                    <option value="NHAM24">Nham24</option>
                                    <option value="FOODPANDA">Foodpanda</option>
                                    <option value="GRAB">Grab</option>
                                    <option value="PASSAPP">PassApp</option>
                                    <option value="OTHER">Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Driver
                                    Name</label>
                                <input type="text" id="so_driver_name" placeholder="Driver Name"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Driver
                                    Phone</label>
                                <input type="text" id="so_driver_phone" placeholder="Driver Phone"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none transition">
                            </div>
                        </div>
                    </div>

                    {{-- ── Section: Payment ── --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 text-xs">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </span>
                            <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wide">
                                Payment <span
                                    class="text-[11px] font-normal text-slate-400 normal-case">ការបង់ប្រាក់</span>
                            </h4>
                        </div>

                        {{-- totals strip --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                                <label for="so_display_pay_amount"
                                    class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Total
                                    Amount</label>
                                <input type="text" id="so_display_pay_amount" disabled
                                    class="w-full bg-transparent border-0 p-0 text-lg font-bold text-slate-800 cursor-not-allowed focus:ring-0">
                            </div>

                            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                                <label for="so_display_pay_amount_converted"
                                    class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Total in
                                    Other</label>
                                <input type="text" id="so_display_pay_amount_converted" disabled
                                    class="w-full bg-transparent border-0 p-0 text-lg font-bold text-slate-800 cursor-not-allowed focus:ring-0">
                            </div>

                            <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3">
                                <label for="paid_amount"
                                    class="block text-[11px] font-semibold text-emerald-500 uppercase mb-1">Paid
                                    Amount</label>
                                <input type="text" id="paid_amount" disabled value="0"
                                    class="w-full bg-transparent border-0 p-0 text-lg font-bold text-emerald-700 cursor-not-allowed focus:ring-0">
                            </div>
                               {{-- ── invoice-level (bill) discount: flat $ ── --}}
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">
                                Bill Discount ($)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-rose-500 font-bold">$</span>
                            <input type="text" id="so_invoice_discount" placeholder="0.00" inputmode="decimal"
                                    autocomplete="off" value="0" oninput="validateSaleOrderPayment(event)"
                                    {{ Auth::user()->hasPermission('pos_sale.edit_discount') ? '' : 'readonly title="You do not have permission to edit the discount."' }}
                                    class="w-full rounded-xl border border-slate-300 bg-white pl-8 pr-3.5 py-2.5 text-sm font-semibold shadow-sm
                                       focus:border-rose-400 focus:ring-2 focus:ring-rose-100 outline-none transition {{ Auth::user()->hasPermission('pos_sale.edit_discount') ? '' : 'opacity-50 cursor-not-allowed' }}">
                            </div>
                        </div>
                        </div>

                        {{-- pay inputs --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Pay as
                                    Dollar</label>
                                <div class="relative">
                                    <span
                                        class="absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-500 font-bold">$</span>
                                    <input type="text" id="so_pay_usd" placeholder="0.00" inputmode="decimal"
                                        autocomplete="off" oninput="validateSaleOrderPayment(event)"
                                        class="w-full rounded-xl border border-slate-300 bg-white pl-8 pr-3.5 py-2.5 text-sm font-semibold shadow-sm
                                           focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">
                                    Pay as <span id="so_currency_display_name">៛</span>
                                </label>
                                <input type="text" id="so_pay_other" placeholder="0 ៛" inputmode="decimal"
                                    autocomplete="off" oninput="validateSaleOrderPayment(event)"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold shadow-sm
                                       focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                            </div>
                        </div>

                        {{-- remaining / return --}}
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                class="flex items-center justify-between rounded-xl bg-rose-50 border border-rose-100 px-4 py-2.5">
                                <span class="text-xs font-semibold text-rose-400 uppercase">Remaining</span>
                                <span class="text-sm">
                                    <span id="so_need_more_usd" class="font-bold text-red-500">0.00 $</span>
                                    <span class="text-slate-300 mx-1">/</span>
                                    <span id="so_need_more_other" class="font-bold text-blue-500">0 ៛</span>
                                </span>
                            </div>

                            <div
                                class="flex items-center justify-between rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-2.5">
                                <span class="text-xs font-semibold text-emerald-400 uppercase">Return</span>
                                <span class="text-sm">
                                    <span id="so_return_usd" class="font-bold text-green-500">0.00 $</span>
                                    <span class="text-slate-300 mx-1">/</span>
                                    <span id="so_return_other" class="font-bold text-green-500">0 ៛</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- ── Section: Customer ── --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-600 text-xs">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wide">
                                Customer <span class="text-[11px] font-normal text-slate-400 normal-case">អតិថិជន</span>
                            </h4>
                        </div>

                        <input type="hidden" id="so_customer_id_info">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Customer
                                    Name</label>
                                <input type="text" id="so_customer_name_info" maxlength="40"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-amber-400 focus:ring-2 focus:ring-amber-100 outline-none transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Phone</label>
                                <input type="text" id="so_customer_phone_info" maxlength="40"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-amber-400 focus:ring-2 focus:ring-amber-100 outline-none transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Address</label>
                                <input type="text" id="so_customer_address_info" maxlength="40"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-amber-400 focus:ring-2 focus:ring-amber-100 outline-none transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Taxi
                                    Phone</label>
                                <input type="text" id="so_remark_invoice" maxlength="40"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm
                                       focus:border-amber-400 focus:ring-2 focus:ring-amber-100 outline-none transition">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== Footer ===== --}}
                <div
                    class="shrink-0 flex flex-wrap justify-between items-center gap-3 border-t border-slate-200 bg-white px-6 py-4">
                    <div class="flex flex-wrap gap-3 items-center"></div>

                    <div class="flex flex-wrap gap-2">
                        <div id="new_order" class="flex flex-wrap gap-2">

                            <button type="button" onclick="Confirm_Save_Sale_Order('Quotation', this)"
                                class="hidden inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-blue-700 hover:shadow-lg transition active:scale-95">
                                <i class="fa-solid fa-file-lines"></i>
                                Quotation
                            </button>

                            {{-- One-click flow: order + deduct stock + invoice, all at once —
                                 needs pos_sale.sell specifically (pos_sale.order alone isn't enough). --}}
                            <button type="button" onclick="Confirm_Save_Sale_Order('Deposit', this)"
                                class="{{ Auth::user()->hasPermission('pos_sale.sell') ? '' : 'hidden' }} inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:from-emerald-600 hover:to-green-700 hover:shadow-lg transition active:scale-95">
                                <i class="fa-solid fa-file-invoice"></i>
                                Confirm Payment
                            </button>

                            {{-- Step 1 of the 2-step flow: creates the order without
                                 touching stock — needs pos_sale.order specifically,
                                 independent of pos_sale.sell. --}}
                            <button type="button" onclick="Confirm_Save_Sale_Order('Ordered', this)"
                                class="{{ Auth::user()->hasPermission('pos_sale.order') ? '' : 'hidden' }} inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 transition active:scale-95">
                                <i class="fa-solid fa-book"></i>
                                Order
                            </button>

                            <button type="button" onclick="Confirm_Save_Sale_Order('Completed', this)"
                                class="hidden inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 transition active:scale-95">
                                <i class="fa-solid fa-dollar-sign"></i>
                                Confirm Sale
                            </button>
                        </div>

                        {{-- Step 2 (completing an order that was created without stock
                             deduction) always deducts stock — needs pos_sale.sell. --}}
                        <div id="update_order" class="flex flex-wrap gap-2">

                            <button type="button" id="buttone_update_deposit"
                                onclick="Confirm_Save_Sale_Order('Update-Deposit', this)"
                                class="{{ Auth::user()->hasPermission('pos_sale.sell') ? '' : 'hidden' }} inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:from-emerald-600 hover:to-green-700 hover:shadow-lg transition active:scale-95">
                                <i class="fa-solid fa-dollar-sign"></i>
                                Pay
                            </button>

                            {{-- Completes an Ordered (no-stock-yet) order in one step:
                                 deducts stock AND records payment. --}}
                            <button type="button" id="save_as_order" onclick="Confirm_update_Sale_Order()"
                                class="{{ Auth::user()->hasPermission('pos_sale.sell') ? '' : 'hidden' }} inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:from-emerald-600 hover:to-green-700 hover:shadow-lg transition active:scale-95">
                                <i class="fa-solid fa-dollar-sign"></i>
                                Ship &amp; Pay
                            </button>

                            {{-- Ship stock without touching payment — pay comes later via
                                 the "Pay" button once the order is in Deposit status.
                                 Only two paths exist here per business rule: Ship then
                                 Pay separately, or Ship & Pay together in one click. --}}
                            <button type="button" id="btn_ship_only" onclick="shipOnlySelectedOrder()"
                                class="{{ Auth::user()->hasPermission('pos_sale.sell') ? '' : 'hidden' }} inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:from-indigo-600 hover:to-blue-700 hover:shadow-lg transition active:scale-95">
                                <i class="fa-solid fa-truck-fast"></i>
                                Ship Only
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Sale Order Line Modal -->
    <div id="saleOrderLineModal"
        class="modal-overlay-sale hidden">
        <div class="w-full max-w-6xl max-h-[92vh] modal-card-sale">

            {{-- Header --}}
            <div class="modal-header-sale">
                <div class="flex items-center gap-3">
                    <div class="modal-icon-badge-sale">
                        <i class="fa-solid fa-file-invoice text-lg"></i>
                    </div>
                    <div>
                        <h2 id="sale-order-no" class="text-xl font-bold text-white">-</h2>
                        <p class="text-sm text-slate-300">
                            Created by <span id="sale-order-created-by">-</span> •
                            <span id="sale-order-posting-date">-</span>
                        </p>
                        <input type="hidden" id="sale_order_id">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" id="btn-toggle-sale-currency" onclick="toggleSaleOrderCurrency()"
                        class="inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 text-white px-4 py-2 text-sm font-semibold transition">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        <span id="sale-currency-toggle-label">View in ៛</span>
                    </button>

                    <button type="button" onclick="closeSaleOrderLineModal()"
                        class="modal-close-btn">
                        &times;
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto min-h-0 bg-slate-50 p-6 space-y-5">

                {{-- Status + Info card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-500 uppercase">Order</span>
                            <div id="sale-order-status"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-500 uppercase">Payment</span>
                            <div id="sale-payment-status"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-500 uppercase">Delivery</span>
                            <span id="sale-delivery-status"
                                class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700">-</span>
                        </div>
                        <div id="currency-rate-info" class="ml-auto text-sm text-slate-500"></div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6 p-5">
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs text-slate-400 uppercase font-semibold">Customer</p>
                                <p id="sale-order-customer" class="font-medium text-slate-800">-</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase font-semibold">Phone</p>
                                <p id="sale-order-phone" class="font-medium text-slate-800">-</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase font-semibold">Address</p>
                                <p id="sale-order-address" class="font-medium text-slate-800">-</p>
                            </div>
                            {{-- Taxi Phone is stored in sale_order_headers.remarks — the
                                 order form's "Taxi Phone" input (#so_remark_invoice) writes
                                 that column, so this reads header.remarks. --}}
                            <div>
                                <p class="text-xs text-slate-400 uppercase font-semibold">Taxi Phone</p>
                                <p id="sale-order-taxi-phone" class="font-medium text-slate-800">-</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <p class="text-xs text-slate-400 uppercase font-semibold">Payment Method</p>
                                <p id="sale-order-payment-method" class="font-medium text-slate-800">-</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase font-semibold">Delivery Date</p>
                                <p id="sale-order-delivery-date" class="font-medium text-slate-800">-</p>
                            </div>
                            <div id="sale-order-delivery-box" class="space-y-4">
                                <div>
                                    <p class="text-xs text-slate-400 uppercase font-semibold">Delivery Info</p>
                                    <p id="sale-order-delivery-info" class="font-medium text-slate-800">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 uppercase font-semibold">Driver Name</p>
                                    <p id="sale-order-driver-name" class="font-medium text-slate-800">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 uppercase font-semibold">Driver Phone</p>
                                    <p id="sale-order-driver-phone" class="font-medium text-slate-800">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Line Table Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-4">
                        <h3 class="flex items-center gap-2 font-bold text-slate-800">
                            <i class="fa-solid fa-boxes-stacked text-slate-400"></i>
                            Sale Order Lines
                        </h3>
                        <span class="text-sm text-slate-500">Items list</span>
                    </div>

                    <div class="overflow-x-auto">
                        {{-- min-width keeps the 10 columns legible and lets the wrapper
                             scroll sideways on a phone; with only w-full the cells were
                             crushed to min-content and headers wrapped mid-word. --}}
                        <table class="w-full min-w-[900px] text-sm">
                            <thead class="bg-slate-100 text-slate-600 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">#</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('Item Code') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('Item Name') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('Qty Shipped') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('Price') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('Sub Total') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('Discount') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('VAT') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('Grand Total') }}</th>
                                </tr>
                            </thead>

                            <tbody id="sale-line-data" class="divide-y divide-slate-100">
                                <!-- JS append rows here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Totals --}}
                <div class="flex justify-end">
                    <div class="w-full space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:w-[420px]">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Total Amount</span>
                            <span id="sale-order-total" class="font-semibold text-slate-800">$0.00</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Discount</span>
                            <span id="sale-order-discount" class="font-semibold text-slate-800">$0.00</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">VAT</span>
                            <span id="sale-order-vat" class="font-semibold text-slate-800">$0.00</span>
                        </div>
                        <hr>
                        <div class="flex justify-between text-xl font-bold">
                            <span class="text-slate-800">Grand Total</span>
                            <span id="sale-order-grand-total" class="text-blue-600">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-between gap-3 border-t border-slate-200 bg-white px-6 py-4 shrink-0">
                <div class="relative">
                    <button type="button" id="btn-print-invoice" onclick="togglePrintMenu('sale-order-print-menu', this)"
                        class="inline-flex items-center gap-2 rounded-xl bg-purple-500 hover:bg-purple-600 text-white font-medium px-4 py-2 shadow-md transition">
                        <i class="fa-solid fa-print"></i>
                        Print
                        <i class="fa-solid fa-chevron-up text-xs"></i>
                    </button>

                    <div id="sale-order-print-menu"
                        class="hidden fixed w-56 rounded-xl border border-slate-200 bg-white shadow-xl overflow-hidden z-[9999]">
                        <button type="button" onclick="closePrintMenus(); printSaleOrderDataAs('invoice')"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100 transition">
                            <i class="fa-solid fa-file-invoice text-slate-400 w-4"></i> Invoice Form
                        </button>
                        <button type="button" onclick="closePrintMenus(); printSaleOrderDataAs('delivery')"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100 transition">
                            <i class="fa-solid fa-truck-fast text-slate-400 w-4"></i> Delivery Note
                        </button>
                        <button type="button" onclick="closePrintMenus(); printSaleOrderDataAs('receipt')"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100 transition">
                            <i class="fa-solid fa-receipt text-slate-400 w-4"></i> Receipt
                        </button>
                        <button type="button" onclick="closePrintMenus(); printSaleOrderDataAs('table')"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100 transition border-t border-slate-100">
                            <i class="fa-solid fa-table text-slate-400 w-4"></i> Entire Table
                        </button>
                    </div>
                </div>

                {{-- Label updates per-order (Ship / Pay Only / Ship & Pay) — see
                     updateOrderDetailActionLabel() in script.js. Always loads the
                     order into the cart either way; the matching action button
                     then auto-shows there based on this exact order's state. --}}
                <button type="button" id="btn-sale-order-detail-update" onclick="Load_order()"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2 shadow-md transition">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span id="btn-sale-order-detail-update-label">Update</span>
                </button>
            </div>
        </div>
    </div>

    <div id="quotationModal"
        class="modal-overlay-sale hidden">

        <div class="w-full max-w-6xl max-h-[95vh] modal-card-sale">

            {{-- Header --}}
            <div class="modal-header-sale">
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-bold text-white">
                        <i class="fa-solid fa-file-lines"></i>
                        <span id="quotation-modal-title">Save Quotation</span>
                    </h2>
                    <p class="text-sm text-slate-300">Create or edit a quotation for a customer</p>
                </div>

                <button type="button" onclick="closeQuotationModal()"
                    class="modal-close-btn">
                    &times;
                </button>
            </div>

            {{-- Body --}}
            <div class="max-h-[80vh] space-y-4 overflow-y-auto bg-slate-50 p-4">

                {{-- Customer Info --}}
                <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-2 border-b bg-white px-4 py-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-user text-gray-400"></i>
                            <h3 class="font-bold text-gray-800">Customer Info</h3>
                        </div>
                        @if (Auth::user()->hasPermission('customer.create'))
                            <button type="button" onclick="openCustomerCreateFor('quotation')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition">
                                <i class="fa-solid fa-user-plus"></i>
                                New Customer
                            </button>
                        @endif
                    </div>

                    <div class="p-3 space-y-2 text-sm">
                        <input type="hidden" id="quotation_id" value="">
                        <input type="hidden" id="quotation-customer-id" value="">

                        <div class="relative">
                            <label class="text-xs text-gray-500">Search Customer</label>
                            <i
                                class="fa-solid fa-magnifying-glass absolute left-3 top-[30px] text-gray-400 text-xs"></i>
                            <input type="text" id="quotation-customer-search" autocomplete="off"
                                placeholder="Search by code, name, or phone..."
                                class="mt-0.5 w-full rounded-xl border-gray-300 pl-9 pr-3.5 py-1.5 text-sm shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">

                            <ul id="quotation-customer-list"
                                class="hidden absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-xl shadow-lg max-h-52 overflow-auto">
                            </ul>
                        </div>

                        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                            <div>
                                <label class="text-xs text-gray-500">Customer Name</label>
                                <input type="text" id="quotation-customer-name" placeholder="Walk-in Customer"
                                    class="mt-0.5 w-full rounded-xl border-gray-300 px-3.5 py-1.5 text-sm shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                            </div>

                            <div>
                                <label class="text-xs text-gray-500">Phone</label>
                                <input type="text" id="quotation-customer-phone" placeholder="Phone number"
                                    class="mt-0.5 w-full rounded-xl border-gray-300 px-3.5 py-1.5 text-sm shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                            </div>

                            <div>
                                <label class="text-xs text-gray-500">Address</label>
                                <input type="text" id="quotation-customer-address" placeholder="Address"
                                    class="mt-0.5 w-full rounded-xl border-gray-300 px-3.5 py-1.5 text-sm shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Remarks</label>
                                <input type="text" id="quotation-remark" placeholder="Remarks (optional)"
                                    class="mt-0.5 w-full rounded-xl border-gray-300 px-3.5 py-1.5 text-sm shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                            </div>
                        </div>
                    </div>
                </div>
                &ensp;
                {{-- Cart Items --}}
                <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b bg-white px-4 py-3">
                        <h3 class="flex items-center gap-2 font-bold text-gray-800">
                            <i class="fa-solid fa-boxes-stacked text-gray-400"></i>
                            Cart Items
                        </h3>
                        <span id="preview-rate-info" class="text-sm text-gray-500"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="Table-sale-list" class="w-full text-sm">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left">{{ __('Code') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('Item') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Price') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Discount') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('VAT') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Grand Total') }}</th>
                                </tr>
                            </thead>

                            <tbody id="preview-line-data" class="divide-y">
                            </tbody>
                        </table>
                    </div>
                </div>
                &ensp;
                {{-- Totals --}}
                <div class="flex justify-end">
                    <div class="w-full space-y-2 rounded-2xl border bg-white p-4 shadow-sm md:w-[420px]">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Total Amount</span>
                            <span id="preview-total" class="font-semibold text-gray-800">0</span>
                        </div>

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Discount</span>
                            <span id="preview-discount" class="font-semibold text-gray-800">0</span>
                        </div>

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">VAT</span>
                            <span id="preview-vat" class="font-semibold text-gray-800">0</span>
                        </div>

                        <hr>

                        <div class="flex justify-between text-xl font-bold">
                            <span class="text-gray-800">Grand Total</span>
                            <span id="preview-grand" class="text-blue-600">0</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-between gap-3 border-t bg-white px-6 py-4">
                <div class="flex">

                    <button id="btn-save-quotation" onclick="submitQuotation()"
                        class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span id="quotation-save-label">Save Quotation</span>
                    </button>
                </div>

                <button type="button" onclick="closeQuotationModal()"
                    class="rounded-xl border px-5 py-2 text-gray-600 transition hover:bg-gray-100">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- <LIST Quotation DATA> --}}
    <div id="quotationListModal" tabindex="-1" aria-hidden="true"
        class="modal-overlay-sale !p-1 hidden">

        <div class="relative w-full max-w-[98vw] mx-auto h-[98vh] flex items-center justify-center">
            <div class="modal-card-sale h-full">

            {{-- Header --}}
            <div class="modal-header-sale !py-2">
                <h3 class="text-sm font-bold text-white flex items-center gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-file-lines"></i>
                    Quotations
                    <span class="font-normal text-slate-300">· Browse, load, or cancel saved quotations</span>
                </h3>

                <button type="button" onclick="closeQuotationListModal()"
                    class="modal-close-btn shrink-0">
                    &times;
                </button>
            </div>

            {{-- Filters --}}
            <div class="bg-slate-50 border-b border-slate-200 px-3 py-2 shrink-0">
                <div class="flex flex-wrap items-center gap-2">

                    <input id="quotation_document_search" placeholder="Quotation No"
                        class="min-w-[140px] border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm
                           focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none transition">

                    <input id="quotation_search" placeholder="Customer / Phone"
                        class="min-w-[160px] border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm
                           focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none transition">

                    <select id="quotation_status"
                        class="min-w-[130px] border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm
                           focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none transition">
                        <option value="">All Status</option>
                        <option value="Quotation">Open</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>

                    <input type="date" id="quotation_from_date"
                        class="border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm
                           focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none transition">

                    <span class="text-slate-400 text-sm">to</span>

                    <input type="date" id="quotation_to_date"
                        class="border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm
                           focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none transition">

                    <button onclick="loadQuotations(1)"
                        class="inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-3 py-1.5 text-sm font-semibold shadow-md transition">
                        <i class="fa-solid fa-magnifying-glass"></i> Search
                    </button>

                    <button onclick="clearQuotationFilters()"
                        class="inline-flex items-center gap-2 border border-slate-300 bg-white hover:bg-slate-100 text-slate-600 px-3 py-1.5 text-sm font-semibold transition">
                        <i class="fa-solid fa-rotate-left"></i> Clear
                    </button>

                </div>
            </div>

            {{-- Table --}}
            <div class="flex-1 overflow-y-auto min-h-0 bg-slate-50 p-6">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table id="Table-quotation" class="w-full text-sm">
                            <thead class="bg-slate-100 text-slate-600 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">#</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('Quotation No') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold">{{ __('Date') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('Customer') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('Phone') }}</th>
                                    <th class="px-4 py-3 text-right font-semibold">{{ __('Grand Total') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold">{{ __('Actions') }}</th>
                                </tr>
                            </thead>

                            <tbody id="Table-quotation-list" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-between items-center gap-3 border-t border-slate-200 bg-white px-6 py-4 shrink-0">
                <div id="quotation-pagination" class="text-sm text-slate-500"></div>

                <button type="button" onclick="closeQuotationListModal()"
                    class="rounded-xl border border-slate-300 px-5 py-2 text-slate-600 font-medium transition hover:bg-slate-100">
                    Close
                </button>
            </div>
        </div>
        </div>
    </div>

    <div id="expenseModal"
        class="modal-overlay-alert hidden">
        <div class="modal-card-alert">
            <h2 class="text-xl font-bold mb-4">Confirm Expense Payment</h2>

            <p class="mb-2">Are you sure you want to pay this expense?</p>

            <label class="block mb-2 font-medium">Select Expense Date </label>
            <input type="date" id="expenseDate" class="w-full border rounded-xl px-3 py-2 mb-4">
            &ensp;
            <div class="flex justify-end gap-2">
                <button onclick="closeExpenseModal()"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-xl transition">
                    Cancel
                </button>

                <button onclick="confirmExpensePayment()"
                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-xl transition">
                    Confirm
                </button>
            </div>
        </div>
    </div>



        {{-- Always rendered — the "Sale Return" trigger button is likewise always in the DOM
             (just visually hidden without pos_sale.sell) and calls getElementById() on this
             modal unconditionally, so removing the modal body would throw a null-reference error. --}}
        <!-- Sale Return Modal -->
        <div id="saleReturnModal"
            class="modal-overlay-sale hidden">

            <div class="w-full max-w-md modal-card-sale">

                <!-- Header -->
                <div class="modal-header-sale">
                    <div>
                        <h2 class="text-lg font-bold flex items-center gap-2 text-white">
                            <i class="fa-solid fa-arrow-rotate-left text-rose-400"></i>
                            Sale Return
                        </h2>
                        <p class="text-sm text-slate-300">Enter return information</p>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Return Date
                        </label>
                        <input type="date" id="return_date"
                            class="w-full rounded-xl border border-gray-300 px-4 py-2.5
                    focus:border-rose-500 focus:ring-2 focus:ring-rose-200 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Document No
                        </label>
                        <input type="text" id="return_document_no" placeholder="Enter invoice / sale document no"
                            class="w-full rounded-xl border border-gray-300 px-4 py-2.5
                    focus:border-rose-500 focus:ring-2 focus:ring-rose-200 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Remark
                        </label>
                        <textarea id="return_remark" rows="3" placeholder="Reason for return..."
                            class="w-full rounded-xl border border-gray-300 px-4 py-2.5 resize-none
                    focus:border-rose-500 focus:ring-2 focus:ring-rose-200 outline-none"></textarea>
                    </div>

                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 bg-gray-50 px-6 py-4">
                    <button onclick="closeSaleReturnModal()"
                        class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700
                hover:bg-gray-100 transition font-semibold">
                        Cancel
                    </button>
                    <button id="btnConfirmReturn" onclick="confirmSaleReturn(this)"
                        class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-rose-500 to-red-600
    hover:from-rose-600 hover:to-red-700 text-white font-semibold
    shadow-md hover:shadow-lg transition active:scale-95">

                        Confirm Return
                    </button>
                </div>

            </div>
        </div>
    <div id="confirm_customer_empty_modal"
        class="modal-overlay-alert z-[9999] hidden">

        <div class="modal-card-alert max-w-md">
            <h2 class="text-lg font-bold text-gray-800 mb-2">
                Customer name is empty
            </h2>

            <p class="text-gray-600 mb-6">
                Do you still want to submit this sale order without customer name?
            </p>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="cancelEmptyCustomerConfirm()"
                    class="px-4 py-2 rounded-xl bg-gray-200 hover:bg-gray-300">
                    Cancel
                </button>

                <button type="button" onclick="continueEmptyCustomerConfirm()"
                    class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white">
                    Continue
                </button>
            </div>
        </div>
    </div>
@endpush
