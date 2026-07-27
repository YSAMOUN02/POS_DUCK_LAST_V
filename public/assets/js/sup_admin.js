if(user_role === "admin"  || user_role === "supervisor"  ){
(function () {
    const $   = (s, r = document) => r.querySelector(s);
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const num = (n) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 6 });

    const state = { warehouses: [], categories: [], results: [], selected: {} };

    function openModal()  { const m = $('#stock-adjustment-modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
    window.closeStockAdjustment = function () { const m = $('#stock-adjustment-modal'); m.classList.add('hidden'); m.classList.remove('flex'); };

    const whFilterOptions  = () => `<option value="All">All warehouses</option>` + state.warehouses.map(w => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
    const catFilterOptions = () => `<option value="All">All categories</option>` + state.categories.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    const whLineOptions = (sel) => state.warehouses.map(w => `<option value="${w.id}" ${String(w.id)===String(sel)?'selected':''}>${esc(w.name)}</option>`).join('');

    // ---- search (debounced) ----
    let searchTimer = null;
    function triggerSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            Livewire.dispatch('adj-search', { payload: {
                term:         $('#adj-search-term').value.trim(),
                warehouse_id: $('#adj-search-wh').value,
                category_id:  $('#adj-search-cat').value,
            }});
        }, 300);
    }

    function renderResults() {
        const box = $('#adj-results');
        if (!state.results.length) {
            box.innerHTML = `<div class="p-6 text-center text-sm text-gray-400">No lots found. Try another search.</div>`;
            return;
        }
        box.innerHTML = state.results.map(r => {
            const added = !!state.selected[r.wp_id];
            const zero  = Number(r.current_stock) <= 0;
            return `
            <div class="flex items-center gap-3 px-3 py-2.5 border-b border-gray-100 hover:bg-amber-50/60 transition">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-mono text-gray-400">${esc(r.code)}</span>
                        <span class="font-semibold text-gray-800 truncate">${esc(r.name)}</span>
                    </div>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-gray-500">
                        <span><i class="fa-solid fa-tag text-amber-400"></i> ${esc(r.lot || 'NO LOT')}</span>
                        <span class="text-gray-300">•</span>
                        <span><i class="fa-regular fa-calendar text-gray-400"></i> ${esc(r.expire || '—')}</span>
                        <span class="text-gray-300">•</span>
                        <span><i class="fa-solid fa-warehouse text-gray-400"></i> ${esc(r.warehouse_name)}</span>
                        ${r.grn ? `<span class="text-gray-300">•</span><span>GRN ${esc(r.grn)}</span>` : ``}
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-sm font-bold ${zero ? 'text-gray-400' : 'text-emerald-600'} tabular-nums">${num(r.current_stock)}</div>
                    <div class="text-[10px] text-gray-400">cost ${num(r.unit_cost)}</div>
                </div>
                <button type="button" data-wp="${r.wp_id}"
                    class="adj-add shrink-0 h-8 w-8 rounded-lg ${added ? 'bg-gray-200 text-gray-400 cursor-default' : 'bg-amber-500 hover:bg-amber-600 text-white'} inline-flex items-center justify-center transition"
                    ${added ? 'disabled' : ''}>
                    <i class="fa-solid ${added ? 'fa-check' : 'fa-plus'}"></i>
                </button>
            </div>`;
        }).join('');
    }

    // ---- selected lines ----
    function addLine(wpId) {
        if (state.selected[wpId]) return;
        const r = state.results.find(x => String(x.wp_id) === String(wpId));
        if (!r) return;
        state.selected[wpId] = r;
        renderLines(); renderResults();
    }
    window.adjRemoveLine = function (wpId) { delete state.selected[wpId]; renderLines(); renderResults(); };

    function renderLines() {
        const tbody = $('#adj-lines');
        const rows  = Object.values(state.selected);
        $('#adj-line-count').textContent = rows.length;
        $('#adj-confirm').disabled = rows.length === 0;

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-10 text-center text-sm text-gray-400">
                Search on the left and tap <span class="font-semibold text-amber-500">+</span> to add lots here.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => `
            <tr data-wp="${r.wp_id}" data-product-id="${r.product_id}" data-orig-lot="${esc(r.lot || '')}"
                data-unit-cost="${r.unit_cost}" data-grn="${esc(r.grn || '')}" data-direction="positive" class="align-top">
                <td class="px-3 py-3">
                    <div class="font-semibold text-gray-800 leading-tight">${esc(r.name)}</div>
                    <div class="text-[11px] font-mono text-gray-400">${esc(r.code)}</div>
                    <div class="text-[10px] text-gray-400 mt-1">on-hand ${num(r.current_stock)} · cost ${num(r.unit_cost)}${r.vendor_name ? ' · '+esc(r.vendor_name) : ''}${r.grn ? ' · GRN '+esc(r.grn) : ''}</div>
                </td>
                <td class="px-2 py-3">
                    <select class="adj-l-wh w-36 px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">${whLineOptions(r.warehouse_id)}</select>
                </td>
                <td class="px-2 py-3">
                    <select class="adj-l-bin w-28 px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                        <option value="">No Bin</option>
                    </select>
                </td>
                <td class="px-2 py-3">
                    <input type="text" class="adj-l-lot w-32 px-2 py-1.5 border border-gray-300 rounded-lg text-sm" value="${esc(r.lot || '')}" placeholder="Lot (free)">
                </td>
                <td class="px-2 py-3">
                    <input type="date" class="adj-l-exp w-40 px-2 py-1.5 border border-gray-300 rounded-lg text-sm" min="2000-01-01" max="2060-12-31" value="${esc(r.expire || '')}">
                </td>
                <td class="px-2 py-3">
                    <div class="inline-flex rounded-lg overflow-hidden border border-gray-200">
                        <button type="button" data-dir="positive" class="adj-dir px-3 py-1.5 text-sm font-bold bg-emerald-500 text-white">+</button>
                        <button type="button" data-dir="negative" class="adj-dir px-3 py-1.5 text-sm font-bold bg-gray-100 text-gray-500">−</button>
                    </div>
                </td>
                <td class="px-2 py-3">
                    <input type="number" step="0.01" min="0.01" class="adj-l-qty w-24 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-right" value="1">
                </td>
                <td class="px-2 py-3 text-center">
                    <button type="button" onclick="adjRemoveLine('${r.wp_id}')" class="h-8 w-8 rounded-lg text-rose-500 hover:bg-rose-50 inline-flex items-center justify-center">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            </tr>`).join('');

        // fill each row's bin select from its own warehouse, defaulting to
        // the bin the stock is actually sitting in right now.
        tbody.querySelectorAll('tr[data-wp]').forEach((tr) => {
            const wpId = tr.dataset.wp;
            const r = rows.find(x => String(x.wp_id) === String(wpId));
            const binSelect = tr.querySelector('.adj-l-bin');
            const whSelect = tr.querySelector('.adj-l-wh');
            if (window.populateBinSelect) {
                window.populateBinSelect(binSelect, whSelect.value).then(() => {
                    if (r?.bin_id) binSelect.value = r.bin_id;
                });
            }
        });
    }

    const ADJ_QTY_MIN = 0.01;

    function confirmAdjustment() {
        const lines = [];
        document.querySelectorAll('#adj-lines tr[data-wp]').forEach(tr => {
            let qty = parseFloat(tr.querySelector('.adj-l-qty')?.value || '0');
            if (!qty || qty <= 0) return;
            if (qty < ADJ_QTY_MIN) qty = ADJ_QTY_MIN;
            lines.push({
                wp_id:        tr.dataset.wp,
                product_id:   tr.dataset.productId,
                orig_lot:     tr.dataset.origLot || '',
                direction:    tr.dataset.direction || 'positive',
                qty:          qty,
                warehouse_id: tr.querySelector('.adj-l-wh').value,
                bin_id:       tr.querySelector('.adj-l-bin')?.value || '',
                lot:          tr.querySelector('.adj-l-lot').value,
                expire:       tr.querySelector('.adj-l-exp').value,
            });
        });
        if (!lines.length) { Livewire.dispatch('payment-error', { message: 'Set a quantity on at least one line' }); return; }

        Livewire.dispatch('stock-adjustment', { payload: {
            lines: lines, adjust_date: $('#adj-date').value, remark: $('#adj-remark').value,
        }});
    }

    // ---- events ----
    document.addEventListener('input',  (e) => { if (e.target.id === 'adj-search-term') triggerSearch(); });
    document.addEventListener('change', (e) => { if (['adj-search-wh','adj-search-cat'].includes(e.target.id)) triggerSearch(); });
    document.addEventListener('change', (e) => {
        if (!e.target.classList.contains('adj-l-wh')) return;
        const tr = e.target.closest('tr');
        const binSelect = tr?.querySelector('.adj-l-bin');
        if (binSelect && window.populateBinSelect) window.populateBinSelect(binSelect, e.target.value);
    });
    document.addEventListener('blur', (e) => {
        if (!e.target.classList?.contains('adj-l-qty')) return;
        if (e.target.value.trim() === '') return;
        let qty = parseFloat(e.target.value);
        if (Number.isNaN(qty) || qty <= 0) return;
        qty = Math.round(qty * 100) / 100;
        if (qty < ADJ_QTY_MIN) qty = ADJ_QTY_MIN;
        e.target.value = qty;
    }, true);
    document.addEventListener('click',  (e) => {
        const add = e.target.closest('.adj-add');
        if (add && !add.disabled) { addLine(add.dataset.wp); return; }

        const dir = e.target.closest('.adj-dir');
        if (dir) {
            const tr = dir.closest('tr');
            tr.dataset.direction = dir.dataset.dir;
            tr.querySelectorAll('.adj-dir').forEach(b => {
                const on = b.dataset.dir === dir.dataset.dir;
                b.classList.remove('bg-emerald-500','bg-rose-500','text-white','bg-gray-100','text-gray-500');
                if (on) b.classList.add(b.dataset.dir==='positive'?'bg-emerald-500':'bg-rose-500','text-white');
                else    b.classList.add('bg-gray-100','text-gray-500');
            });
            return;
        }
        if (e.target.closest('#adj-confirm')) confirmAdjustment();
    });

    // ---- livewire wiring ----
    document.addEventListener('livewire:init', () => {
        Livewire.on('show-stock-adjustment', (payload) => {
            const d = Array.isArray(payload) ? payload[0] : payload;
            state.warehouses = d.warehouses || [];
            state.categories = d.categories || [];
            state.results = []; state.selected = {};

            $('#adj-search-wh').innerHTML  = whFilterOptions();
            $('#adj-search-cat').innerHTML = catFilterOptions();
            $('#adj-search-term').value = '';
            $('#adj-date').value   = d.today || '';
            $('#adj-remark').value = '';

            renderResults(); renderLines(); openModal();
            triggerSearch();   // initial load (empty term → available lots)
        });

        Livewire.on('adj-search-results', (payload) => {
            const d = Array.isArray(payload) ? payload[0] : payload;
            state.results = d.rows || [];
            renderResults();
        });

        Livewire.on('adjustment-success', () => {
            window.closeStockAdjustment();
            if (typeof loadWarehouseStock === 'function') {
                loadWarehouseStock(typeof currentWarehouseId !== 'undefined' ? currentWarehouseId : 0);
            }
        });
    });
})();
}
