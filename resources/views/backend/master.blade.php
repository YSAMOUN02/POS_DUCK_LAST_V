<!DOCTYPE html>
{{-- translate="no" keeps Edge/Chrome from offering to translate this page.
     Machine translation rewrites the text nodes underneath Livewire, whose
     DOM morphing then cannot match them and drops the element — which is how
     cards vanished after the browser's language bar appeared. The UI is
     already localised through app()->getLocale(), so there is nothing for a
     browser translator to usefully add. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="google" content="notranslate">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ URL('assets/css/fonts6/css/all.css') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="shortcut icon" href="{{ asset('assets/icon/download.jpg') }}" type="image/x-icon">
    <link rel="stylesheet"
        href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">


    {{-- Google Font  --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Khmer:wght@100..900&display=swap" rel="stylesheet">

    <livewire:styles />
    <script src="{{ asset('assets/js/html2canvas.min.js') }}"></script>
    <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
    <title>POS System</title>
</head>

<body>
    {{-- Not Using it but kept for reference --}}
    <!-- drawer init and toggle -->
    <div class="text-center hidden">
        <button
            class="inline-flex items-center justify-center text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none"
            type="button" data-drawer-target="drawer-swipe" data-drawer-show="drawer-swipe"
            data-drawer-placement="bottom" data-drawer-edge="true" data-drawer-edge-offset="bottom-[60px]"
            aria-controls="drawer-swipe">
            Show swipeable drawer
        </button>
    </div>






    <!-- drawer component -->
    <div id="drawer-swipe"
        class="fixed z-40 w-full overflow-y-auto bg-amber-400 border-t border-default rounded-t-base transition-transform bottom-0 left-0 right-0 translate-y-full bottom-[60px]"
        tabindex="-1" aria-labelledby="drawer-swipe-label">
        <div id="drawer-body" class="p-4 cursor-pointer hover:bg-amber-500" data-drawer-toggle="drawer-swipe">
            <span class="absolute w-8 h-1 -translate-x-1/2 bg-neutral-quaternary rounded-lg top-3 left-1/2"></span>
            <h5 id="drawer-swipe-label" class="inline-flex items-center text-base text-body font-medium">
                <svg class="w-5 h-5 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                    height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M14 17h6m-3 3v-6M4.857 4h4.286c.473 0 .857.384.857.857v4.286a.857.857 0 0 1-.857.857H4.857A.857.857 0 0 1 4 9.143V4.857C4 4.384 4.384 4 4.857 4Zm10 0h4.286c.473 0 .857.384.857.857v4.286a.857.857 0 0 1-.857.857h-4.286A.857.857 0 0 1 14 9.143V4.857c0-.473.384-.857.857-.857Zm-10 10h4.286c.473 0 .857.384.857.857v4.286a.857.857 0 0 1-.857.857H4.857A.857.857 0 0 1 4 19.143v-4.286c0-.473.384-.857.857-.857Z" />
                </svg>
                &ensp; Menu
            </h5>
        </div>

        <div id="drawer" class="grid grid-cols-3 gap-4 p-4 lg:grid-cols-8">
            <button onclick="loadReport()" class="{{ Auth::user()->hasPermission('report.profit') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Dashboard / KPI') }}
                    </div>
                </div>
            </button>
            <button id="sale_data" data-modal-target="default-modal-sale-list"
                data-modal-toggle="default-modal-sale-list"
                class="{{ Auth::user()->hasPermission('report.sales') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <svg class="w-7 h-7 inline text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 6.025A7.5 7.5 0 1 0 17.975 14H10V6.025Z" />
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.5 3c-.169 0-.334.014-.5.025V11h7.975c.011-.166.025-.331.025-.5A7.5 7.5 0 0 0 13.5 3Z" />
                        </svg>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Sales Report') }}
                    </div>
                </div>
            </button>

            <button id="expense_data" data-modal-target="default-modal-expense-list" onclick="loadExpenses(1)"
                data-modal-toggle="default-modal-expense-list"
                class="{{ Auth::user()->hasPermission('report.expense') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Expense Report') }}
                    </div>
                </div>
            </button>
            <button id="item_ledger_entry" data-modal-target="default-modal-ledger-entry-list"
                onclick="loadItemLedgerEntries(1)" data-modal-toggle="default-modal-ledger-entry-list"
                class="{{ Auth::user()->hasPermission('report.stock') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Stock In/Out Report') }}
                    </div>
                </div>
            </button>

            <div id="openWarehouseModel" data-modal-target="default-modal-warehouse"
                data-modal-toggle="default-modal-warehouse"
                class="flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium {{ Auth::user()->hasPermission('warehouse.view') ? '' : 'hidden' }}">
                <div
                    class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <div class="font-medium text-center text-body">{{ __('Manage Warehouse') }}</div>
            </div>

            <div id="openProductModal" data-modal-target="default-modal-product-list"
                data-modal-toggle="default-modal-product-list"
                class="flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium lg:block {{ Auth::user()->hasPermission('product.view') ? '' : 'hidden' }}">
                <div
                    class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div class=" font-medium text-center text-body">Manage Products</div>
            </div>

            <button onclick="openQuotationListModal()" class="{{ Auth::user()->hasPermission('quotation.view') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <svg class="w-7 h-7 inline text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6h8m-8 6h8m-8 6h8M4 16a2 2 0 1 1 3.321 1.5L4 20h5M4 5l2-1v6m-2 0h4" />
                        </svg>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Manage Quotes') }}</div>
                </div>
            </button>

            <div
                class="hidden flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium lg:block">
                <div
                    class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                    <svg class="w-7 h-7 inline text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 4h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3m0 3h6m-3 5h3m-6 0h.01M12 16h3m-6 0h.01M10 3v4h4V3h-4Z" />
                    </svg>
                </div>
                <div class="font-medium text-center text-body">{{ __('Task') }}</div>
            </div>

            <button id="openCustomerModal" data-modal-target="default-modal-customer-list"
                data-modal-toggle="default-modal-customer-list"
                class="{{ Auth::user()->hasPermission('customer.view') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-users-between-lines"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Manage Customers') }}
                    </div>
                </div>
            </button>

            <div data-modal-target="static-modal-currency-exchange" data-modal-toggle="static-modal-currency-exchange"
                class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium {{ Auth::user()->hasPermission('exchange_rate.view') ? '' : 'hidden' }}">
                <button>
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <svg class="w-7 h-7 inline text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                                d="M8 7V6a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-1M3 18v-7a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Zm8-3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                        </svg>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Exchange Rate') }}</div>
                </button>
            </div>

            {{-- Always rendered — Flowbite-paired (data-modal-target/toggle) trigger buttons
                 orphan their modal instance if removed via @if, so gate with a hidden class
                 instead (see the same pattern used for warehouse/purchasing above). --}}
            <button id="user_data" data-modal-target="default-modal-user-list"
                data-modal-toggle="default-modal-user-list"
                class="{{ Auth::user()->hasPermission('user.view') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Manage Users') }}
                    </div>
                </div>
            </button>
            @if (Auth::user()->hasPermission('company_profile.view'))
                <button id="pos_profile_data" onclick="openPosProfileModal()">
                    <div
                        class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                        <div
                            class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div class="font-medium text-center text-body">{{ __('Company Profile') }}
                        </div>
                    </div>
                </button>
            @endif
            @if (Auth::user()->hasPermission('category.view'))
                <button id="manage_categories_data" onclick="openManageCategoriesModal()">
                    <div
                        class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                        <div
                            class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                            <i class="fa-solid fa-tags"></i>
                        </div>
                        <div class="font-medium text-center text-body">{{ __('Manage Categories') }}
                        </div>
                    </div>
                </button>
            @endif
            <button id="purchasing" class="{{ Auth::user()->hasPermission('purchasing.view') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-basket-shopping"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Purchasing') }}
                    </div>
                </div>
            </button>

            {{-- Language: switches to the other locale and returns to this page.
                 Label shows the language you'd get by clicking, not the current one. --}}
            @php($nextLocale = app()->getLocale() === 'km' ? 'en' : 'km')
            <a href="{{ route('locale.switch', $nextLocale) }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-language"></i>
                    </div>
                    <div class="font-medium text-center text-body">
                        {{ $nextLocale === 'km' ? 'ខ្មែរ' : 'English' }}
                    </div>
                </div>
            </a>

            <button id="logout">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Logout') }}
                    </div>
                </div>
            </button>
        </div>
    </div>

    {{-- <div id="welcomeScreen" class="fixed inset-0 flex items-center justify-center bg-amber-500 z-50">
        <h1 class="text-4xl font-bold mb-4 animate-bounce">Welcome {{ Auth::user()->name }}! to POS System 🍕🥤</h1>
        <br>
    </div> --}}
    <main>
        @yield('content')

    </main>


    {{-- ALL modals will be printed here --}}
    @stack('modals')





    <div id="globalInfoPopup" class="info-popup">
        <div class="ip-header"></div>
        <div class="ip-row"><span class="ip-label">📦 Stock</span><span class="ip-value" data-f="stock"></span>
        </div>
        <div class="ip-row"><span class="ip-label">🏷 Barcode</span><span class="ip-value" data-f="barcode"></span>
        </div>
        <div class="ip-row"><span class="ip-label">📂 Category</span><span class="ip-value"
                data-f="category"></span></div>
        <div class="ip-desc" style="display:none;"></div>
    </div>
    <livewire:scripts />

    <script>
        const user_role = @json(Auth::user()->role);
        const user_report = @json(Auth::user()->report);
        const user_id = @json(Auth::user()->id);
        const warehouse_ids = @json(Auth::user()->warehouses->pluck('id'));

        // On window, not `let` — see backend/master_purchasing.blade.php: a
        // top-level `let` is script-scoped and Livewire's navigate discards it
        // on a page swap, while the persisted script files keep running and
        // then cannot see it. script.js reassigns this after a profile save,
        // which still works against a window property.
        window.pos_profile_for_print = @json($posInfoForPrint);

        // ===== i18n bridge for strings built in JS =====
        // Blade's __() can't reach text that script.js generates at runtime, so
        // the active locale's map is handed over here. t() mirrors __(): unknown
        // keys fall through to the English source string.
        const app_locale = @json(app()->getLocale());
        const translations = @json(
            app()->getLocale() === config('app.fallback_locale')
                ? []
                : (file_exists(base_path('lang/' . app()->getLocale() . '.json'))
                    ? json_decode(file_get_contents(base_path('lang/' . app()->getLocale() . '.json')), true)
                    : []));

        function t(key) {
            return translations[key] ?? key;
        }

        // ===== Global "processing" cursor + permission-denied toast =====
        // Wraps fetch() (which Livewire's own AJAX transport also uses) so
        // ANY request — raw fetch or a Livewire action — shows a busy
        // cursor automatically, without needing to touch every call site.
        // Also catches any 403 (no permission for that action) globally and
        // surfaces a toast, since most call sites don't check response.ok
        // before parsing JSON.
        (function () {
            let activeRequests = 0;
            let lastPermissionToastAt = 0;
            const originalFetch = window.fetch;
            window.fetch = function (...args) {
                activeRequests++;
                document.body.style.cursor = 'progress';
                {{-- apply(window, …) not apply(this, …): a bare fetch() from any
                     strict-mode caller (Livewire, a module) passes this ===
                     undefined, and fetch rejects that with "Illegal
                     invocation". --}}
                return originalFetch.apply(window, args).then((response) => {
                    if (response.status === 403 && typeof showToast === 'function') {
                        const now = Date.now();
                        if (now - lastPermissionToastAt > 1000) { // de-dupe bursts of parallel requests
                            lastPermissionToastAt = now;
                            showToast({
                                message: 'You do not have permission to do that.',
                                type: 'error',
                            });
                        }
                    }
                    return response;
                }).finally(() => {
                    activeRequests = Math.max(0, activeRequests - 1);
                    if (activeRequests === 0) document.body.style.cursor = '';
                });
            };
        })();

        // ===== Livewire permission-denied → toast, not the default error overlay =====
        // The fetch() wrapper above only covers plain fetch() calls. Livewire's own
        // component actions (Cart::confirmSaleOrder, PurchaseCart::post_grn, etc. —
        // guarded server-side with abort_unless(..., 403)) go through Livewire's own
        // request lifecycle, which shows its own "Something went wrong" full-page
        // error overlay on any non-200 response unless we intercept it here.
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 403 && typeof showToast === 'function') {
                        preventDefault();
                        showToast({
                            message: 'You do not have permission to do that.',
                            type: 'error',
                        });
                    }
                });
            });
        });



        // ===== Mobile cart toggle =====
        const mobileCartBtn = document.getElementById('mobileCartToggle');
        const sidebarMobile = document.getElementById('sidebar');

        if (mobileCartBtn && sidebarMobile) {

            mobileCartBtn.addEventListener('click', () => {
                const open = sidebarMobile.classList.toggle('mobile-open');
                mobileCartBtn.classList.toggle('cart-open', open);
                mobileCartBtn.querySelector('i').className = open ?
                    'fa-solid fa-xmark' :
                    'fa-solid fa-cart-shopping';
            });

            // badge: count items from the cart-sync payload (survives Livewire re-renders)
            function updateMobileBadge() {
                const el = document.getElementById('cart-sync');
                if (!el) return;
                let n = 0;
                try {
                    n = (JSON.parse(el.dataset.payload || '{}').items || []).length;
                } catch (e) {}
                const badge = document.getElementById('mobileCartBadge');
                badge.textContent = n;
                badge.style.display = n > 0 ? 'flex' : 'none';
            }
            // Called by syncDisplay() when the cart actually changes — see the
            // note there. Polling this twice a second parsed the cart JSON
            // ~5,000 times an hour to redraw a number that rarely moves.
            window.updateMobileBadge = updateMobileBadge;

            // adding a product opens nothing, but pulse the button as feedback
            window.addEventListener('click', (e) => {
                if (e.target.closest('.add-to-cart-btn')) {
                    mobileCartBtn.classList.remove('pulse');
                    void mobileCartBtn.offsetWidth; // restart animation
                    mobileCartBtn.classList.add('pulse');
                }
            });
        }

        // ===== Resizable cart sidebar (saved per user) =====
        const resizer = document.getElementById('resizer');
        const sidebarEl = document.getElementById('sidebar');

        if (resizer && sidebarEl) {
            const SIDEBAR_KEY = `pos_sidebar_width_${user_id}`;
            const MIN_W = 280;
            const maxW = () => Math.floor(window.innerWidth * 0.80);

            function setSidebarWidth(w) {
                w = Math.max(MIN_W, Math.min(maxW(), w));
                sidebarEl.style.width = `${w}px`;
            }

            // desktop-only: below the lg breakpoint the layout stacks to a
            // single column and the sidebar must stay full-width (see
            // pos.blade.php `w-full lg:w-[380px]`) — a saved desktop width
            // must never get forced onto a small/stacked screen.
            const savedW = parseInt(localStorage.getItem(SIDEBAR_KEY));
            if (savedW && window.innerWidth >= 1024) setSidebarWidth(savedW);

            let resizing = false;
            const startResize = () => {
                resizing = true;
                resizer.classList.add('active');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
            };
            const stopResize = () => {
                if (!resizing) return;
                resizing = false;
                resizer.classList.remove('active');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                localStorage.setItem(SIDEBAR_KEY, sidebarEl.getBoundingClientRect().width | 0);
            };

            resizer.addEventListener('mousedown', (e) => {
                e.preventDefault();
                startResize();
            });
            window.addEventListener('mousemove', (e) => {
                if (resizing) setSidebarWidth(window.innerWidth - e.clientX);
            });
            window.addEventListener('mouseup', stopResize);
            resizer.addEventListener('touchstart', startResize, {
                passive: true
            });
            window.addEventListener('touchmove', (e) => {
                if (resizing) setSidebarWidth(window.innerWidth - e.touches[0].clientX);
            }, {
                passive: true
            });
            window.addEventListener('touchend', stopResize);
        }

        // ===== Customer Display =====
        const cdToggle = document.getElementById('customerDisplayToggle');
        let cdWindow = null;
        let lastPayload = '';

        function broadcastCart(force = false) {
            const el = document.getElementById('cart-sync');
            if (!el) return;
            const payload = el.dataset.payload;
            if (!force && payload === lastPayload) return;
            lastPayload = payload;
            localStorage.setItem('pos_customer_display', payload);
        }

        // delegation → survives Livewire re-renders of the cart header
        document.addEventListener('change', async (e) => {
            if (e.target.id !== 'customerDisplayToggle') return;

            if (!e.target.checked) {
                if (cdWindow && !cdWindow.closed) cdWindow.close();
                return;
            }

            // 1️⃣ OPEN FIRST — while the click gesture is still valid
            cdWindow = window.open("{{ route('pos.customer-display') }}",
                'pos_customer_display', 'width=1024,height=768');

            if (!cdWindow) {
                alert('Popup blocked — please allow popups for this site.');
                e.target.checked = false;
                return;
            }

            broadcastCart(true);

            // 2️⃣ THEN find the extended screen and move the window there
            try {
                if ('getScreenDetails' in window) {
                    const details = await window.getScreenDetails(); // permission prompt 1st time
                    const ext = details.screens.find(s => !s.isPrimary);
                    if (ext && cdWindow && !cdWindow.closed) {
                        cdWindow.moveTo(ext.availLeft, ext.availTop);
                        cdWindow.resizeTo(ext.availWidth, ext.availHeight);
                    }
                }
            } catch (err) {
                console.warn('Window placement not granted', err);
            }
        });


        // ===== Customer Display sync (server-based) =====
        let lastSent = '';
        window.displayEvent = null;

        function currentDisplayTheme() {
            return localStorage.getItem(`pos_display_theme_${user_id}`) || 'dark';
        }

        async function pushDisplayState(force = false) {
            const el = document.getElementById('cart-sync');
            if (!el) return;

            const state = JSON.stringify({
                cart: JSON.parse(el.dataset.payload || '{}'),
                theme: currentDisplayTheme(),
                event: window.displayEvent,
            });

            if (!force && state === lastSent) return;
            lastSent = state;

            try {
                await fetch('/pos/display-sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ??
                            document.querySelector('input[name="_token"]')?.value,
                    },
                    body: state,
                });
            } catch (e) {}
        }
        /* ===== Display sync: event-driven, not polled =====
           pushDisplayState used to run on a 400ms timer, so every open POS tab
           woke up 150 times a minute and POSTed whenever the cart differed —
           which is what was loading the server.

           The cart only changes when Livewire re-renders it (adding an item,
           changing a quantity, removing a line, completing a sale), so that is
           when this now fires. HEARTBEAT is a safety net for state that changes
           without a re-render — the display theme, or a customer screen opened
           after the last push — not a substitute for it.

           lastSent / lastPayload still guard the POST, so a re-render that did
           not actually change the cart costs nothing. */
        const DISPLAY_HEARTBEAT = 5 * 60 * 1000; // 5 minutes

        function syncDisplay(force = false) {
            window.updateMobileBadge?.();
            broadcastCart(force);      // localStorage -> customer display tab
            pushDisplayState(force);   // server -> customer display on another device
        }

        // Cart re-rendered: an item was added, edited, removed, or the sale closed.
        document.addEventListener('livewire:update', () => syncDisplay());

        // A completed sale clears the cart; push the final state immediately
        // rather than waiting for the next re-render.
        window.addEventListener('payment-success', () => syncDisplay(true));

        document.addEventListener('DOMContentLoaded', () => syncDisplay(true));
        if (document.readyState !== 'loading') syncDisplay(true);

        setInterval(() => syncDisplay(), DISPLAY_HEARTBEAT);


        // ===== Customer Display theme button (controls display only) =====
        const DISPLAY_THEME_KEY = `pos_display_theme_${user_id}`;

        function syncThemeButton(theme) {
            const btn = document.getElementById('displayThemeToggle');
            if (!btn) return;
            btn.classList.toggle('dark-on', theme === 'dark');
            btn.innerHTML = theme === 'dark' ?
                '<i class="fa-solid fa-sun"></i>' :
                '<i class="fa-solid fa-moon"></i>';
        }

        function setDisplayTheme(theme) {
            localStorage.setItem(DISPLAY_THEME_KEY, theme);
            localStorage.setItem('pos_display_theme', theme);
            syncThemeButton(theme);
        }

        // delegation → survives Livewire re-renders of the cart header
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#displayThemeToggle')) return;
            const cur = localStorage.getItem(DISPLAY_THEME_KEY) || 'dark';
            setDisplayTheme(cur === 'dark' ? 'light' : 'dark');
        });

        // initial state + repaint icon after Livewire morphs
        setDisplayTheme(localStorage.getItem(DISPLAY_THEME_KEY) || 'dark');
        setInterval(() => syncThemeButton(localStorage.getItem(DISPLAY_THEME_KEY) || 'dark'), 1000);





        // Add fade-out before reload/navigation
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.target) {
                    e.preventDefault();
                    document.body.classList.add('fade-out');
                    setTimeout(() => {
                        window.location = this.href;
                    }, 300);
                }
            });
        });

        function loadReport() {
            window.location.href = "{{ url('/reports/gain-cost') }}";
        }




        const infoPopup = document.getElementById('globalInfoPopup');

        document.addEventListener('mouseover', (e) => {
            const wrap = e.target.closest('.info-wrap');
            if (!wrap) return;

            // fill data
            infoPopup.querySelector('.ip-header').textContent = wrap.dataset.name;
            infoPopup.querySelector('[data-f="stock"]').textContent = wrap.dataset.stock;
            infoPopup.querySelector('[data-f="barcode"]').textContent = wrap.dataset.barcode;
            infoPopup.querySelector('[data-f="category"]').textContent = wrap.dataset.category;
            const desc = infoPopup.querySelector('.ip-desc');
            if (wrap.dataset.desc) {
                desc.textContent = '📝 ' + wrap.dataset.desc;
                desc.style.display = 'block';
            } else {
                desc.style.display = 'none';
            }

            // position
            infoPopup.classList.add('show');
            const r = wrap.getBoundingClientRect();
            let top = r.bottom + 8;
            let left = r.right - 250;

            const ph = infoPopup.offsetHeight || 200;
            if (top + ph > window.innerHeight - 8) top = r.top - ph - 8;
            if (left < 8) left = 8;

            infoPopup.style.top = top + 'px';
            infoPopup.style.left = left + 'px';
        });

        document.addEventListener('mouseout', (e) => {
            const wrap = e.target.closest('.info-wrap');
            if (!wrap) return;
            if (wrap.contains(e.relatedTarget)) return;
            infoPopup.classList.remove('show');
        });

        document.addEventListener('scroll', () => infoPopup.classList.remove('show'), true);
    </script>
    <script src="{{ asset('assets/js/flowbite.min.js') }}"></script>
    <script src="{{ asset('assets/js/html2pdf.bundle.min.js') }}"></script>
    {{-- <script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script> --}}
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script> --}}
    <script src="{{ asset('assets/js/script.js') }}?v={{ filemtime(public_path('assets/js/script.js')) }}"></script>
    <script src="{{ asset('assets/js/admin.js') }}?v={{ filemtime(public_path('assets/js/admin.js')) }}"></script>
    <script src="{{ asset('assets/js/sup_admin.js') }}?v={{ filemtime(public_path('assets/js/admin.js')) }}"></script>


    <script
        src="{{ asset('assets/js/print_thermal_receipt.js') }}?v={{ filemtime(public_path('assets/js/print_thermal_receipt.js')) }}">
    </script>

    <script
        src="{{ asset('assets/js/print_document_a4.js') }}?v={{ filemtime(public_path('assets/js/print_document_a4.js')) }}">
    </script>

    {{-- Optional drag-a-card-into-the-cart input mode, toggled from the cart
         header. Loaded last so the product grid and cart already exist. --}}
    <script
        src="{{ asset('assets/js/drag_to_cart.js') }}?v={{ filemtime(public_path('assets/js/drag_to_cart.js')) }}">
    </script>

    {{-- Right-click a cart line for a wheel-driven quantity stepper. --}}
    <script
        src="{{ asset('assets/js/qty_stepper.js') }}?v={{ filemtime(public_path('assets/js/qty_stepper.js')) }}">
    </script>

    {{-- Horizontal drag divider under the product grid — the height counterpart
         to the vertical #resizer that sets the cart width. --}}
    <script
        src="{{ asset('assets/js/grid_resizer.js') }}?v={{ filemtime(public_path('assets/js/grid_resizer.js')) }}">
    </script>

</body>

</html>
