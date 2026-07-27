<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gain &amp; Cost</title>
    <link rel="stylesheet" href="{{ asset('assets/css/gain-cost.css') }}">


    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Khmer:wght@100..900&display=swap" rel="stylesheet">

    {{-- the only Blade in this file: hand server data to the client --}}
    <script>
        window.GC_BOOT = @json($filters);
        window.GC_BOOT.api = "{{ url('reports/gain-cost') }}";
        window.GC_BOOT.csrf = "{{ csrf_token() }}";
    </script>
</head>

<body>
    @verbatim
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

        <div class="gc-report">
            <div class="gc-wrap">

                <header class="gc-head">
                    <div class="gc-brand">
                        <div class="gc-mark" id="ico-coins"></div>
                        <div>
                            <h1>Dashboard KPI</h1>
                            <p>Profitability intelligence · sales, purchasing &amp; expenses</p>
                        </div>
                    </div>
                    <div class="gc-tools gc-noprint">
                        <button class="btn ghost" id="btnPrint">Print</button>
                        <div class="exp-wrap">
                            <button class="btn primary" id="btnExport">Export ▾</button>
                            <div class="exp-menu" id="expMenu" hidden>
                                <button data-export="excel">Excel workbook (.xlsx)</button>
                                <button data-export="summary">Summary (KPIs · CSV)</button>
                                <button data-export="table">Current table (CSV)</button>
                                <button data-export="print">Full report (PDF)</button>
                            </div>
                        </div>
                    </div>
                </header>

                <section class="gc-filters gc-noprint">
                    <div class="f-group">
                        <label class="f-field"><span>From</span><input type="date" id="fFrom"></label>
                        <span class="f-arrow">→</span>
                        <label class="f-field"><span>To</span><input type="date" id="fTo"></label>
                    </div>
                    <label class="f-field sel"><span>View in</span><select id="fCur"></select></label>
                    <label class="f-field sel"><span>Payment</span><select id="fPay"></select></label>
                    <label class="f-field sel"><span>Warehouse</span><select id="fWh"></select></label>
                    <label class="f-field sel"><span>Category</span><select id="fCat"></select></label>
                    <label class="f-field sel"><span>Created by</span><select id="fBy"></select></label>
                    <label class="f-field sel"><span>Item</span><select id="fItem"></select></label>
                    <div class="quick" id="quick"></div>
                </section>

                <section class="kpi-grid" id="kpiGrid"></section>
                <section class="mini-row" id="miniRow"></section>

                <section class="grid-main">
                    <div class="gc-card">
                        <div class="card-h">
                            <div>
                                <h2>Revenue · Cost of Goods</h2>
                                <p class="card-sub" id="trendSub">Sales vs cost of goods sold · the gap is gross profit</p>
                            </div>
                            <div class="legend">
                                <span class="legend-i"><span class="legend-dot"
                                        style="background:#1F6079"></span>Revenue</span>
                                <span class="legend-i"><span class="legend-dot" style="background:#C0573A"></span>Cost of
                                    Goods</span>
                            </div>
                        </div>
                        <div class="chart-box"><canvas id="trendChart"></canvas></div>
                    </div>
                    <div class="gc-card">
                        <div class="card-h">
                            <div>
                                <h2>Expense Breakdown</h2>
                                <p class="card-sub">Click a slice for detail</p>
                            </div>
                        </div>
                        <div class="chart-box sm"><canvas id="expChart"></canvas></div>
                        <div class="donut-legend" id="expLegend"></div>
                    </div>
                </section>
                <section style="margin-bottom:18px;">
                    <div class="gc-card">
                        <div class="card-h">
                            <div>
                                <h2>Gross Profit — Daily</h2>
                                <p class="card-sub" id="trendGrossSub">Revenue minus cost of goods · before expenses</p>
                            </div>
                            <div class="legend">
                                <span class="legend-i"><span class="legend-dot" style="background:#1E8A5F"></span>Gross
                                    Profit</span>
                            </div>
                        </div>
                        <div class="chart-box"><canvas id="trendGrossChart"></canvas></div>
                    </div>
                </section>
                <section style="margin-bottom:18px;">
                    <div class="gc-card">
                        <div class="card-h">
                            <div>
                                <h2>Net Gain</h2>
                                <p class="card-sub" id="trendProfitSub">Profit trend · own scale so it's readable</p>
                            </div>
                            <div class="legend">
                                <span class="legend-i"><span class="legend-dot" style="background:#1E8A5F"></span>Net
                                    Gain</span>
                            </div>
                        </div>
                        <div class="chart-box"><canvas id="trendProfitChart"></canvas></div>
                    </div>
                </section>
                <section style="margin-bottom:18px;">
                    <div class="gc-card">
                        <div class="card-h">
                            <div>
                                <h2>Net Gain — Accumulated</h2>
                                <p class="card-sub" id="trendCumSub">Running total of profit over the period</p>
                            </div>
                            <div class="legend">
                                <span class="legend-i"><span class="legend-dot"
                                        style="background:#1E8A5F"></span>Cumulative
                                    Net Gain</span>
                            </div>
                        </div>
                        <div class="chart-box"><canvas id="trendCumChart"></canvas></div>
                    </div>
                </section>
                <section class="grid-sec">
                    <div class="gc-card">
                        <div class="card-h">
                            <div>
                                <h2>Gross Profit by Category</h2>
                                <p class="card-sub">Click a bar to drill in</p>
                            </div>
                        </div>
                        <div class="chart-box md"><canvas id="catChart"></canvas></div>
                    </div>
                    <div class="gc-card">
                        <div class="card-h">
                            <div>
                                <h2>Top Products by Profit</h2>
                                <p class="card-sub">Click for product detail</p>
                            </div>
                        </div>
                        <div class="tp-list" id="topList"></div>
                    </div>
                </section>









                <section style="margin:18px 0;">
                    <div
                        style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:6px;">
                        <h2 style="font-family:'Fraunces',Georgia,serif;font-size:18px;font-weight:600;margin:0;">Inventory
                            &amp; Adjustments</h2>
                        <p class="card-sub" style="margin:0;">Stock value after adjustments · gains &amp; losses · click
                            any card</p>
                    </div>
                    <div class="kpi-grid" id="invGrid"></div>
                </section>
                <section class="gc-card stock-card">
                    <div class="card-h stock-head">
                        <div>
                            <h2>Stock on Hand</h2>
                            <p class="card-sub">Live balance from purchase lots · valued at cost · own filters</p>
                        </div>
                        <div class="stock-filters gc-noprint">
                            <select id="sWh" title="Warehouse"></select>
                            <select id="sCat" title="Category"></select>
                            <select id="sItem" title="Item"></select>

                        </div>
                    </div>
                    <div class="stock-grid">
                        <div class="stock-pane">
                            <div style="color:darkgreen;" class="pane-t" id="stockQtySub">Units by item</div>
                            <div class="chart-box"><canvas id="stockQtyChart"></canvas></div>
                        </div>
                        <div class="stock-pane">
                            <div style="color:darkgreen;" class="pane-t" id="stockValSub">Value by item (cost)</div>
                            <div class="chart-box"><canvas id="stockValChart"></canvas></div>
                        </div>
                    </div>
                </section>
                <section class="gc-card">
                    <div class="card-h tx-h">
                        <div>
                            <h2>Transaction Detail</h2>
                            <p class="card-sub">Open the line-by-line explorer — or click any KPI card / chart point above
                                for a focused breakdown</p>
                        </div>
                        <div class="tx-search gc-noprint"></div>
                    </div>
                </section>


                <footer class="gc-foot">
                    <span>Net Gain = Net Revenue − Cost of Goods − Operating Expenses. Returns are netted by summing
                        <code>+/−</code> lines per invoice. Foreign-currency rows convert to base via
                        <code>factor</code>.</span>
                </footer>
            </div>

            <div class="gc-ov" id="modalOv" hidden>
                <div class="gc-modal" id="modalBox"></div>
            </div>
            <div class="gc-toast" id="toast" hidden></div>
        </div>

        <script>
            /* ============================ Gain & Cost dashboard — client ============================ */
            (function() {
                'use strict';
                const B = window.GC_BOOT,
                    API = B.api;

                const COLOR = {
                    revenue: '#1F6079',
                    cogs: '#C0573A',
                    expense: '#C58A1B',
                    profit: '#1E8A5F',
                    purchase: '#74808D',
                    gold: '#B6883A'
                };
                const DONUT = ['#C58A1B', '#1F6079', '#1E8A5F', '#C0573A', '#7E6BAE', '#74808D', '#B6883A', '#5E8C7D',
                    '#A8553F'
                ];
                const NUMH = {
                    'Qty': 1,
                    'Unit': 1,
                    'Cost': 1,
                    'Disc': 1,
                    'Net': 1,
                    'Gain': 1,
                    'Value': 1,
                    'Profit': 1,
                    'Amount': 1,
                    'Revenue': 1,
                    'Units': 1,
                    'Line Amount': 1,
                    'Unit Cost': 1,
                    'Grand Total': 1,
                    'Gross Profit': 1
                };
                const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const I = {
                    cart: '<path d="M1 1h3l2.6 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>',
                    box: '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
                    trend: '<path d="M3 17l6-6 4 4 7-7"/><path d="M14 8h6v6"/>',
                    wallet: '<rect x="2" y="5" width="20" height="15" rx="2"/><path d="M2 9h20"/><circle cx="17" cy="13" r="1.3"/>',
                    coins: '<circle cx="9" cy="9" r="6"/><path d="M16.5 5.3A6 6 0 1 1 14 18.7"/>',
                    layers: '<path d="M12 2 2 7l10 5 10-5z"/><path d="M2 12l10 5 10-5"/><path d="M2 17l10 5 10-5"/>',
                    boxes: '<path d="M12 2 4 6v6l8 4 8-4V6z"/><path d="M4 12l8 4 8-4"/>',
                    receipt: '<path d="M5 2v20l3-2 3 2 3-2 3 2V2z"/><path d="M9 7h6M9 11h6"/>',
                    bag: '<path d="M6 7h12l1 14H5z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
                    tag: '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
                    truck: '<rect x="1" y="5" width="13" height="9" rx="1"/><path d="M14 8h4l3 3v3h-7z"/><circle cx="5" cy="17" r="1.6"/><circle cx="17" cy="17" r="1.6"/>',
                    undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-4"/>',
                    users: '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.5a3.2 3.2 0 0 1 0 6"/><path d="M21.5 20a6.5 6.5 0 0 0-4.5-6.2"/>',
                    search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>',
                    up: '<path d="M3 17l6-6 4 4 7-7"/><path d="M14 8h6v6"/>',
                    down: '<path d="M3 7l6 6 4-4 7 7"/><path d="M14 16h6v-6"/>',
                    chevR: '<path d="M9 6l6 6-6 6"/>',
                    chevL: '<path d="M15 6l-6 6 6 6"/>',
                    x: '<path d="M6 6l12 12M18 6 6 18"/>'
                };
                const ic = (n) =>
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    (I[n] || '') + '</svg>';
                const $ = (id) => document.getElementById(id);
                const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;'
                } [c]));
                const CURMAP = (function() {
                    const m = {
                        USD: {
                            sym: '$',
                            dec: 2
                        },
                        KHR: {
                            sym: '៛',
                            dec: 0
                        }
                    };
                    (B.views || []).forEach(v => {
                        m[v.code] = {
                            sym: v.sym || v.code,
                            dec: (v.dec == null ? 2 : v.dec)
                        };
                    });
                    return m;
                })();
                const cur = () => CURMAP[state.view] || CURMAP.USD;
                const money = (v) => {
                    v = +v || 0;
                    const c = cur();
                    return (v < 0 ? '-' : '') + c.sym + Math.abs(v).toLocaleString('en-US', {
                        minimumFractionDigits: c.dec,
                        maximumFractionDigits: c.dec
                    });
                };
                const money2 = money;
                const moneyC = (v) => {
                    v = +v || 0;
                    const c = cur(),
                        a = Math.abs(v),
                        s = (v < 0 ? '-' : '') + c.sym;
                    if (a >= 1e9) return s + (a / 1e9).toFixed(2) + 'B';
                    if (a >= 1e6) return s + (a / 1e6).toFixed(2) + 'M';
                    if (a >= 1e5) return s + (a / 1e3).toFixed(0) + 'k';
                    return s + Math.round(a).toLocaleString('en-US');
                };
                const pct = (v) => {
                    v = +v || 0;
                    return (v < 0 ? '-' : '') + Math.abs(v).toFixed(1) + '%';
                };
                const num = (v) => Math.round(+v || 0).toLocaleString('en-US');
                const pISO = (s) => {
                    const a = String(s).slice(0, 10).split('-').map(Number);
                    return new Date(a[0], a[1] - 1, a[2]);
                };
                const tISO = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d
                    .getDate()).padStart(2, '0');
                const addD = (d, n) => {
                    const x = new Date(d);
                    x.setDate(x.getDate() + n);
                    return x;
                };
                const addM = (d, n) => {
                    const x = new Date(d);
                    x.setMonth(x.getMonth() + n);
                    return x;
                };
                const niceD = (s) => {
                    if (!s) return '';
                    const d = pISO(s);
                    return String(d.getDate()).padStart(2, '0') + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
                };

                const state = {
                    from: B.from,
                    to: B.to,
                    view: (B.defaultView || 'USD'),
                    payment: 'ALL',
                    warehouse: 'ALL',
                    category: 'ALL',
                    createdBy: 'ALL',
                    product: 'ALL',
                    sWh: 'ALL',
                    sCat: 'ALL',
                    sItem: 'ALL',
                    tab: 'sales',
                    q: '',
                    sort: 'date',
                    dir: 'desc',
                    page: 1
                };
                let trendChart, trendProfitChart, trendCumChart, trendGrossChart, expChart, catChart, stockQtyChart,
                    stockValChart, lastKpis = null,
                    lastSvc = null,
                    lastDeltas = null;

                function qs(extra) {
                    return new URLSearchParams(Object.assign({
                        from: state.from,
                        to: state.to,
                        view: state.view,
                        payment: state.payment,
                        warehouse: state.warehouse,
                        category: state.category,
                        created_by: state.createdBy,
                        product_id: state.product
                    }, extra || {})).toString();
                }

                function get(path, extra) {
                    return fetch(API + path + '?' + qs(extra), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => r.json());
                }

                function toast(msg) {
                    const t = $('toast');
                    t.textContent = msg;
                    t.hidden = false;
                    clearTimeout(toast._t);
                    toast._t = setTimeout(() => t.hidden = true, 2500);
                }

                /* ---------------- init ---------------- */
                function init() {
                    $('ico-coins').innerHTML = ic('coins');



                    const opt = (v, t) => '<option value="' + esc(v) + '">' + esc(t) + '</option>';
                    $('fCur').innerHTML = (B.views || [{
                        code: 'USD',
                        sym: '$'
                    }, {
                        code: 'KHR',
                        sym: '៛'
                    }]).map(v => '<option value="' + v.code + '"' + (v.code === state.view ? ' selected' : '') + '>' + v
                        .sym + ' ' + v.code + '</option>').join('');
                    $('fPay').innerHTML = opt('ALL', 'All methods') + (B.payments || []).map(p => opt(p, p)).join('');
                    $('fWh').innerHTML = opt('ALL', 'All warehouses') + (B.warehouses || []).map(w => opt(w.id, w.name))
                        .join('');
                    $('fCat').innerHTML = opt('ALL', 'All categories') + (B.categories || []).map(c => opt(c, c)).join('');
                    $('fBy').innerHTML = opt('ALL', 'All users') + (B.createdBys || []).map(u => opt(u, u)).join('');
                    $('fItem').innerHTML = opt('ALL', 'All items') + (B.products || []).map(p => opt(p.id, (p.code ? ('[' +
                        p.code + '] ') : '') + p.name)).join('');
                    $('sWh').innerHTML = opt('ALL', 'All warehouses') + (B.warehouses || []).map(w => opt(w.id, w.name))
                        .join('');
                    $('sCat').innerHTML = opt('ALL', 'All categories') + (B.categories || []).map(c => opt(c, c)).join('');
                    $('sItem').innerHTML = opt('ALL', 'All items') + (B.products || []).map(p => opt(p.id, (p.code ? ('[' +
                        p.code + '] ') : '') + p.name)).join('');

                    const ff = $('fFrom'),
                        ft = $('fTo');
                    ff.min = B.minDate;
                    ff.max = B.maxDate;
                    ff.value = state.from;
                    ft.min = B.minDate;
                    ft.max = B.maxDate;
                    ft.value = state.to;

                    const quicks = [
                        ['ថ្ងៃនេះ', () => setRange(pISO(B.maxDate), pISO(B.maxDate))],
                        ['30ថ្ងៃ ចុងក្រោយ', () => setRange(addD(pISO(B.maxDate), -29), pISO(B.maxDate))],
                        ['90ថ្ងៃ ចុងក្រោយ', () => setRange(addD(pISO(B.maxDate), -89), pISO(B.maxDate))],
                        ['6ខែ ចុងក្រោយ', () => setRange(addM(pISO(B.maxDate), -6), pISO(B.maxDate))],
                        ['1 ឆ្នាំ ចុងក្រោយ', () => setRange(addM(pISO(B.maxDate), -12), pISO(B.maxDate))],
                        ['ទាំងអស', () => setRange(pISO(B.minDate), pISO(B.maxDate))],
                    ];
                    const qd = $('quick');
                    quicks.forEach(([t, fn]) => {
                        const b = document.createElement('button');
                        b.className = 'chip';
                        b.textContent = t;
                        b.onclick = fn;
                        qd.appendChild(b);
                    });
                    const rb = document.createElement('button');
                    rb.className = 'chip reset';
                    rb.textContent = 'Reset';
                    rb.onclick = () => {
                        state.view = (B.defaultView || 'USD');
                        state.payment = 'ALL';
                        state.warehouse = 'ALL';
                        state.category = 'ALL';
                        state.createdBy = 'ALL';
                        state.product = 'ALL';
                        $('fCur').value = state.view;
                        $('fPay').value = 'ALL';
                        $('fWh').value = 'ALL';
                        $('fCat').value = 'ALL';
                        $('fBy').value = 'ALL';
                        $('fItem').value = 'ALL';
                        reloadAll();
                    };
                    qd.appendChild(rb);

                    ff.onchange = () => {
                        state.from = ff.value;
                        reloadAll();
                    };
                    ft.onchange = () => {
                        state.to = ft.value;
                        reloadAll();
                    };
                    $('fCur').onchange = e => {
                        state.view = e.target.value;
                        reloadAll();
                    };
                    $('fPay').onchange = e => {
                        state.payment = e.target.value;
                        reloadAll();
                    };
                    $('fWh').onchange = e => {
                        state.warehouse = e.target.value;
                        reloadAll();
                    };
                    $('fCat').onchange = e => {
                        state.category = e.target.value;
                        reloadAll();
                    };
                    $('fBy').onchange = e => {
                        state.createdBy = e.target.value;
                        reloadAll();
                    };
                    $('fItem').onchange = e => {
                        state.product = e.target.value;
                        reloadAll();
                    };





                    $('btnPrint').onclick = () => window.print();
                    $('btnExport').onclick = () => {
                        const m = $('expMenu');
                        m.hidden = !m.hidden;
                    };
                    $('sWh').onchange = e => {
                        state.sWh = e.target.value;
                        loadStock();
                    };
                    $('sCat').onchange = e => {
                        state.sCat = e.target.value;
                        loadStock();
                    };
                    $('sItem').onchange = e => {
                        state.sItem = e.target.value;
                        loadStock();
                    };

                    $('expMenu').querySelectorAll('button').forEach(b => b.onclick = () => {
                        const k = b.dataset.export;
                        $('expMenu').hidden = true;
                        if (k === 'print') {
                            window.print();
                            return;
                        }
                        if (k === 'excel') {
                            window.location = API + '/export-excel?' + qs();
                            return;
                        }
                        window.location = API + '/export?' + qs(k === 'summary' ? {
                            kind: 'summary'
                        } : {
                            kind: 'table',
                            tab: state.tab
                        });
                    });
                    document.addEventListener('click', e => {
                        if (!e.target.closest('.exp-wrap')) $('expMenu').hidden = true;
                    });
                    $('modalOv').onclick = e => {
                        if (e.target.id === 'modalOv') closeModal();
                    };
                    document.addEventListener('keydown', e => {
                        if (e.key === 'Escape') closeModal();
                    });

                    reloadAll();
                }

                function setRange(from, to) {
                    state.from = tISO(from);
                    state.to = tISO(to);
                    $('fFrom').value = state.from;
                    $('fTo').value = state.to;
                    reloadAll();
                }

                function reloadAll() {
                    skeletonKpis();
                    lastKpis = null;
                    lastSvc = null;
                    lastDeltas = null;
                    state.page = 1;
                    loadSummary();
                    loadTrend();
                    loadBreakdown();
                    loadServices();
                    loadStock();
                    loadInventory();

                }

                function switchTab(tab) {
                    document.querySelectorAll('#tabs .tab').forEach(t => t.classList.toggle('on', t.dataset.tab === tab));
                    state.tab = tab;
                    state.page = 1;
                    state.sort = 'date';
                    state.dir = 'desc';
                    state.q = '';
                    $('txSearch').value = '';
                    $('txSearch').placeholder = 'Search ' + tab + '…';
                    loadTx();
                }

                /* ---------------- loaders ---------------- */
                function loadSummary() {
                    get('/summary').then(d => {
                        lastKpis = d.kpis;
                        lastDeltas = d.deltas;
                        renderKpis();
                        renderMini();
                    }).catch(() => {});
                }

                function loadTrend() {
                    get('/trend').then(d => {
                        $('trendSub').textContent = (d.byDay ? 'Daily' : 'Monthly') +
                            ' flow over the selected period';
                        renderTrend(d.series || []);
                        renderTrendProfit(d.series || []);
                        renderTrendCum(d.series || []);
                        renderTrendGross(d.series || []);
                    }).catch(() => {});
                }

                function loadBreakdown() {
                    get('/breakdown').then(d => {
                        renderExpense(d.expense || []);
                        renderCategory(d.category || []);
                        renderTop(d.products || []);
                    }).catch(() => {});
                }

                function loadStock() {
                    get('/stock', {
                        warehouse: state.sWh,
                        category: state.sCat,
                        product_id: state.sItem
                    }).then(renderStock).catch(() => {});
                }

                function barLabel(fmt) {
                    return {
                        id: 'barLabel',
                        afterDatasetsDraw(chart) {
                            const ds = chart.data.datasets[0];
                            if (!ds) return;
                            const ctx = chart.ctx;
                            ctx.save();
                            ctx.font = '600 11px "IBM Plex Mono", monospace';
                            ctx.fillStyle = '#5A5242';
                            ctx.textBaseline = 'middle';
                            chart.getDatasetMeta(0).data.forEach((el, i) => {
                                const val = ds.data[i];
                                if (val == null) return;
                                const neg = (+val) < 0;
                                ctx.textAlign = neg ? 'right' : 'left';
                                ctx.fillText(fmt(val), el.x + (neg ? -6 : 6), el.y);
                            });
                            ctx.restore();
                        }
                    };
                }

                function renderStock(d) {
                    const t = d.totals || {
                        qty: 0,
                        value: 0,
                        items: 0,
                        lots: 0
                    };
                    $('stockQtySub').textContent = 'Total ' + (+t.qty).toLocaleString('en-US') + ' units · ' + t.items +
                        ' items';
                    $('stockValSub').textContent = 'Total ' + moneyC(t.value) + ' at cost · ' + t.lots + ' lots';
                    const items = d.byItem || [],
                        labels = items.map(c => c.name);

                    const q = $('stockQtyChart');
                    if (q && window.Chart) {
                        if (stockQtyChart) stockQtyChart.destroy();
                        if (items.length) stockQtyChart = new Chart(q, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                    label: 'Units',
                                    data: items.map(c => c.qty),
                                    backgroundColor: COLOR.revenue,
                                    borderRadius: 5,
                                    maxBarThickness: 24
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                layout: {
                                    padding: {
                                        right: 20
                                    }
                                },
                                onClick: (e, els) => {
                                    if (els.length) {
                                        const it = items[els[0].index];
                                        if (it && it.id) openDetail('stockItem', it.id, {
                                            warehouse: state.sWh,
                                            category: state.sCat,
                                            product_id: it.id
                                        });
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            title: ctx => items[ctx[0].dataIndex].name,
                                            label: c => 'Units: ' + (+c.parsed.x).toLocaleString('en-US')
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grace: '14%',
                                        grid: {
                                            color: '#F0EBDF'
                                        },
                                        ticks: {
                                            color: '#8C8473',
                                            font: {
                                                family: 'IBM Plex Mono',
                                                size: 11
                                            }
                                        }
                                    },
                                    y: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            color: '#4C4538',
                                            font: {
                                                family: 'IBM Plex Sans',
                                                size: 12
                                            }
                                        }
                                    }
                                }
                            },
                            plugins: [barLabel(v => (+v).toLocaleString('en-US'))]
                        });
                    }

                    const v = $('stockValChart');
                    if (v && window.Chart) {
                        if (stockValChart) stockValChart.destroy();
                        if (items.length) stockValChart = new Chart(v, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                    label: 'Value',
                                    data: items.map(c => c.value),
                                    backgroundColor: COLOR.gold,
                                    borderRadius: 5,
                                    maxBarThickness: 24
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                layout: {
                                    padding: {
                                        right: 20
                                    }
                                },
                                onClick: (e, els) => {
                                    if (els.length) {
                                        const it = items[els[0].index];
                                        if (it && it.id) openDetail('stockItem', it.id, {
                                            warehouse: state.sWh,
                                            category: state.sCat,
                                            product_id: it.id
                                        });
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: c => 'Value: ' + money2(c.parsed.x)
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grace: '14%',
                                        grid: {
                                            color: '#F0EBDF'
                                        },
                                        ticks: {
                                            color: '#8C8473',
                                            font: {
                                                family: 'IBM Plex Mono',
                                                size: 11
                                            },
                                            callback: axMoney
                                        }
                                    },
                                    y: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            color: '#4C4538',
                                            font: {
                                                family: 'IBM Plex Sans',
                                                size: 12
                                            }
                                        }
                                    }
                                }
                            },
                            plugins: [barLabel(v => money2(v))]
                        });
                    }
                }

                function loadServices() {
                    get('/services').then(d => {
                        lastSvc = (d && d.totals) || {
                            revenue: 0,
                            cost: 0,
                            gain: 0,
                            qty: 0,
                            lines: 0,
                            invoices: 0
                        };
                        renderKpis(); // Service Gain card + overall Net Margin depend on this
                    }).catch(() => {});
                }

                function loadTx() {
                    renderTxHead();
                    get('/transactions', {
                        tab: state.tab,
                        q: state.q,
                        sort: state.sort,
                        dir: state.dir,
                        page: state.page
                    }).then(renderTx).catch(() => {});
                }

                /* ---------------- KPI + mini ---------------- */
                function deltaHtml(v, invert) {
                    if (v === null || v === undefined || !isFinite(v))
                        return '<span class="delta flat">— no prior data</span>';
                    const up = v >= 0,
                        good = invert ? !up : up;
                    return '<span class="delta ' + (good ? 'good' : 'bad') + '">' + ic(up ? 'up' : 'down') + pct(v) +
                        ' <span class="delta-sub">vs prev.</span></span>';
                }

                function card(label, val, accent, icon, delta, invert, big, dt, di) {
                    const cls = 'kpi' + (big ? ' big' : '') + (dt ? ' clickable' : '');
                    const data = dt ? (' data-dt="' + esc(dt) + '" data-di="' + esc(di || '') + '"') : '';
                    return '<div class="' + cls + '"' + data + ' style="--accent:' + accent + '">' +
                        '<div class="kpi-top"><span class="kpi-ico">' + ic(icon) + '</span><span class="kpi-label">' +
                        label + '</span></div>' +
                        '<div class="kpi-val">' + val + '</div>' +
                        '<div class="kpi-foot">' + deltaHtml(delta, invert) + '</div></div>';
                }

                function cardPctAmt(label, pctVal, amtVal, accent, icon, note, dt, di) {
                    const cls = 'kpi' + (dt ? ' clickable' : '');
                    const data = dt ? (' data-dt="' + esc(dt) + '" data-di="' + esc(di || '') + '"') : '';
                    const amt = amtVal ?
                        '<span style="display:block;font-size:.44em;font-weight:600;color:' + accent +
                        ';opacity:.92;margin-top:2px;line-height:1.1;">' + amtVal + '</span>' : '';
                    return '<div class="' + cls + '"' + data + ' style="--accent:' + accent + '">' +
                        '<div class="kpi-top"><span class="kpi-ico">' + ic(icon) + '</span><span class="kpi-label">' +
                        label + '</span></div>' +
                        '<div class="kpi-val">' + pctVal + amt + '</div>' +
                        '<div class="kpi-foot"><span class="delta flat">' + note + '</span></div></div>';
                }

                function cardNote(label, val, accent, icon, note, big, dt, di) {
                    const cls = 'kpi' + (big ? ' big' : '') + (dt ? ' clickable' : '');
                    const data = dt ? (' data-dt="' + esc(dt) + '" data-di="' + esc(di || '') + '"') : '';
                    return '<div class="' + cls + '"' + data + ' style="--accent:' + accent + '">' +
                        '<div class="kpi-top"><span class="kpi-ico">' + ic(icon) + '</span><span class="kpi-label">' +
                        label + '</span></div>' +
                        '<div class="kpi-val">' + val + '</div>' +
                        '<div class="kpi-foot"><span class="delta flat">' + note + '</span></div></div>';
                }

               function renderKpis() {
                    const k = lastKpis;
                    if (!k) return;
                    const d = lastDeltas || {};
                    const svc = lastSvc;

                    const svcGain = svc ? (+svc.gain || 0) : 0;
                    const svcRev  = svc ? (+svc.revenue || 0) : 0;

                    // Net Gain = product net gain + service gain (service revenue − service cost)
                    const totalNet = k.net + svcGain;
                    const na = totalNet >= 0 ? COLOR.profit : COLOR.cogs;

                    const svcRevVal = svc ? moneyC(svc.revenue) : '…';

                    // Product Margin: gross profit ÷ revenue  → big %, small $ = gross profit
                    const prodMargin = k.revenue ? (k.gross / k.revenue * 100) : 0;

                    // Overall Margin: total net (product + service) ÷ total revenue  → big %, small $ = Net Gain
                    const totRev = k.revenue + svcRev;
                    const overallMargin = totRev ? (totalNet / totRev * 100) : 0;

                    $('kpiGrid').innerHTML = [
                        card('Net Revenue', moneyC(k.revenue), COLOR.revenue, 'cart', d.revenue, false, false, 'pnl'),
                        card('Cost of Goods', moneyC(k.cogs), COLOR.cogs, 'box', d.cogs, true, false, 'pnl'),
                        cardPctAmt('Product Margin', pct(prodMargin), moneyC(k.gross), COLOR.profit, 'trend', 'gross profit ÷ revenue', 'marginInfo', 'product'),
                        cardNote('Service Revenue', svcRevVal, COLOR.gold, 'tag', 'delivery & fees billed', false, 'serviceLines'),
                        card('Operating Expenses', moneyC(k.expense), COLOR.expense, 'wallet', d.expense, true, false, 'expensesList'),
                        card('Net Gain', moneyC(totalNet), na, 'coins', d.net, false, true, 'pnl'),
                        cardPctAmt('Overall Margin', svc ? pct(overallMargin) : '…', svc ? moneyC(totalNet) : '', COLOR.gold, 'layers', 'net gain ÷ total revenue', 'marginInfo', 'overall')
                    ].join('');

                    $('kpiGrid').querySelectorAll('[data-dt]').forEach(el => {
                        el.onclick = () => openDetail(el.dataset.dt, el.dataset.di || '');
                    });
                }

                function invCard(label, val, accent, icon, foot, go, big) {
                    return '<div class="kpi ' + (big ? 'big' : '') + ' clickable" data-inv="' + go + '" style="--accent:' +
                        accent + '">' +
                        '<div class="kpi-top"><span class="kpi-ico">' + ic(icon) + '</span><span class="kpi-label">' +
                        label + '</span></div>' +
                        '<div class="kpi-val">' + val + '</div>' +
                        '<div class="kpi-foot"><span class="delta flat">' + foot + '</span></div></div>';
                }

                function renderInventory(c) {
                    const netColor = c.net >= 0 ? COLOR.profit : COLOR.cogs;
                    $('invGrid').innerHTML = [
                        invCard('Inventory Gain', moneyC(c.gain), COLOR.profit, 'up', c.gainCount + ' adjustments · ' +
                            num(c.gainQty) + ' found', 'adjGain'),
                        invCard('Inventory Loss', moneyC(c.loss), COLOR.cogs, 'down', c.lossCount + ' adjustments · ' +
                            num(c.lossQty) + ' lost', 'adjLoss'),
                        invCard('Net Adjustment', moneyC(c.net), netColor, 'layers', (c.net >= 0 ? 'net gain' :
                            'net loss') + ' from adjustments', 'adjNet')
                    ].join('');
                    $('invGrid').querySelectorAll('[data-inv]').forEach(el => {
                        const t = el.getAttribute('data-inv');
                        el.onclick = () => openDetail(t, '');
                    });
                }

                function loadInventory() {
                    get('/inventory').then(d => renderInventory(d.cards || {})).catch(() => {});
                }

                function mini(label, val, icon, hint) {
                    return '<div class="mini"><span class="mini-ico">' + ic(icon) + '</span><div class="mini-body">' +
                        '<span class="mini-label">' + label + '</span><span class="mini-val">' + val +
                        '</span><span class="mini-hint">' + hint + '</span></div></div>';
                }

                function renderMini() {
                    const k = lastKpis;
                    if (!k) return;
                    $('miniRow').innerHTML = [
                        miniClick('Inventory Purchases', moneyC(k.purch), 'boxes', 'cash to vendors', 'purchases'),
                        mini('Invoices', num(k.invoices), 'receipt', num(k.qty) + ' units sold'),
                        miniClick('Customers', num(k.customers), 'users', 'buyers', 'customers'),
                        miniClick('Vendors', num(k.vendors), 'truck', 'suppliers', 'vendors'),
                        miniClick('Sale Returns', num(k.saleReturnQty), 'undo', 'units in from customers',
                            'saleReturns'),
                        miniClick('Purchase Returns', num(k.purchaseReturnQty), 'undo', 'units back to vendors',
                            'purchReturns')
                    ].join('');
                    $('miniRow').querySelectorAll('[data-dt]').forEach(el => {
                        el.onclick = () => openDetail(el.dataset.dt, el.dataset.di || '');
                    });
                }

                function skeletonKpis() {
                    $('kpiGrid').innerHTML = Array(6).fill(
                        '<div class="kpi" style="--accent:var(--line)"><div class="kpi-top"><span class="kpi-ico"></span><span class="sk" style="width:60%;height:12px"></span></div><span class="sk val"></span><div class="kpi-foot"></div></div>'
                    ).join('');
                    $('miniRow').innerHTML = Array(6).fill(
                        '<div class="mini"><div class="mini-body" style="width:100%"><span class="sk" style="width:60%;height:11px"></span><span class="sk" style="height:18px;width:70%;margin-top:5px"></span></div></div>'
                    ).join('');
                }

                /* ---------------- charts ---------------- */
                const axMoney = (v) => {
                    const c = cur();
                    return c.sym + (Math.abs(v) >= 1000 ? (v / 1000).toFixed(0) + 'k' : v);
                };

                function renderTrend(series) {
                    const ctx = $('trendChart');
                    if (!ctx || !window.Chart) return;
                    if (trendChart) trendChart.destroy();

                    const rev = series.map(s => s.revenue);
                    const cog = series.map(s => s.cogs);

                    // local compact format for THIS chart only: 5000→$5k, 5210→$5.2k
                    const kLabel = (v) => {
                        v = +v || 0;
                        const c = cur(),
                            a = Math.abs(v),
                            s = (v < 0 ? '-' : '') + c.sym;
                        const trim = (x) => x.replace(/\.0+$/, '');
                        if (a >= 1e6) return s + trim((a / 1e6).toFixed(1)) + 'M';
                        if (a >= 1e3) return s + trim((a / 1e3).toFixed(1)) + 'k';
                        return s + Math.round(a).toLocaleString('en-US');
                    };

                    // two-line value labels: Revenue ABOVE the point, Cost BELOW — so they never
                    // collide. Compact k/M only, no %. Auto-thins + always labels peaks/valleys.
                    const dualLabels = {
                        id: 'dualLabels',
                        afterDatasetsDraw(chart) {
                            const ctx = chart.ctx;
                            const n = rev.length;
                            const step = n <= 14 ? 1 : (n <= 31 ? 2 : Math.ceil(n / 14));
                            const isTurn = (arr, i) => {
                                if (i === 0 || i === n - 1) return true;
                                const a = arr[i - 1],
                                    b = arr[i],
                                    c = arr[i + 1];
                                if (a == null || b == null || c == null) return false;
                                return (b > a && b >= c) || (b < a && b <= c);
                            };
                            const draw = (dsIndex, arr, above, color) => {
                                const meta = chart.getDatasetMeta(dsIndex);
                                ctx.save();
                                ctx.textAlign = 'center';
                                ctx.font = '700 10px "IBM Plex Mono", monospace';
                                ctx.fillStyle = color;
                                ctx.textBaseline = above ? 'bottom' : 'top';
                                meta.data.forEach((pt, i) => {
                                    const val = arr[i];
                                    if (val == null || val === 0) return;
                                    const show = (i === 0) || (i === n - 1) || (i % step === 0) || isTurn(
                                        arr, i);
                                    if (!show) return;
                                    ctx.fillText(kLabel(val), pt.x, pt.y + (above ? -8 : 18));
                                });
                                ctx.restore();
                            };
                            draw(0, rev, true, COLOR.revenue); // Revenue → above, blue
                            draw(1, cog, false, COLOR.cogs); // Cost    → below, red
                        }
                    };

                    trendChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: series.map(s => s.label),
                            datasets: [{
                                    label: 'Revenue',
                                    data: rev,
                                    borderColor: COLOR.revenue,
                                    backgroundColor: 'rgba(31,96,121,.14)',
                                    fill: true,
                                    tension: .35,
                                    pointRadius: 3,
                                    pointBackgroundColor: COLOR.revenue,
                                    borderWidth: 2
                                },
                                {
                                    label: 'Cost of Products',
                                    data: cog,
                                    borderColor: COLOR.cogs,
                                    backgroundColor: 'rgba(192,87,58,.12)',
                                    fill: true,
                                    tension: .35,
                                    pointRadius: 3,
                                    pointBackgroundColor: COLOR.cogs,
                                    borderWidth: 2
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            layout: {
                                padding: {
                                    top: 22,
                                    bottom: 18
                                }
                            },
                            onClick: (e, els) => {
                                if (els.length) {
                                    const p = series[els[0].index];
                                    if (p) openDetail('pnl', null, {
                                        from: p.from,
                                        to: p.to
                                    });
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: c => c.dataset.label + ': ' + money2(c.parsed.y)
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        color: '#F0EBDF'
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Sans',
                                            size: 11
                                        },
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 8
                                    }
                                },
                                y: {
                                    grid: {
                                        color: '#F0EBDF'
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Mono',
                                            size: 11
                                        },
                                        callback: axMoney
                                    }
                                }
                            }
                        },
                        plugins: [dualLabels]
                    });
                }

                function renderTrendProfit(series) {
                    const ctx = $('trendProfitChart');
                    if (!ctx || !window.Chart) return;
                    if (trendProfitChart) trendProfitChart.destroy();

                    const data = series.map(s => s.gain);
                    const allNeg = data.every(v => v < 0);

                    trendProfitChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: series.map(s => s.label),
                            datasets: [{
                                label: 'Net Gain',
                                data: data,
                                borderColor: COLOR.profit,
                                backgroundColor: allNeg ? 'rgba(192,87,58,.12)' : 'rgba(30,138,95,.12)',
                                fill: true,
                                tension: .35,
                                pointRadius: 3,
                                pointBackgroundColor: COLOR.profit,
                                borderWidth: 2.4,
                                segment: {
                                    borderColor: ctx2 => (ctx2.p1.parsed.y < 0 ? COLOR.cogs : COLOR.profit)
                                }
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            layout: {
                                padding: {
                                    top: 38
                                }
                            },
                            onClick: (e, els) => {
                                if (els.length) {
                                    const p = series[els[0].index];
                                    if (p) openDetail('pnl', null, {
                                        from: p.from,
                                        to: p.to
                                    });
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: c => c.dataset.label + ': ' + money2(c.parsed.y)
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        color: '#F0EBDF'
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Sans',
                                            size: 11
                                        },
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 8
                                    }
                                },
                                y: {
                                    grid: {
                                        color: c => c.tick.value === 0 ? '#C9BFA8' : '#F0EBDF',
                                        lineWidth: c => c.tick.value === 0 ? 1.5 : 1
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Mono',
                                            size: 11
                                        },
                                        callback: axMoney
                                    }
                                }
                            }
                        },
                        plugins: [pointValuePct({
                            showPct: true
                        })]
                    });
                }

                function renderTrendCum(series) {
                    const ctx = $('trendCumChart');
                    if (!ctx || !window.Chart) return;
                    if (trendCumChart) trendCumChart.destroy();

                    let running = 0;
                    const cumulative = series.map(s => {
                        running += (+s.gain || 0);
                        return running;
                    });
                    $('trendCumSub').textContent = 'Running total of profit · ends at ' + money2(running);

                    trendCumChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: series.map(s => s.label),
                            datasets: [{
                                label: 'Cumulative Net Gain',
                                data: cumulative,
                                borderColor: COLOR.profit,
                                backgroundColor: 'rgba(30,138,95,.14)',
                                fill: true,
                                tension: .35,
                                pointRadius: 3,
                                pointBackgroundColor: COLOR.profit,
                                borderWidth: 2.6,
                                segment: {
                                    borderColor: c => (c.p1.parsed.y < 0 ? COLOR.cogs : COLOR.profit)
                                }
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            layout: {
                                padding: {
                                    top: 38
                                }
                            },
                            onClick: (e, els) => {
                                if (els.length) {
                                    const p = series[els[0].index];
                                    if (p) openDetail('pnl', null, {
                                        from: state.from,
                                        to: p.to,
                                        cum: 1
                                    });
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: c => 'Total so far: ' + money2(c.parsed.y)
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        color: '#F0EBDF'
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Sans',
                                            size: 11
                                        },
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 8
                                    }
                                },
                                y: {
                                    grid: {
                                        color: c => c.tick.value === 0 ? '#C9BFA8' : '#F0EBDF',
                                        lineWidth: c => c.tick.value === 0 ? 1.5 : 1
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Mono',
                                            size: 11
                                        },
                                        callback: axMoney
                                    }
                                }
                            }
                        },
                        plugins: [pointValuePct({
                            showPct: true
                        })]
                    });
                }

                function renderTrendGross(series) {
                    const ctx = $('trendGrossChart');
                    if (!ctx || !window.Chart) return;
                    if (trendGrossChart) trendGrossChart.destroy();

                    const gross = series.map(s => (+s.revenue || 0) - (+s.cogs || 0));
                    const total = gross.reduce((a, b) => a + b, 0);
                    $('trendGrossSub').textContent = 'Revenue − cost of goods · before expenses · total ' + money2(total);

                    trendGrossChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: series.map(s => s.label),
                            datasets: [{
                                label: 'Gross Profit',
                                data: gross,
                                borderColor: COLOR.profit,
                                backgroundColor: 'rgba(30,138,95,.12)',
                                fill: true,
                                tension: .35,
                                pointRadius: 3,
                                pointBackgroundColor: COLOR.profit,
                                borderWidth: 2.6,
                                segment: {
                                    borderColor: c => (c.p1.parsed.y < 0 ? COLOR.cogs : COLOR.profit)
                                }
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            layout: {
                                padding: {
                                    top: 38
                                }
                            },
                            onClick: (e, els) => {
                                if (els.length) {
                                    const p = series[els[0].index];
                                    if (p) openDetail('pnl', null, {
                                        from: p.from,
                                        to: p.to
                                    });
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: c => 'Gross Profit: ' + money2(c.parsed.y)
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        color: '#F0EBDF'
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Sans',
                                            size: 11
                                        },
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 8
                                    }
                                },
                                y: {
                                    grid: {
                                        color: c => c.tick.value === 0 ? '#C9BFA8' : '#F0EBDF',
                                        lineWidth: c => c.tick.value === 0 ? 1.5 : 1
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Mono',
                                            size: 11
                                        },
                                        callback: axMoney
                                    }
                                }
                            }
                        },
                        plugins: [pointValuePct({
                            showPct: true
                        })]
                    });
                }

                function renderExpense(arr) {
                    const ctx = $('expChart');
                    const total = arr.reduce((s, e) => s + (+e.value || 0), 0);
                    const pctOf = (v) => total > 0 ? ((+v || 0) / total * 100) : 0;

                    // plugin: draw "%" on each slice (skips slivers under 5% so labels don't collide)
                    const sliceLabels = {
                        id: 'sliceLabels',
                        afterDatasetsDraw(chart) {
                            const {
                                ctx
                            } = chart;
                            const meta = chart.getDatasetMeta(0);
                            const ds = chart.data.datasets[0];
                            ctx.save();
                            ctx.font = '700 11px "IBM Plex Mono", monospace';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            meta.data.forEach((arc, i) => {
                                const p = pctOf(ds.data[i]);
                                if (p < 5) return; // too thin to label
                                const {
                                    startAngle,
                                    endAngle,
                                    innerRadius,
                                    outerRadius
                                } = arc;
                                const mid = (startAngle + endAngle) / 2;
                                const r = innerRadius + (outerRadius - innerRadius) / 2;
                                const x = arc.x + Math.cos(mid) * r;
                                const y = arc.y + Math.sin(mid) * r;
                                ctx.fillStyle = '#fff';
                                ctx.fillText(p.toFixed(0) + '%', x, y);
                            });
                            ctx.restore();
                        }
                    };

                    if (window.Chart && ctx) {
                        if (expChart) expChart.destroy();
                        if (arr.length) {
                            expChart = new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: arr.map(e => e.name),
                                    datasets: [{
                                        data: arr.map(e => e.value),
                                        backgroundColor: arr.map((e, i) => DONUT[i % DONUT.length]),
                                        borderWidth: 0
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '62%',
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: c => c.label + ': ' + money2(c.parsed) + ' (' + pctOf(c
                                                    .parsed).toFixed(1) + '%)'
                                            }
                                        }
                                    },
                                    onClick: (e, els) => {
                                        if (els.length) openDetail('expenseName', arr[els[0].index].name);
                                    }
                                },
                                plugins: [sliceLabels] // 🔥 register the inline label plugin
                            });
                        }
                    }

                    const leg = $('expLegend');
                    if (!arr.length) {
                        leg.innerHTML = '<div class="tbl-empty" style="padding:18px">No expenses in range</div>';
                        return;
                    }
                    leg.innerHTML = arr.slice(0, 6).map((e, i) =>
                        '<button class="dleg" data-name="' + esc(e.name) + '">' +
                        '<span class="dleg-dot" style="background:' + DONUT[i % DONUT.length] + '"></span>' +
                        '<span class="dleg-name">' + esc(e.name) + '</span>' +
                        '<span class="dleg-val">' + moneyC(e.value) + '</span>' +
                        '</button>'
                    ).join('');
                    leg.querySelectorAll('.dleg').forEach(b => b.onclick = () => openDetail('expenseName', b.dataset.name));
                }

                function renderCategory(arr) {
                    const ctx = $('catChart');
                    if (!ctx || !window.Chart) return;
                    if (catChart) catChart.destroy();
                    if (!arr.length) return;
                    catChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: arr.map(c => c.category),
                            datasets: [{
                                label: 'Gross Profit',
                                data: arr.map(c => c.profit),
                                backgroundColor: arr.map(c => c.profit >= 0 ? COLOR.profit : COLOR.cogs),
                                borderRadius: 5,
                                maxBarThickness: 26
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: {
                                    right: 22
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: c => 'Gross Profit: ' + money2(c.parsed.x)
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grace: '14%',
                                    grid: {
                                        color: '#F0EBDF'
                                    },
                                    ticks: {
                                        color: '#8C8473',
                                        font: {
                                            family: 'IBM Plex Mono',
                                            size: 11
                                        },
                                        callback: axMoney
                                    }
                                },
                                y: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: '#4C4538',
                                        font: {
                                            family: 'IBM Plex Sans',
                                            size: 12
                                        }
                                    }
                                }
                            },
                            onClick: (e, els) => {
                                if (els.length) openDetail('category', arr[els[0].index].category);
                            }
                        },
                        plugins: [barLabel(v => money2(v))]
                    });
                }

                function renderTop(arr) {
                    const box = $('topList');
                    if (!arr.length) {
                        box.innerHTML = '<div class="tbl-empty">No products in range</div>';
                        return;
                    }
                    const max = arr[0].profit || 1;
                    box.innerHTML = arr.map((p, i) => '<button class="tp-row" data-id="' + esc(p.id) +
                        '"><span class="tp-rank">' + (i + 1) + '</span>' +
                        '<span class="tp-info"><span class="tp-name">' + esc(p.name) + '</span>' +
                        '<span class="tp-meta">' + esc(p.category || '') + ' · ' + num(p.qty) + ' sold · ' + (+p.margin)
                        .toFixed(0) + '% margin</span>' +
                        '<span class="tp-bar"><span class="tp-fill" style="width:' + Math.max(4, (p.profit / max) *
                            100) + '%"></span></span></span>' +
                        '<span class="tp-val">' + moneyC(p.profit) + '</span></button>').join('');
                    box.querySelectorAll('.tp-row').forEach(b => b.onclick = () => openDetail('product', b.dataset.id));
                }

                /* ---------------- transactions table ---------------- */
                function renderTxHead() {
                    const sortTh = (label, key, right) => '<th class="sortable ' + (right ? 'r' : '') + '" data-sort="' +
                        key + '"><span class="th-inner">' + label + (state.sort === key ? (state.dir === 'asc' ? ' ▲' :
                            ' ▼') : '') + '</span></th>';
                    const th = (label, right) => '<th class="' + (right ? 'r' : '') + '">' + label + '</th>';
                    let cols;
                    if (state.tab === 'sales') cols = sortTh('Date', 'date') + th('Invoice') + th('Customer') + th(
                            'Payment') + sortTh('Grand Total', 'amount', true) + sortTh('Gross Profit', 'profit', true) +
                        th('', true);
                    else if (state.tab === 'purchases') cols = sortTh('Date', 'date') + th('PO No') + th('Vendor') + th(
                        'Payment') + sortTh('Amount', 'amount', true) + th('', true);
                    else cols = sortTh('Date', 'date') + th('Expense') + th('Status') + th('Payment') + sortTh('Amount',
                        'amount', true) + th('', true);
                    $('txHead').innerHTML = '<tr>' + cols + '</tr>';
                    $('txHead').querySelectorAll('.sortable').forEach(t => t.onclick = () => {
                        const k = t.dataset.sort;
                        if (state.sort === k) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                        else {
                            state.sort = k;
                            state.dir = 'desc';
                        }
                        loadTx();
                    });
                }

                function txRow(r) {
                    const arrow = '<td class="r"><span class="row-arrow">' + ic('chevR') + '</span></td>';
                    if (state.tab === 'sales') {
                        return '<tr class="row-click" data-type="sale" data-id="' + esc(r.id) + '">' +
                            '<td class="num td-date">' + niceD(r.date) + '</td>' +
                            '<td><div class="cell-strong">' + esc(r.id) + (r.returned ?
                                '<span class="badge ret">return</span>' : '') + '</div><div class="cell-sub">' + esc(r
                                .meta || '') + '</div></td>' +
                            '<td><div class="cell-strong sm">' + esc(r.who || '') + '</div><div class="cell-sub">' + esc(r
                                .cur || '') + '</div></td>' +
                            '<td><span class="pay-pill">' + esc(r.pay || '') + '</span></td>' +
                            '<td class="r num td-amt">' + money2(r.amount) + '</td>' +
                            '<td class="r num"><span class="' + (r.profit >= 0 ? 'pos' : 'neg') + '">' + money2(r.profit) +
                            '</span></td>' + arrow + '</tr>';
                    }
                    if (state.tab === 'purchases') {
                        const who = /^\d+$/.test(String(r.who)) ? ('Vendor #' + r.who) : (r.who || '');
                        return '<tr class="row-click" data-type="purchase" data-id="' + esc(r.id) + '">' +
                            '<td class="num td-date">' + niceD(r.date) + '</td>' +
                            '<td><div class="cell-strong sm">' + esc(r.id) + '</div><div class="cell-sub">' + esc(r.meta ||
                                '') + '</div></td>' +
                            '<td><div class="cell-strong sm">' + esc(who) + '</div><div class="cell-sub">' + esc(r.cur ||
                                '') + '</div></td>' +
                            '<td><span class="pay-pill">' + esc(r.pay || '') + '</span></td>' +
                            '<td class="r num td-amt">' + money2(r.amount) + '</td>' + arrow + '</tr>';
                    }
                    return '<tr class="row-click" data-type="expense" data-id="' + esc(r.rid) + '">' +
                        '<td class="num td-date">' + niceD(r.date) + '</td>' +
                        '<td><div class="cell-strong sm">' + esc(r.who || '') + '</div><div class="cell-sub">' + esc(r
                            .meta || '') + '</div></td>' +
                        '<td><div class="cell-sub">' + ((+r.status === 1) ? 'Posted' : 'Pending') + '</div></td>' +
                        '<td><span class="pay-pill">' + esc(r.pay || '') + '</span></td>' +
                        '<td class="r num td-amt">' + money2(r.amount) + '</td>' + arrow + '</tr>';
                }

                function miniClick(label, val, icon, hint, dt, di) {
                    return '<div class="mini clickable" data-dt="' + esc(dt) + '" data-di="' + esc(di || '') +
                        '"><span class="mini-ico">' + ic(icon) + '</span><div class="mini-body">' +
                        '<span class="mini-label">' + label + '</span><span class="mini-val">' + val +
                        '</span><span class="mini-hint">' + hint + '</span></div></div>';
                }

                function renderTx(data) {
                    const rows = data.rows || [],
                        tb = $('txBody'),
                        span = state.tab === 'sales' ? 7 : 6;
                    if (!rows.length) {
                        tb.innerHTML = '<tr><td colspan="' + span + '"><div class="tbl-empty">No ' + state.tab +
                            ' match your filters.</div></td></tr>';
                    } else {
                        tb.innerHTML = rows.map(txRow).join('');
                        tb.querySelectorAll('.row-click').forEach(tr => tr.onclick = () => openDetail(tr.dataset.type, tr
                            .dataset.id));
                    }
                    const total = data.total || 0,
                        page = data.page || 1,
                        pages = data.pages || 1;
                    const a = total ? ((page - 1) * 8 + 1) : 0,
                        b = Math.min(page * 8, total);
                    $('txInfo').textContent = 'Showing ' + a + '–' + b + ' of ' + num(total);
                    $('pgLabel').textContent = page + ' / ' + pages;
                    $('pgPrev').disabled = page <= 1;
                    $('pgNext').disabled = page >= pages;
                    state.page = page;
                }

                /* ---------------- detail modal (content rendered inside the modal) ---------------- */
                function openDetail(type, id, extra) {
                    get('/detail', Object.assign({
                        type,
                        id
                    }, extra || {})).then(m => {
                        if (m) m._di = id; // remember id for the pager
                        renderModal(m);
                    }).catch(() => {});
                }

                function closeModal() {
                    $('modalOv').hidden = true;
                    $('modalBox').innerHTML = '';
                }

                function fmtKpi(val, fmt) {
                    if (fmt === '%') return (+val).toFixed(1) + '%';
                    if (fmt === '#') return num(val);
                    if (fmt === 'raw') return typeof val === 'number' ? (+val).toLocaleString('en-US', {
                        maximumFractionDigits: 6
                    }) : String(val);
                    return money2(val);
                }

                function pointValuePct(opts) {
                    opts = opts || {};
                    const showPct = opts.showPct !== false;
                    return {
                        id: 'pointValuePct',
                        afterDatasetsDraw(chart) {
                            const {
                                ctx
                            } = chart;
                            const meta = chart.getDatasetMeta(0);
                            const ds = chart.data.datasets[0];
                            if (!ds) return;
                            const vals = ds.data;
                            const n = vals.length;
                            const step = n <= 14 ? 1 : (n <= 31 ? 2 : Math.ceil(n / 14));
                            // detect local peaks & valleys (turning points) — always label these
                            const isTurn = (i) => {
                                if (i === 0 || i === n - 1) return true;
                                const a = vals[i - 1],
                                    b = vals[i],
                                    c = vals[i + 1];
                                if (a == null || b == null || c == null) return false;
                                return (b > a && b >= c) || (b < a && b <= c); // peak or valley
                            };
                            ctx.save();
                            ctx.textAlign = 'center';
                            meta.data.forEach((pt, i) => {
                                const val = vals[i];
                                if (val == null || val === 0) return; // skip empty days
                                // show: first, last, every Nth, AND every peak/valley
                                const show = (i === 0) || (i === n - 1) || (i % step === 0) || isTurn(i);
                                if (!show) return;

                                if (showPct && i > 0) {
                                    const prev = vals[i - 1];
                                    if (prev != null && prev !== 0 && isFinite(prev)) {
                                        const p = ((val - prev) / Math.abs(prev)) * 100;
                                        let txt, col;
                                        if (p > 0) {
                                            txt = '▲ ' + p.toFixed(1) + '%';
                                            col = '#1E8A5F';
                                        } else if (p < 0) {
                                            txt = '▼ ' + Math.abs(p).toFixed(1) + '%';
                                            col = '#C0573A';
                                        } else {
                                            txt = '– 0%';
                                            col = '#8C8473';
                                        }
                                        ctx.font = '700 9px "IBM Plex Sans", sans-serif';
                                        ctx.fillStyle = col;
                                        ctx.textBaseline = 'bottom';
                                        ctx.fillText(txt, pt.x, pt.y - 22);
                                    }
                                }

                                ctx.font = '700 10.5px "IBM Plex Mono", monospace';
                                ctx.fillStyle = '#2A2422';
                                ctx.textBaseline = 'bottom';
                                ctx.fillText(moneyC(val), pt.x, pt.y - 10);
                            });
                            ctx.restore();
                        }
                    };
                }

                function renderModal(m) {
                    if (!m || m.error) {
                        return;
                    }
                    const accent = COLOR[m.accent] || COLOR.profit;
                    const buildPnl = () => {
                        if (!m.pnl) return '';
                        const row = (r) => {
                            const v = +r.v || 0;
                            const sign = v < 0 ? 'neg' : (v > 0 ? 'pos' : '');
                            const cls = 'pnl-row' + (r.strong ? ' strong' : '') + (r.rule ? ' rule' : '');
                            return '<div class="' + cls + '"><span class="pnl-l">' + esc(r.l) +
                                '</span><span class="pnl-v ' + sign + '">' + money2(v) + '</span></div>';
                        };
                        return '<div class="pnl-card">' + m.pnl.map(row).join('') + '</div>';
                    };
                    let h = '<div class="m-head" style="--accent:' + accent + '"><div><div class="m-eyebrow">' + esc(m
                            .eyebrow || '') + '</div>' +
                        '<h3 class="m-title">' + esc(m.title || '') + '</h3><div class="m-tags">' + (m.tags || []).map(t =>
                            '<span class="m-tag">' + esc(t) + '</span>').join('') + '</div></div>' +
                        '<button class="m-close" id="mClose">' + ic('x') + '</button></div>';

                    if (m.meta && m.meta.length)
                        h += '<div class="m-meta">' + m.meta.map(x => '<div class="meta-i"><span class="meta-l">' + esc(x[
                            0]) + '</span><span class="meta-v">' + esc(x[1]) + '</span></div>').join('') + '</div>';

                    if (m.kpis && m.kpis.length)
                        h += '<div class="m-kpis">' + m.kpis.map(k => '<div class="m-kpi" style="--accent:' + (COLOR[k[
                                2]] || COLOR.profit) + '"><span class="m-kpi-l">' + esc(k[0]) +
                            '</span><span class="m-kpi-v">' + esc(fmtKpi(k[1], k[3])) + '</span></div>').join('') +
                        '</div>';
                    if (m.pnl) {
                        h += buildPnl();
                        if (m.linesLabel) h += '<div class="m-section-label">' + esc(m.linesLabel) + '</div>';
                    }
                    if (m.columns && m.lines) {
                        const heads = m.columns.map((c, i) => '<th class="' + ((NUMH[c] && i > 0) ? 'r' : '') + '">' + esc(
                            c) + '</th>').join('');
                        const body = m.lines.map(ln => {
                            const cls = (ln.cls === 'ret' ? 'ret ' : '') + (ln.drill ? 'drill' : '');
                            const tds = ln.cells.map((cell, i) => {
                                const right = NUMH[m.columns[i]] && i > 0;
                                if (cell && typeof cell === 'object') {
                                    const v = cell.cls ? '<span class="' + cell.cls + '">' + esc(cell.v) +
                                        '</span>' : esc(cell.v);
                                    return '<td class="' + (right ? 'r' : '') + '">' + v + (cell.sub ?
                                            '<div class="m-cell-sub">' + esc(cell.sub) + '</div>' : '') +
                                        '</td>';
                                }
                                return '<td class="' + (right ? 'r' : '') + '">' + esc(cell) + '</td>';
                            }).join('');
                            const dr = ln.drill ? ' data-dt="' + esc(ln.drill.type) + '" data-di="' + esc(ln.drill
                                .id) + '"' : '';
                            return '<tr class="' + cls.trim() + '"' + dr + '>' + tds + '</tr>';
                        }).join('');
                        h += '<div class="m-tbl-wrap"><table class="m-tbl"><thead><tr>' + heads + '</tr></thead><tbody>' +
                            body + '</tbody></table></div>';
                    }

                     if (m.totals && m.totals.length)
                        h += '<div class="m-totals">' + m.totals.map(t => {
                            const v = +t[1] || 0;
                            const sign = v < 0 ? 'neg' : (v > 0 ? 'pos' : '');
                            return '<div class="trow ' + (t[2] ? 'strong' : '') + '" style="--accent:' + accent +
                                '"><span>' + esc(t[0]) + '</span><span class="num ' + sign + '">' + money2(v) + '</span></div>';
                        }).join('') + '</div>';

                  if (m.note) h += '<div class="m-note">' + esc(m.note).replace(/\n/g, '<br>') + '</div>';
                    if (m.pages && m.pages > 1 && m.drillType) {
                        const pg = m.page || 1,
                            pgs = m.pages,
                            dt = m.drillType,
                            di = m._di || '';
                        h += '<div class="m-pager">' +
                            '<button class="m-pg-btn" data-pg="' + (pg - 1) + '"' + (pg <= 1 ? ' disabled' : '') + '>' + ic(
                                'chevL') + '</button>' +
                            '<span class="m-pg-num">' + pg + ' / ' + pgs + ' · ' + num(m.total || 0) + ' rows</span>' +
                            '<button class="m-pg-btn" data-pg="' + (pg + 1) + '"' + (pg >= pgs ? ' disabled' : '') + '>' +
                            ic('chevR') + '</button>' +
                            '</div>';
                    }
                    const box = $('modalBox');
                    box.style.setProperty('--accent', accent);
                    box.innerHTML = h;
                    $('modalOv').hidden = false;
                    $('mClose').onclick = closeModal;
                    box.querySelectorAll('tr.drill').forEach(tr => tr.onclick = () => openDetail(tr.dataset.dt, tr.dataset
                        .di));
                    box.querySelectorAll('.m-pg-btn').forEach(b => b.onclick = () => {
                        if (b.disabled) return;
                        openDetail(m.drillType, m._di || '', {
                            page: b.dataset.pg
                        });
                    });
                }
                /*  auto - thins labels when there are many points so they don 't collide.
                opts: {
                    showPct: true
                }*/


                if (document.readyState !== 'loading') init();
                else document.addEventListener('DOMContentLoaded', init);
            })
            ();
        </script>
    @endverbatim

    {{-- Line-level sales explorer. Adds a "Line detail" button to the transactions
     toolbar; opens an async, server-paginated table with its own filters. --}}
    @include('backend._gain-cost-line-explorer')
</body>
<script src="{{ asset('assets/js/info.js') }}"></script>

</html>
