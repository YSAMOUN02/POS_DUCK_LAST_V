@extends('backend.master_purchasing')

@section('content')
    <div id="container" class="w-full grid grid-cols-1 m lg:grid-cols-8 gap-2 h-screen overflow-hidden">
        <div id="mainContent"
            class=" tab_control  lg:col-span-6 md:col-span-4 col-span-2  border-1 border-default border-dashed rounded-base">

            <div class=" flex justify-between  mb-2 border-b border-default  mx-5 sticky top-0 bg-blue-400 z-10">
                <div class="flex items-center px-4 py-3">
                    @csrf

                    <!-- Search group: select + input joined -->
                    <div
                        class="flex items-stretch h-10 rounded-full bg-white shadow-sm
                                border border-gray-300 overflow-hidden
                                focus-within:ring-2 focus-within:ring-brand focus-within:border-brand
                                transition-all duration-200">

                        <!-- Field select -->
                        <select id="field-select" style="font-size:9px"
                            class="h-full pl-2 pr-2 text-sm font-medium text-gray-600 bg-gray-50
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
                                style="font-size:9px"
                                class="h-full w-25 lg:w-64 pl-9 pr-3 text-sm border-0
                                       focus:ring-0 focus:outline-none placeholder:text-gray-400">
                        </div>

                    </div>
                </div>
                <style>
                    /* ===== Category tabs: subtle minimal ===== */

                    .tab-track {
                        background: rgba(255, 255, 255, 0.25);
                        /* faint frosted strip on the amber */
                        border-radius: 10px;
                        box-shadow: none;
                        scrollbar-width: thin;
                        scrollbar-color: rgba(0, 0, 0, .2) transparent;
                        scroll-behavior: smooth;
                    }

                    .tab-track::-webkit-scrollbar {
                        height: 3px;
                    }

                    .tab-track::-webkit-scrollbar-track {
                        background: transparent;
                    }

                    .tab-track::-webkit-scrollbar-thumb {
                        background: rgba(0, 0, 0, .15);
                        border-radius: 9999px;
                    }

                    .tab-track:hover::-webkit-scrollbar-thumb {
                        background: rgba(0, 0, 0, .3);
                    }

                    .tab-pill {
                        white-space: nowrap;
                        padding: 0.45rem 1rem;
                        border-radius: 7px;
                        font-size: 0.8rem;
                        font-weight: 600;
                        color: #4b5563;
                        background: transparent;
                        /* no box until you interact */
                        border: 1px solid transparent;
                        box-shadow: none;
                        transition: background 0.15s ease, color 0.15s ease;
                        cursor: pointer;
                    }

                    .tab-pill:hover {
                        background: rgba(255, 255, 255, 0.55);
                        color: #111827;
                        transform: none;
                        /* no lifting/jumping */
                    }

                    .tab-pill.tab-active {
                        color: #111827;
                        background: #ffffff;
                        /* clean white chip, no gradient */
                        border-color: rgba(0, 0, 0, 0.06);
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
                        /* whisper of depth */
                        transform: none;
                    }
                </style>

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
                    <div class="w-full grid grid-cols-5 gap-2 p-3 bg-slate-200  mb-12 pb-16">
                        Top
                    </div>
                </div>
            </div>
            <div class="overflow-auto" id="tab-content">


            </div>

        </div>


        {{-- Toggle view  --}}
        <div id="resizer" class="w-1.5 shrink-0 cursor-col-resize bg-gray-200 hover:bg-blue-400 transition-colors relative z-20">
            <div class="resizer-handle"></div>
        </div>

        {{-- no lg:col-span-2 here anymore --}}
        <div id="sidebar" class="flex flex-col max-h-full shrink-0 w-full lg:w-[380px]">
            <div id="inner-sidebar" class="sticky top-0 bg-slate-100 border-l border-default">
                <div class="overflow-y-auto bg-white w-full h-full">
                    @livewire('purchase-cart')
                </div>
            </div>
        </div>
    </div>

    <script>
        let factor = @json($factor);
        let currency_name = @json($currency_name);
        window.addEventListener("change-currency", (e) => {
            factor = Number(e.detail[0].factor);
            currency_name = e.detail[0].currency_name;

            document.querySelectorAll(".costs").forEach((element) => {
                const baseCost = parseFloat(element.getAttribute("data-base-cost")) || 0;

                element.textContent = fmtMoney(
                    baseCost,
                    factor,
                    currency_name,
                    '0'
                );
            });
        });

        function fmtMoney(base, factor, currency_name, zeroLabel = '') {
            let value = Number(base) * Number(factor);
            if (!value) return zeroLabel;

            const f = Number(factor);
            let decimal;

            if (f === 1) {
                decimal = 3;
            } else if (f >= 4000) {
                decimal = 0; // KHR
            } else if (f >= 100) {
                decimal = 3;
            } else {
                decimal = 2;
            }

            return value.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: decimal,
            }) + ' ' + currency_name;
        }


        document.addEventListener('click', function(e) {
            const card = e.target.closest('.card_style');
            if (!card) return;

            const isSuccess = card.classList.contains('card_style_success');

            // ❌ FAIL → FLOAT TEXT ONLY
            if (!isSuccess) {
                const float = document.createElement('div');
                float.className = 'no-stock-float';
                float.textContent = '🚫 No Stock';

                float.style.left = e.pageX + 'px';
                float.style.top = e.pageY + 'px';

                document.body.appendChild(float);
                setTimeout(() => float.remove(), 1000);

                return; // ⛔ STOP here (no icons)
            }

            // ✅ SUCCESS → BURST ICONS
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

        function toggleItem(button) {
            const allBodies = document.querySelectorAll('.bonus'); // all dropdowns
            const allArrows = document.querySelectorAll('.arrow'); // all arrows
            const allCards = document.querySelectorAll('.btn_sale_invoice'); // parent cards

            const body = button.nextElementSibling; // clicked dropdown
            const arrow = button.querySelector('.arrow'); // clicked arrow
            const card = button; // the parent card button itself

            // Close all other dropdowns
            allBodies.forEach(b => {
                if (b !== body) b.classList.add('hidden');
            });

            allArrows.forEach(a => {
                if (a !== arrow) a.classList.remove('rotate-180');
            });

            allCards.forEach(c => {
                if (c !== card) c.classList.remove('active-card'); // remove focus from others
            });

            // Toggle the clicked one
            body.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
            card.classList.toggle('active-card'); // toggle focus on current
        }


        const tabs = document.querySelectorAll('#category-tabs button');
        const tabContent = document.getElementById('tab-content');

        // Convert Blade categories JSON into JS object
        const productsByCategory = @json($categories);

        let topProducts = @json($top_products ?? []);
        // Helper: sort products by total_stock DESC
        function sortByStock(products) {
            return products.sort((a, b) => b.total_stock - a.total_stock);
        }

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
        async function renderCategoryProducts(category) {
            tabContent.innerHTML = '<p class="p-4">Loading...</p>';
            document.body.style.cursor = 'wait';

            try {
                let products = [];
               if (category === 'top') {
                        products = sortByStock(Object.values(productsByCategory).flat()).slice(0, 30);
                    } else if (category === 'topsale') {
                        products = topProducts;                         // keep server sales order — no re-sort
                    } else {
                        products = sortByStock(productsByCategory[category] || []);
                    }

                let html =
                    '<div class="min_heigh_70 w-full product-grid p-3 bg-[#F6F5FF] mb-12 pb-16">';

                products.forEach(product => {
                    const imageSrc = product.image ?
                        `/assets/startic_img/${encodeURIComponent(product.image)}` :
                        '/assets/defult/placeholder.png';

                    // Stock color logic using percentage
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


                    // Show
                    html += `
                     <div class="card_style ${style_click} bg-neutral-primary-soft block max-w-sm border border-default shadow-xs relative">
                                <button class="add-to-cart-btn w-full flex flex-col h-full" data-product='${JSON.stringify(product)}'>

                                    <!-- IMAGE -->
                                    <div class="relative w-full">
                                        <img id="product-image${product.id}" class="object-cover w-full" loading="lazy" style="max-height:150px;min-height:150px;"
                                            src="${imageSrc}" onerror="this.onerror=null;this.src='/assets/defult/placeholder.png'" alt="${product.name}" />
                                        <i class="info fa-solid fa-circle-info absolute top-1 right-1 text-blue-500 text-sm"></i>
                                        ${product.discount_percent != 0 ? `
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <span class="absolute top-1 left-1 inline-flex items-center bg-red-500 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-sm shadow-md">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <i class="fa-solid fa-tag mr-0.5"></i>${product.discount_percent}% Off
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                </span>` : ''}
                                    </div>


                                    <!-- TEXT CONTENT -->
                                    <div class="flex flex-col justify-between p-2 mt-2 h-[130px]">
                                        <!-- h-[130px] = fixed height for bottom content, adjust as needed -->

                                        <div>

                                            <h5 class="text-sm line-clamp-2">
                                                ${product.name}
                                            </h5>
                                        </div>

                                        <div class="text-center mt-1">
                                            <small>
                                                             <i class="${stockColor} fa-solid fa-boxes-stacked"></i>
                                                                <span class="${stockColor}">
                                                                    ${
                                                                        product.total_stock > 0
                                                                    ? parseFloat(product.total_stock)
                                                                        .toFixed(6)
                                                                        .replace(/\.?0+$/, '') + ' ' + product.unit
                                                                    : 'No stock'
                                                            }
                                            </small>
                                                 ${product.track_stock ? `

                                                                                                    </span>
                                                                                                    &ensp;
                                                                                                ` : ''}

                                   <span
                                        class="costs font-semibold text-sm"
                                        data-base-cost="${Number(product.cost || 0).toFixed(6)}">
                                        ${fmtMoney(
                                            product.cost,
                                            factor,
                                            currency_name,
                                            'មិនមានតម្លៃ'
                                        )}
                                    </span>
            </div>
        </div>

    </button>
</div>

            `;
                });

                html += '</div>';
                tabContent.innerHTML = html;

                // Initialize buttons (if you have any JS logic for add-to-cart)
                initAddToCartButtons();

            } catch (err) {
                tabContent.innerHTML = '<p class="p-4 text-red-500">Failed to load products.</p>';
                console.error(err);
            } finally {
                document.body.style.cursor = 'default';
            }
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

        // Exposed because this function lives inside this handler, not at script
        // top level, so script_purchase.js cannot see it. It calls this after a
        // GRN posts so the cards show the stock that was just received.
        window.refreshPurchaseProducts = resetToActiveTab;

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

        async function doSearch() {
            const query = searchInput_product.value.trim();
            const field = fieldSelect.value || 'name';

            if (!query) {
                resetToActiveTab();
                return;
            }

            try {
                tabContent.innerHTML = `
            <div class="min_heigh_70 w-full product-grid p-3 bg-[#F6F5FF] mb-12 pb-16">
                <div class="col-span-full text-center">Loading...</div>
            </div>
        `;

                const response = await fetch('/purchase/products/search', {
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

                // 🔥 BARCODE MODE: exact single match → add to cart directly
                if (field === 'bar_code' && products.length === 1) {
                    Livewire.dispatch('add-product', JSON.stringify(products[0]));

                    searchInput_product.value = '';
                    resetToActiveTab();
                    searchInput_product.focus();
                    return;
                }

                tabContent.innerHTML = `
            <div class="min_heigh_70 w-full grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-2 p-3 bg-[#F6F5FF] mb-12 pb-16">
                ${
                    products.length
                        ? products.map(p => renderProductCard(p)).join('')
                        : `<div class="col-span-full text-center text-gray-500">No products found</div>`
                }
            </div>
        `;

            } catch (err) {
                console.error(err);
                tabContent.innerHTML = `
            <div class="col-span-full p-4 text-red-500">Search failed.</div>
        `;
            }
        }


        function renderProductCard(product) {

            const imageSrc = product.image ?
                `/assets/startic_img/${encodeURIComponent(product.image)}` :
                '/assets/defult/placeholder.png';



            // Stock color logic using percentage
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
            console.log(product);

            // Search
            return `
                     <div class="card_style ${style_click} bg-neutral-primary-soft block max-w-sm border border-default shadow-xs relative">
                                <button class="add-to-cart-btn w-full flex flex-col h-full" data-product='${JSON.stringify(product)}'>

                                      <!-- IMAGE -->
                                    <div class="relative w-full">
                                        <img id="product-image${product.id}" class="object-cover w-full" loading="lazy" style="max-height:150px;min-height:150px;"
                                            src="${imageSrc}" onerror="this.onerror=null;this.src='/assets/defult/placeholder.png'" alt="${product.name}" />
                                        <i class="info fa-solid fa-circle-info absolute top-1 right-1 text-blue-500 text-sm"></i>
                                        ${product.discount_percent != 0 ? `
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <span class="absolute top-1 left-1 inline-flex items-center bg-red-500 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-sm shadow-md">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <i class="fa-solid fa-tag mr-0.5"></i>${product.discount_percent}% Off
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                </span>` : ''}
                                    </div>

                                    <!-- TEXT CONTENT -->
                                    <div class="flex flex-col justify-between p-2 mt-2 h-[130px]">
                                        <!-- h-[130px] = fixed height for bottom content, adjust as needed -->

                                        <div>

                                            <h5 class="text-sm line-clamp-2">
                                                ${product.name}
                                            </h5>
                                        </div>

                                        <div class="text-center mt-1">

                                            <p class="text-xs">

                                          ${product.track_stock ? `
                                                                                                                    <i class="${stockColor} fa-solid fa-boxes-stacked"></i>
                                                                                                                    <span class="${stockColor}">
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


                                             <span
                                        class="costs font-semibold text-sm"
                                        data-base-cost="${Number(product.cost || 0).toFixed(6)}">
                                        ${fmtMoney(
                                            product.cost,
                                            factor,
                                            currency_name,
                                            'មិនមានតម្លៃ'
                                        )}
                                    </span>

                                        </p>
            </div>
        </div>

    </button>
</div>

            `;
        }








        function initAddToCartButtons() {

            document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
                btn.removeEventListener('click', btn._addToCartListener); // remove old listener if exists
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




        const input = document.getElementById("vendorSearch");
        const list = document.getElementById("vendorList");
        const hiddenInput = document.getElementById("vendorValue");

        input.addEventListener("input", async () => {
            const value = input.value.trim();

            if (value.length === 0) {
                list.classList.add("hidden");
                list.innerHTML = '';
                hiddenInput.value = '';
                return;
            }

            try {
                const res = await fetch(`{{ route('vendor.search') }}`, {
                    method: 'POST',
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                        "Accept": "application/json",
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        q: value
                    })
                });

                const data = await res.json();

                list.innerHTML = '';
                list.classList.remove("hidden");

                if (!Array.isArray(data) || data.length === 0) {
                    list.innerHTML = `<li class="px-3 py-2 text-sm text-gray-500">No results found</li>`;
                    return;
                }

                data.forEach(vendor => {
                    const li = document.createElement("li");
                    li.textContent = `${vendor.code} - ${vendor.name}`;
                    li.className = "px-3 py-2 cursor-pointer hover:bg-gray-100 text-sm";

                    li.addEventListener("click", () => {
                        input.value = `${vendor.code} - ${vendor.name}`;
                        hiddenInput.value = vendor.id;
                        list.classList.add("hidden");
                        hiddenInput.dispatchEvent(new Event("input"));
                    });

                    list.appendChild(li);
                });

            } catch (error) {
                console.error(error);
                list.innerHTML = `<li class="px-3 py-2 text-sm text-red-500">Error loading vendors</li>`;
                list.classList.remove("hidden");
            }
        });
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


    {{-- <LIST VENDOR> --}}
    <div id="default-modal-vendor-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-purchase items-start md:items-center !p-1 hidden">

        <div class="relative w-full max-w-[98vw]">

            <div class="h-[98vh] min-h-[98vh] max-h-[98vh] modal-card-purchase">

                {{-- Header --}}
                <div class="modal-header-purchase">
                        <div class="flex items-center gap-3">
                            <div class="modal-icon-badge-purchase">
                                <i class="fa-solid fa-truck-field text-lg"></i>
                            </div>

                            <div>
                                <h3 class="text-lg font-bold text-white">
                                    Vendor Information
                                    <span class="text-sm font-normal text-sky-200">
                                        ព័ត៌មានអ្នកផ្គត់ផ្គង់
                                    </span>
                                </h3>

                                <p class="text-xs text-sky-200 mt-1">
                                    Supplier list, contact, phone, email and status
                                    <span id="vendorPageInfo" class="ml-2 text-xs text-sky-300 whitespace-nowrap"></span>
                                </p>
                            </div>
                        </div>

                        <button type="button"
                            class="modal-close-btn"
                            data-modal-hide="default-modal-vendor-list">
                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18 17.94 6M18 18 6.06 6" />
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                {{-- Filters --}}
                <div
                    class="flex flex-wrap items-center gap-3 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 px-6 pb-5 shrink-0">

                        <label for="vendorSearchCheckbox"
                            class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-sm font-medium text-white">
                            <input type="checkbox" id="vendorSearchCheckbox" checked onchange="loadVendors(1)"
                                class="w-4 h-4 border border-white/30 rounded-sm">
                            <span>
                                Active
                                <span class="text-xs text-slate-300">សកម្ម</span>
                            </span>
                        </label>

                        <div class="relative w-full md:w-96">
                            <i
                                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                            <input type="text" id="vendorSearchInput" placeholder="Search code, name, phone, email..."
                                oninput="handleVendorSearchInput()"
                                class="w-full pl-11 pr-4 py-2.5 border border-white/10 rounded-2xl text-sm bg-white/10 text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-300">
                        </div>

                    </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto min-h-0 bg-slate-50 p-4">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="scroll_content_70 overflow-x-auto">

                            <table id="vendor-list" class="min-w-full text-sm text-left border-collapse">
                                <thead class="sticky top-0 z-20 bg-slate-800 text-xs uppercase text-white shadow">
                                    <tr class="text-nowrap">
                                        <th class="px-4 py-3 border border-slate-700 text-center">
                                            Select<br>
                                            <span
                                                class="text-[10px] text-slate-300 font-normal normal-case">ជ្រើសរើស</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            ID<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">លេខ</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Code<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">កូដ</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Name<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">ឈ្មោះ</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Contact Person<br>
                                            <span
                                                class="text-[10px] text-slate-300 font-normal normal-case">អ្នកទំនាក់ទំនង</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Phone 1<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">ទូរស័ព្ទ
                                                ១</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Phone 2<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">ទូរស័ព្ទ
                                                ២</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Email<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">អ៊ីមែល</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Country<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">ប្រទេស</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            City<br>
                                            <span class="text-[10px] text-slate-300 font-normal normal-case">ទីក្រុង</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700">
                                            Website<br>
                                            <span
                                                class="text-[10px] text-slate-300 font-normal normal-case">គេហទំព័រ</span>
                                        </th>
                                        <th class="px-4 py-3 border border-slate-700 text-center">
                                            Status<br>
                                            <span
                                                class="text-[10px] text-slate-300 font-normal normal-case">ស្ថានភាព</span>
                                        </th>
                                    </tr>
                                </thead>

                                <tbody id="vendor-table-body" class="divide-y divide-slate-100 bg-white">
                                    <!-- async rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-4">

                    <div class="flex items-center gap-2">
                        <button type="button" id="btnEditvendor" data-modal-target="default-modal-edit-vendor"
                            data-modal-toggle="default-modal-edit-vendor"
                            class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-700 transition {{ Auth::user()->hasPermission('vendor.edit') ? '' : 'hidden' }}">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Edit
                            <span class="text-xs text-blue-100">កែប្រែ</span>
                        </button>

                        <button type="button" data-modal-target="default-modal-vendor"
                            data-modal-toggle="default-modal-vendor"
                            class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700 transition {{ Auth::user()->hasPermission('vendor.create') ? '' : 'hidden' }}">
                            <i class="fa-solid fa-plus"></i>
                            New Vendor
                            <span class="text-xs text-emerald-100">បង្កើតថ្មី</span>
                        </button>
                    </div>

                    <div class="flex items-center justify-between relative z-50">
                        <div id="vendorPaginationContainer"
                            class="flex items-center justify-center gap-2 mx-2 pointer-events-auto">
                            <!-- JS buttons -->
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
    {{-- <ADD Vendor> --}}
    <div id="default-modal-vendor" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-purchase overflow-y-auto hidden">

        <div class="relative w-full max-w-4xl modal-card-purchase">

            <form id="AddVendorForm">
                    @csrf

                    {{-- Header --}}
                    <div class="modal-header-purchase items-start">
                            <div class="flex items-center gap-3">
                                <div class="modal-icon-badge-purchase">
                                    <i class="fa-solid fa-truck-field text-lg"></i>
                                </div>

                                <div>
                                    <h3 class="text-xl font-bold text-white">
                                        Add Vendor
                                        <span class="text-sm font-normal text-sky-200">បន្ថែមអ្នកផ្គត់ផ្គង់</span>
                                    </h3>
                                    <p class="mt-1 text-xs text-sky-200">
                                        Create supplier information, contact and address
                                    </p>
                                </div>
                            </div>

                            <button type="button"
                                class="modal-close-btn"
                                data-modal-hide="default-modal-vendor">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                    </div>

                    {{-- Body --}}
                    <div class="max-h-[72vh] overflow-y-auto bg-slate-50 p-6">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="grid gap-5 md:grid-cols-2">


                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Name <span class="text-slate-400">ឈ្មោះ</span>
                                        <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" name="name" placeholder="Vendor Name" required
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Contact Person <span class="text-slate-400">អ្នកទំនាក់ទំនង</span>
                                    </label>
                                    <input type="text" name="contact_person" placeholder="John Doe"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Email <span class="text-slate-400">អ៊ីមែល</span>
                                    </label>
                                    <input type="email" name="email" placeholder="test@mail.com"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Phone 1 <span class="text-slate-400">ទូរស័ព្ទ ១</span>
                                    </label>
                                    <input type="text" name="phone1"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Phone 2 <span class="text-slate-400">ទូរស័ព្ទ ២</span>
                                    </label>
                                    <input type="text" name="phone2"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Country <span class="text-slate-400">ប្រទេស</span>
                                    </label>
                                    <input type="text" name="country" value="Cambodia"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        City <span class="text-slate-400">ទីក្រុង</span>
                                    </label>
                                    <input type="text" name="city" value="Phnom Penh"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Website <span class="text-slate-400">គេហទំព័រ</span>
                                    </label>
                                    <input type="text" name="website" placeholder="https://example.com"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Address 1 <span class="text-slate-400">អាសយដ្ឋាន ១</span>
                                    </label>
                                    <textarea name="address1" rows="2"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300"></textarea>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Address 2 <span class="text-slate-400">អាសយដ្ឋាន ២</span>
                                    </label>
                                    <textarea name="address2" rows="2"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300"></textarea>
                                </div>

                                <div class="md:col-span-2">
                                    <label
                                        class="inline-flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                        <input type="checkbox" name="status" checked value="1"
                                            class="w-4 h-4 rounded border-emerald-400 focus:ring-emerald-400">
                                        <span class="text-sm font-semibold text-emerald-700">
                                            Active Vendor

                                        </span>
                                    </label>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                        <button type="button" data-modal-hide="default-modal-vendor"
                            class="rounded-2xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Cancel
                            <span class="text-xs text-slate-400">បោះបង់</span>
                        </button>

                        <button type="button" onclick="addVendor()"
                            class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Vendor
                            <span class="text-xs text-emerald-100">រក្សាទុក</span>
                        </button>
                    </div>
                </form>

        </div>
    </div>


    {{-- <EDIT Vendor> --}}
    <div id="default-modal-edit-vendor" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-purchase overflow-y-auto hidden">

        <div class="relative w-full max-w-4xl modal-card-purchase">

            <form id="EditVendorForm">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="vendor_id" id="edit_vendor_id">

                    {{-- Header --}}
                    <div class="modal-header-purchase items-start">
                            <div class="flex items-center gap-3">
                                <div class="modal-icon-badge-purchase">
                                    <i class="fa-solid fa-truck-field text-lg"></i>
                                </div>

                                <div>
                                    <h3 class="text-xl font-bold text-white">
                                        Update Vendor
                                        <span class="text-sm font-normal text-sky-200">កែប្រែអ្នកផ្គត់ផ្គង់</span>
                                    </h3>
                                    <p class="mt-1 text-xs text-sky-200">
                                        Edit supplier information, contact and address
                                    </p>
                                </div>
                            </div>

                            <button type="button"
                                class="modal-close-btn"
                                data-modal-hide="default-modal-edit-vendor">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                    </div>

                    {{-- Body --}}
                    <div class="max-h-[72vh] overflow-y-auto bg-slate-50 p-6">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="grid gap-5 md:grid-cols-2">

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Vendor Code <span class="text-slate-400">កូដអ្នកផ្គត់ផ្គង់</span>
                                        <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" name="code" id="edit_code" placeholder="V0001" required
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Name <span class="text-slate-400">ឈ្មោះ</span>
                                        <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" name="name" id="edit_name" placeholder="Vendor Name"
                                        required
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Contact Person <span class="text-slate-400">អ្នកទំនាក់ទំនង</span>
                                    </label>
                                    <input type="text" name="contact_person" id="edit_contact_person"
                                        placeholder="John Doe"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Email <span class="text-slate-400">អ៊ីមែល</span>
                                    </label>
                                    <input type="email" name="email" id="edit_email" placeholder="example@mail.com"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Phone 1 <span class="text-slate-400">ទូរស័ព្ទ ១</span>
                                    </label>
                                    <input type="text" name="phone1" id="edit_phone1"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Phone 2 <span class="text-slate-400">ទូរស័ព្ទ ២</span>
                                    </label>
                                    <input type="text" name="phone2" id="edit_phone2"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Country <span class="text-slate-400">ប្រទេស</span>
                                    </label>
                                    <input type="text" name="country" id="edit_country" placeholder="Cambodia"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        City <span class="text-slate-400">ទីក្រុង</span>
                                    </label>
                                    <input type="text" name="city" id="edit_city" placeholder="Phnom Penh"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Website <span class="text-slate-400">គេហទំព័រ</span>
                                    </label>
                                    <input type="text" name="website" id="edit_website"
                                        placeholder="https://example.com"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Address 1 <span class="text-slate-400">អាសយដ្ឋាន ១</span>
                                    </label>
                                    <textarea name="address1" id="edit_address1" rows="2"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300"></textarea>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-semibold text-slate-700">
                                        Address 2 <span class="text-slate-400">អាសយដ្ឋាន ២</span>
                                    </label>
                                    <textarea name="address2" id="edit_address2" rows="2"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-300"></textarea>
                                </div>

                                <div class="md:col-span-2">
                                    <label
                                        class="inline-flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                        <input type="checkbox" name="status" id="edit_status" value="1"
                                            class="w-4 h-4 rounded border-emerald-400 focus:ring-emerald-400">
                                        <span class="text-sm font-semibold text-emerald-700">
                                            Active Vendor

                                        </span>
                                    </label>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                        <button type="button" data-modal-hide="default-modal-edit-vendor"
                            class="rounded-2xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Cancel
                            <span class="text-xs text-slate-400">បោះបង់</span>
                        </button>

                        <button type="button" onclick="updateVendor()"
                            class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-700">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Update Vendor
                            <span class="text-xs text-blue-100">កែប្រែ</span>
                        </button>
                    </div>
                </form>

        </div>
    </div>





    {{-- <LIST Purchase DATA> --}}


    <div id="default-modal-purchase-list" tabindex="-1" aria-hidden="true" data-modal-backdrop="static"
        class="modal-overlay-purchase !p-1 hidden">

        <div class="mx-auto flex min-h-full w-full max-w-[98vw] items-center justify-center">
            <div class="relative flex h-[98vh] min-h-[98vh] max-h-[98vh] w-full modal-card-purchase">

                {{-- ===================== HEADER + FILTERS ===================== --}}
                <div class="modal-header-purchase flex-col items-stretch gap-2 !py-2">

                    {{-- title row --}}
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-sm font-bold leading-tight text-white flex items-center gap-2 whitespace-nowrap">
                            <i class="fa-solid fa-cart-flatbed"></i>
                            របាយការណ៍ ការទិញ
                            <span class="font-normal text-sky-200">· Each row is one purchase line</span>
                        </h3>
                        <button type="button" data-modal-hide="default-modal-purchase-list"
                            class="modal-close-btn shrink-0">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    {{-- filters: one dense responsive grid, uniform height --}}
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                        {{-- date from --}}
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10 text-[9px] font-semibold text-slate-500">
                                FROM
                            </span>
                            <input type="date" id="from_date" class="ss-f w-full !pl-14 !pr-3">
                        </div>

                        {{-- date to --}}
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10 text-[9px] font-semibold text-slate-500">
                                TO
                            </span>
                            <input type="date" id="to_date" class="ss-f w-full !pl-10 !pr-3">
                        </div>

                        {{-- document no --}}
                        <div class="relative">
                            <i
                                class="fa-solid fa-file-invoice pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10 w-4 text-center text-xs text-slate-500"></i>
                            <input type="text" id="doc_filter" placeholder="Document No" autocomplete="off"
                                class="ss-f w-full !pl-10 !pr-3">
                        </div>

                        {{-- vendor --}}
                        <div class="relative">
                            <i
                                class="fa-solid fa-truck-field pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10 w-4 text-center text-xs text-slate-500"></i>
                            <input type="text" id="vendor_search" placeholder="Vendor" autocomplete="off"
                                class="ss-f w-full !pl-10 !pr-3">
                        </div>

                        {{-- item --}}
                        <div class="relative">
                            <i
                                class="fa-solid fa-box pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10 w-4 text-center text-xs text-slate-500"></i>
                            <input type="text" id="product_search" placeholder="Item / code / barcode"
                                autocomplete="off" class="ss-f w-full !pl-10 !pr-3">
                        </div>

                        {{-- variant --}}
                        <div class="relative">
                            <i
                                class="fa-solid fa-layer-group pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10 w-4 text-center text-xs text-slate-500"></i>
                            <input type="text" id="variant_filter" placeholder="Variant" autocomplete="off"
                                class="ss-f w-full !pl-10 !pr-3">
                        </div>

                        {{-- lot --}}
                        <div class="relative">
                            <i
                                class="fa-solid fa-tag pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10 w-4 text-center text-xs text-slate-500"></i>
                            <input type="text" id="lot_filter" placeholder="Lot" autocomplete="off"
                                class="ss-f w-full !pl-10 !pr-3">
                        </div>
                        {{-- category --}}
                        <select id="category_filter" class="ss-f w-full">
                            <option value="">All Categories</option>
                        </select>

                        {{-- returns toggle --}}
                        <label class="ss-f flex cursor-pointer items-center gap-2">
                            <input type="checkbox" id="returns_only"
                                class="h-4 w-4 rounded border-slate-600 bg-slate-700 text-rose-500 focus:ring-rose-400">
                            <span class="flex items-center gap-1 text-rose-300"><i
                                    class="fa-solid fa-rotate-left text-[11px]"></i> Returns</span>
                        </label>
                        {{-- limit --}}
                        <select id="limit_filter" class="ss-f w-full">
                            <option value="100">100 Rows</option>
                            <option value="200">200 Rows</option>
                            <option value="300">300 Rows</option>
                        </select>
                    </div>
                </div>

                {{-- ===================== TABLE ===================== --}}
                <div class="flex-1 overflow-auto bg-slate-50">
                    <table id="Table-Purchase-list" class="min-w-full border-collapse text-sm">
                        <thead
                            class="sticky top-0 z-20 bg-slate-800 text-[11px] font-semibold uppercase tracking-wide text-slate-200">
                            <tr>
                                <th class="px-3 py-2.5 text-center">{{ __('No') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Date') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Document') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Vendor') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Item Code') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Item') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Variant') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Lot') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Expire') }}</th>
                                <th class="px-3 py-2.5 text-right">{{ __('Qty') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Unit') }}</th>
                                <th class="px-3 py-2.5 text-right">{{ __('Unit Cost') }}</th>
                                <th class="px-3 py-2.5 text-right">{{ __('Line Total') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Category') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('Remark') }}</th>
                                <th class="px-3 py-2.5 text-left">{{ __('By') }}</th>
                                <th class="px-3 py-2.5 text-center">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseTableBody" class="text-slate-700">
                            <tr>
                                <td colspan="17" class="px-4 py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- ===================== FOOTER ===================== --}}
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <div id="paginationContainer_purchase" class="flex flex-wrap items-center gap-1"></div>
                        <span id="pageInfo_purchase"
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600"></span>
                    </div>
                    <button type="button" id="downloadPurchase"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        <i class="fa-regular fa-file-excel"></i> Export Excel
                    </button>
                </div>

            </div>
        </div>


    </div>
    <!-- Purchase Line Modal -->
    <div id="purchaseLineModal" class="modal-overlay-purchase hidden">
        <div class="w-11/12 max-w-6xl max-h-[92vh] modal-card-purchase">

            <div class="modal-header-purchase">
                <h2 class="text-xl font-bold text-white">{{ __('Purchase Details') }}</h2>

                <div class="flex items-center gap-3">
                    {{-- Mirrors the sale order detail's toggle. Hidden by
                         renderPurchaseLineModal() when the document was saved in
                         dollars, since there is no second currency to show. --}}
                    {{-- Hidden via an inline style, not the `hidden` class: both
                         it and `inline-flex` set display, and which wins depends
                         on Tailwind's output order rather than the attribute. --}}
                    <button type="button" id="btn-toggle-purchase-currency" onclick="togglePurchaseCurrency()"
                        style="display:none"
                        class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        <span id="purchase-currency-toggle-label">View in ៛</span>
                    </button>

                    <button type="button" onclick="closePurchaseLineModal()" class="modal-close-btn">
                        &times;
                    </button>
                </div>
            </div>

            <div class="p-6 overflow-y-auto max-h-[75vh] space-y-5">

                {{-- Summary card: document identity on the left, the facts that
                     distinguish one receipt from another on the right. --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div
                        class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 bg-slate-50 px-6 py-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </span>
                                <h3 id="purchase-no" class="text-xl font-bold text-slate-800 truncate">-</h3>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('Created by') }} <span id="purchase-created-by"
                                    class="font-medium text-slate-700">-</span>
                                <span class="mx-1 text-slate-300">•</span>
                                <span id="purchase-posting-date">-</span>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">
                                <i class="fa-solid fa-warehouse"></i>
                                <span id="purchase-warehouse">-</span>
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800">
                                <i class="fa-solid fa-money-bill-transfer"></i>
                                <span id="purchase-currency">-</span>
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-x-6 gap-y-4 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Vendor') }}
                            </p>
                            <p id="purchase-vendor" class="mt-0.5 font-medium text-slate-800 break-words">-</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Vendor ID') }}
                            </p>
                            <p id="purchase-vendor-id" class="mt-0.5 font-medium text-slate-800">-</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {{ __('Payment Method') }}</p>
                            <p id="purchase-payment-method" class="mt-0.5 font-medium text-slate-800">-</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Remark') }}
                            </p>
                            <p id="purchase-remark" class="mt-0.5 font-medium text-slate-800 break-words">-</p>
                        </div>
                    </div>
                </div>
                <div class="border rounded-xl overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-gray-800">Purchase Lines</h3>
                        <span class="text-sm text-gray-500">Items list</span>
                    </div>

                    <div class="overflow-x-auto p-2">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-100 text-gray-700">
                                <tr class="text-nowrap">
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">{{ __('Item Code') }}</th>
                                    <th class="px-4 py-3">{{ __('Barcode') }}</th>
                                    <th class="px-4 py-3">{{ __('Name') }}</th>
                                    <th class="px-4 py-3">{{ __('Variant') }}</th>
                                    <th class="px-4 py-3">{{ __('Description') }}</th>
                                    <th class="px-4 py-3">{{ __('Category') }}</th>
                                    <th class="px-4 py-3">{{ __('Lot') }}</th>
                                    <th class="px-4 py-3">{{ __('Expire') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3">{{ __('Unit') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Unit Cost') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                                    <th class="px-4 py-3">{{ __('Remark') }}</th>
                                </tr>
                            </thead>

                            <tbody id="purchase-line-data" class="divide-y">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex justify-end">
                    <div class="w-full md:w-96 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="space-y-2 px-5 py-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">{{ __('Total Qty') }}</span>
                                <span id="purchase-total-qty" class="font-semibold text-slate-800">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">{{ __('Lines') }}</span>
                                <span id="purchase-total-lines" class="font-semibold text-slate-800">0</span>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-4 text-lg font-bold text-slate-800">
                            <span>{{ __('Grand Total') }}</span>
                            <span id="purchase-grand-total" class="text-emerald-700">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-between gap-3 px-6 py-4 border-t bg-gray-50">
                <button type="button" onclick="closePurchaseLineModal()"
                    class="rounded-xl border border-slate-300 px-5 py-2 font-medium text-slate-600 transition hover:bg-slate-100">
                    {{ __('Close') }}
                </button>

                {{-- Return and Print grouped on the right: Close is the way out,
                     these two act on the document. --}}
                <div class="flex flex-wrap items-center gap-3">
                    @if (Auth::user()->hasPermission('purchasing.purchase_return'))
                        <button type="button" id="btn-open-return" onclick="openReturnModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 font-medium text-white shadow-md transition hover:bg-rose-700 active:scale-95">
                            <i class="fa-solid fa-rotate-left"></i>
                            {{ __('Create Purchase Return') }}
                        </button>
                    @endif

                    {{-- Was an unstyled bare <button>, so it rendered as a plain
                         grey browser control in the middle of the footer. --}}
                    <button type="button" onclick="printPurchase()"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2 font-medium text-white shadow-md transition hover:bg-slate-900 active:scale-95">
                        <i class="fa-solid fa-print"></i>
                        {{ __('Print') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="grnModal" class="modal-overlay-alert hidden">

        <div class="modal-card-alert space-y-4">

            <h2 class="text-xl font-bold">
                Confirm Purchase
                <span class="text-sm text-gray-500">(GRN Date)</span>
            </h2>

            <p class="text-gray-600">
                Please select GRN date before posting purchase
            </p>

            <div>
                <label class="block mb-2 font-medium">
                    GRN Date
                </label>

                <input type="date" id="grnDate" class="w-full border rounded-xl px-3 py-2">
            </div>

            <div class="flex justify-end gap-3 pt-2">

                <button onclick="closeGrnModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-xl transition">
                    Cancel
                </button>

                <button onclick="confirmGrn()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl transition">
                    Confirm
                </button>

            </div>

        </div>
    </div>

    {{-- <PURCHASE PREVIEW> — the same modal the sale side shows behind its
         Preview button: vendor block, the cart line by line, totals, and a
         single Preview action. Nothing here posts a GRN or receives stock. --}}
    <div id="purchasePreviewModal" class="modal-overlay-sale hidden">

        <div class="w-full max-w-6xl max-h-[95vh] modal-card-sale">

            {{-- Header --}}
            <div class="modal-header-sale">
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-bold text-white">
                        <i class="fa-solid fa-eye"></i>
                        <span>Preview</span>
                    </h2>
                    <p class="text-sm text-slate-300">Preview this cart — nothing is posted</p>
                </div>

                <button type="button" onclick="closePurchasePreviewModal()" class="modal-close-btn">
                    &times;
                </button>
            </div>

            {{-- Body --}}
            <div class="max-h-[80vh] space-y-4 overflow-y-auto bg-slate-50 p-4">

                {{-- Vendor Info --}}
                <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div class="flex items-center gap-2 border-b bg-white px-4 py-3">
                        <i class="fa-solid fa-user-tie text-gray-400"></i>
                        <h3 class="font-bold text-gray-800">Vendor Info</h3>
                    </div>

                    <div class="grid grid-cols-2 gap-2 p-3 text-sm lg:grid-cols-4">
                        <div>
                            <label class="text-xs text-gray-500">Vendor</label>
                            <input type="text" id="purchase-preview-vendor" readonly
                                class="mt-0.5 w-full rounded-xl border-gray-300 bg-gray-50 px-3 py-1.5 text-sm shadow-sm outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Phone</label>
                            <input type="text" id="purchase-preview-phone" readonly
                                class="mt-0.5 w-full rounded-xl border-gray-300 bg-gray-50 px-3 py-1.5 text-sm shadow-sm outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Address</label>
                            <input type="text" id="purchase-preview-address" readonly
                                class="mt-0.5 w-full rounded-xl border-gray-300 bg-gray-50 px-3 py-1.5 text-sm shadow-sm outline-none">
                        </div>
                        <div>
                            {{-- Editable, like the customer fields on the sale
                                 preview: it is the date the printed document
                                 carries, and previewing is when you notice it is
                                 wrong. --}}
                            <label class="text-xs text-gray-500">GRN Date</label>
                            <input type="date" id="purchase-preview-date"
                                class="mt-0.5 w-full rounded-xl border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-100 outline-none transition">
                        </div>
                    </div>
                </div>

                {{-- Cart Items --}}
                <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-2 border-b bg-white px-4 py-3">
                        <h3 class="flex items-center gap-2 font-bold text-gray-800">
                            <i class="fa-solid fa-boxes-stacked text-gray-400"></i>
                            Cart Items
                        </h3>
                        <span id="purchase-preview-rate-info" class="text-sm text-gray-500"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left">{{ __('Item') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('Unit') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('Lot') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Unit Cost') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                                </tr>
                            </thead>

                            <tbody id="purchase-preview-lines" class="divide-y">
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
                            <span id="purchase-preview-total" class="font-semibold text-gray-800">0</span>
                        </div>

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Deposit</span>
                            <span id="purchase-preview-deposit" class="font-semibold text-gray-800">0</span>
                        </div>

                        <hr>

                        <div class="flex justify-between text-xl font-bold">
                            <span class="text-gray-800">Balance</span>
                            <span id="purchase-preview-balance" class="text-blue-600">0</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 border-t bg-white px-6 py-4">
                <button type="button" onclick="closePurchasePreviewModal()"
                    class="rounded-xl bg-gray-200 px-4 py-2 font-medium text-gray-700 transition hover:bg-gray-300">
                    Cancel
                </button>

                <button type="button" onclick="printPurchasePreview()"
                    class="flex items-center gap-2 rounded-xl bg-slate-600 px-4 py-2 font-medium text-white shadow-md transition hover:bg-slate-700">
                    <i class="fa-solid fa-eye"></i>
                    <span>{{ __('Preview') }}</span>
                </button>
            </div>
        </div>
    </div>

    {{-- <GRN POSTED — review what was received, then optionally print> --}}
    <div id="grnPostedModal" class="modal-overlay-alert z-[100] hidden">
        <div class="modal-card-alert max-w-lg animate-scaleUp">
            <div
                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <i class="fa-solid fa-check text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold mb-1 text-gray-800 text-center">GRN Posted</h2>
            <p class="text-gray-500 mb-4 text-center text-sm">
                <span id="grnPostedDocNo" class="font-semibold text-gray-700"></span>
                — stock received into the warehouse below.
            </p>

            <div class="mb-5 max-h-64 overflow-auto rounded-xl border border-gray-200 text-left">
                <table class="w-full min-w-[520px] text-xs">
                    <thead class="sticky top-0 bg-gray-50 text-gray-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">{{ __('Item') }}</th>
                            <th class="px-3 py-2 text-left font-semibold">{{ __('Variant') }}</th>
                            <th class="px-3 py-2 text-left font-semibold">{{ __('Lot') }}</th>
                            <th class="px-3 py-2 text-left font-semibold">{{ __('Expire') }}</th>
                            <th class="px-3 py-2 text-left font-semibold">{{ __('Bin') }}</th>
                        </tr>
                    </thead>
                    <tbody id="grnPostedLines" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>

            <div class="flex justify-center space-x-4">
                <button id="grnPostedSkip"
                    class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                    Skip
                </button>
                <button id="grnPostedPrint"
                    class="px-5 py-2 bg-sky-600 text-white rounded-xl hover:bg-sky-700 transition inline-flex items-center gap-2">
                    <i class="fa-solid fa-print"></i> Print GRN
                </button>
            </div>
        </div>
    </div>











    <!-- Purchase Return Modal -->
    <!-- ============================================================
                                                                    PURCHASE RETURN MODAL  ·  location-first selection
                                                                    User picks a location → only that location's returnable lots show.
                                                                    Add this block anywhere in your purchasing blade.
                                                                ============================================================ -->
    <!-- ============================================================
                                                                 PURCHASE RETURN MODAL  ·  location-first selection
                                                                 User picks a location → only that location's returnable lots show.
                                                                 Add this block anywhere in your purchasing blade.
                                                            ============================================================ -->
    <div id="purchaseReturnModal" class="modal-overlay-purchase-stacked hidden">
        <div class="w-11/12 max-w-5xl max-h-[92vh] modal-card-purchase">

            <!-- header -->
            <div class="modal-header-purchase">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-rotate-left text-rose-300"></i>
                        Create Purchase Return
                    </h2>
                    <p class="text-sm text-sky-200">
                        GRN <span id="ret-grn-no" class="font-semibold">-</span> ·
                        <span id="ret-vendor">-</span>
                    </p>
                </div>
                <button onclick="closeReturnModal()"
                    class="modal-close-btn">&times;</button>
            </div>

            <!-- controls -->
            <div class="px-6 py-4 border-b bg-white space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- LOCATION SELECTOR -->
                    <div>
                        <label class="block text-xs text-gray-500 uppercase mb-1">
                            Location <span class="text-rose-500">*</span>
                        </label>
                        <select id="ret-location" onchange="retRenderLocation()"
                            class="w-full rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-rose-300">
                            <option value="">— Select location —</option>
                        </select>
                    </div>

                    <!-- return date -->
                    <div>
                        <label class="block text-xs text-gray-500 uppercase mb-1">Return Date</label>
                        <input type="date" id="ret-date"
                            class="w-full rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-rose-300">
                    </div>

                    <!-- reason -->
                    <div>
                        <label class="block text-xs text-gray-500 uppercase mb-1">
                            Reason <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="ret-reason" placeholder="Reason for return"
                            class="w-full rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-rose-300">
                    </div>
                </div>
            </div>

            <!-- items for the chosen location -->
            <div class="p-6 overflow-y-auto flex-1 min-h-0">
                <!-- prompt shown before a location is picked -->
                <div id="ret-pick-prompt" class="text-center text-gray-400 py-12">
                    <i class="fa-solid fa-warehouse text-3xl mb-3 block"></i>
                    Select a location above to see returnable items.
                </div>

                <!-- table shown after a location is picked -->
                <div id="ret-table-wrap" class="hidden border rounded-xl overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 border-b flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">
                            Items at <span id="ret-loc-name" class="text-rose-600">-</span>
                        </span>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" id="ret-select-all" onchange="retToggleAll(this)">
                            Select all
                        </label>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-100 text-gray-700">
                                <tr class="text-nowrap">
                                    <th class="px-3 py-3 w-10"></th>
                                    <th class="px-3 py-3">{{ __('Item') }}</th>
                                    <th class="px-3 py-3">{{ __('Lot') }}</th>
                                    <th class="px-3 py-3">{{ __('Expire') }}</th>
                                    <th class="px-3 py-3 text-right">{{ __('Remaining') }}</th>
                                    <th class="px-3 py-3 text-right">{{ __('Return Qty') }}</th>
                                    <th class="px-3 py-3 text-right">{{ __('Unit Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody id="ret-line-data" class="divide-y"></tbody>
                        </table>
                    </div>
                </div>

                <!-- empty state for a location with nothing returnable -->
                <p id="ret-empty" class="hidden text-center text-gray-400 py-10"></p>
            </div>

            <!-- footer -->
            <div class="flex justify-between gap-3 px-6 py-4 border-t bg-gray-50">
                <button onclick="closeReturnModal()"
                    class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-xl shadow">Cancel</button>
                <button id="ret-confirm-btn" onclick="confirmReturn()"
                    class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-xl shadow flex items-center gap-2 disabled:opacity-50">
                    <i class="fa-solid fa-check"></i> Confirm Return
                </button>
            </div>
        </div>
    </div>
    {{-- ===== Refresh button: reload the page ===== --}}
    <script>
        (function() {
            const btn = document.getElementById('refreshBtn');
            if (!btn) return;
            const icon = document.getElementById('refresh-icon');
            btn.addEventListener('click', () => {
                icon?.classList.add('refresh-spin');
                location.reload();
            });
        })();
    </script>

    <style>
        #refresh-icon {
            cursor: pointer;
            transition: transform .2s;
        }

        #refresh-icon:hover {
            transform: rotate(90deg);
        }

        .refresh-spin {
            animation: refresh-rot 1s linear infinite;
        }

        @keyframes refresh-rot {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
@endpush
