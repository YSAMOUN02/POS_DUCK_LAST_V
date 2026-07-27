<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="{{ asset('assets/js/qz-tray.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="shortcut icon" href="{{ asset('assets/icon/download.jpg') }}" type="image/x-icon">
    <link rel="stylesheet"
        href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">

    {{-- Google Font  --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Khmer:wght@100..900&display=swap" rel="stylesheet">

    <livewire:styles />

    <title>POS Confirel</title>
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
        class="fixed z-40 w-full overflow-y-auto bg-blue-400 border-t border-default rounded-t-base transition-transform bottom-0 left-0 right-0 translate-y-full bottom-[60px]"
        tabindex="-1" aria-labelledby="drawer-swipe-label">
        <div id="drawer-body" class="p-4 cursor-pointer hover:bg-blue-500" data-drawer-toggle="drawer-swipe">
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
            {{-- Always rendered — Flowbite-paired (data-modal-target/toggle) trigger
                 buttons orphan their modal instance if removed via @if, so gate with
                 a hidden class instead. --}}
            <button id="openCustomerModal" data-modal-target="default-modal-vendor-list" onclick="loadVendors(1)"
                data-modal-toggle="default-modal-vendor-list"
                class="{{ Auth::user()->hasPermission('vendor.view') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Manage Vendors') }}
                    </div>
                </div>
            </button>
            <button id="openPurchaseModal" data-modal-target="default-modal-purchase-list" onclick="loadPurchases(1)"
                data-modal-toggle="default-modal-purchase-list"
                class="{{ Auth::user()->hasPermission('purchasing.view') ? '' : 'hidden' }}">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Manage Purchase Data') }}
                    </div>
                </div>
            </button>
            <button id="Sales">
                <div
                    class="h-full flex flex-col justify-center p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-house"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Home') }}
                    </div>
                </div>
            </button>
            <button>

            </button>
            <button id="purchasing">
                <div
                    class="hidden p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
                    <div
                        class="flex justify-center items-center p-2 mx-auto mb-2 bg-neutral-primary-strong border border-default-strong rounded-full w-12 h-12">
                        <i class="fa-solid fa-basket-shopping"></i>
                    </div>
                    <div class="font-medium text-center text-body">{{ __('Purchasing') }}
                    </div>
                </div>
            </button>

            {{-- Language switcher — mirrors the one in backend.master. --}}
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
                    class="hidden p-4 rounded-base cursor-pointer bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium">
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


    <main>
        @yield('content')




    </main>


    {{-- ALL modals will be printed here --}}
    @stack('modals')



    <livewire:scripts />
    <script>
        const warehouse_ids = @json(Auth::user()->warehouses->pluck('id'));

        // ===== i18n bridge (mirrors backend.master) =====
        // listMoney() and t() in script.js read these. Without them this page
        // silently stayed English/USD while the POS page followed the locale.
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

        // Shop default rate — fallback for documents saved with factor = 1.
        const factor = @json(optional(\App\Models\Currency::where('is_default', 1)->first())->factor ?? 1);

        // ===== Global "processing" cursor =====
        // Wraps fetch() (which Livewire's own AJAX transport also uses) so
        // ANY request — raw fetch or a Livewire action — shows a busy
        // cursor automatically, without needing to touch every call site.
        (function () {
            let activeRequests = 0;
            const originalFetch = window.fetch;
            window.fetch = function (...args) {
                activeRequests++;
                document.body.style.cursor = 'progress';
                return originalFetch.apply(this, args).finally(() => {
                    activeRequests = Math.max(0, activeRequests - 1);
                    if (activeRequests === 0) document.body.style.cursor = '';
                });
            };
        })();

        // in master layout <script>, next to user_role
        const user_id = @json(Auth::user()->id);
        let pos_profile_for_print = @json($posInfoForPrint);
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
            // purchasing.blade.php `w-full lg:w-[380px]`) — a saved desktop
            // width must never get forced onto a small/stacked screen.
            const savedW = parseInt(localStorage.getItem(SIDEBAR_KEY));
            if (savedW && window.innerWidth >= 1024) setSidebarWidth(savedW);

            let resizing = false;

            function startResize() {
                resizing = true;
                resizer.classList.add('active');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
            }

            function stopResize() {
                if (!resizing) return;
                resizing = false;
                resizer.classList.remove('active');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                localStorage.setItem(SIDEBAR_KEY, sidebarEl.getBoundingClientRect().width | 0);
            }

            resizer.addEventListener('mousedown', (e) => {
                e.preventDefault();
                startResize();
            });
            window.addEventListener('mousemove', (e) => {
                if (!resizing) return;
                setSidebarWidth(window.innerWidth - e.clientX);
            });
            window.addEventListener('mouseup', stopResize);

            // touch (iPad)
            resizer.addEventListener('touchstart', startResize, {
                passive: true
            });
            window.addEventListener('touchmove', (e) => {
                if (!resizing) return;
                setSidebarWidth(window.innerWidth - e.touches[0].clientX);
            }, {
                passive: true
            });
            window.addEventListener('touchend', stopResize);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="{{ asset('assets/js/script_purchase.js') }}"></script>
    <script
        src="{{ asset('assets/js/print_document_a4.js') }}?v={{ filemtime(public_path('assets/js/print_document_a4.js')) }}">
    </script>
    <script src="{{ asset('assets/js/print_purchase.js') }}"></script>
</body>

</html>
