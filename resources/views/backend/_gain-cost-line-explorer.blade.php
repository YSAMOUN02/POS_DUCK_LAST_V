{{-- File: resources/views/reports/_gain-cost-line-explorer.blade.php
     Include this partial once near the bottom of the dashboard view, after the
     closing endverbatim of the main script. It adds a "Line detail" button to the
     transactions toolbar and also exposes window.openSalesLines(). --}}

<script>window.GC_DETAIL_API = "{{ url('reports/gain-cost/sales-detail') }}";</script>

@verbatim
<style>
#gcl-ov{position:fixed;inset:0;z-index:300;background:rgba(34,30,23,.5);backdrop-filter:blur(3px);
  display:flex;align-items:center;justify-content:center;padding:2.5vh 2vw;
  font-family:'IBM Plex Sans',system-ui,sans-serif;color:#221E17;}
#gcl-ov[hidden]{display:none;}
#gcl-ov .num{font-family:'IBM Plex Mono',ui-monospace,monospace;font-variant-numeric:tabular-nums;}
.gcl-panel{background:#FAF7F0;border:1px solid #E8E1D2;border-radius:18px;width:100%;max-width:1500px;height:95vh;
  display:flex;flex-direction:column;overflow:hidden;box-shadow:0 30px 70px rgba(34,30,23,.34);}
.gcl-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 20px;
  background:linear-gradient(160deg,#eef4f1,#fff);border-bottom:1px solid #E8E1D2;}
.gcl-head h3{font-family:'Fraunces',Georgia,serif;font-weight:600;font-size:19px;letter-spacing:-.3px;display:flex;align-items:center;gap:9px;}
.gcl-head .dot{width:9px;height:9px;border-radius:3px;background:#1F6079;}
.gcl-x{width:34px;height:34px;border-radius:10px;border:1px solid #E8E1D2;background:#fff;cursor:pointer;
  font-size:18px;line-height:1;color:#4C4538;display:grid;place-items:center;}
.gcl-x:hover{background:#221E17;color:#fff;border-color:#221E17;}

.gcl-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;padding:14px 20px;
  border-bottom:1px solid #EFEADF;background:#fff;}
.gcl-f{display:flex;flex-direction:column;gap:3px;}
.gcl-f span{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#8C8473;}
.gcl-f input,.gcl-f select{font-family:inherit;font-size:13px;color:#221E17;border:1px solid #E8E1D2;background:#FAF7F0;
  border-radius:8px;padding:7px 9px;outline:none;}
.gcl-f input:focus,.gcl-f select:focus{border-color:#1F6079;box-shadow:0 0 0 3px rgba(31,96,121,.12);}
.gcl-actions{display:flex;align-items:flex-end;gap:8px;}
.gcl-btn{font-family:inherit;font-weight:600;font-size:12.5px;border:none;border-radius:9px;padding:9px 13px;cursor:pointer;white-space:nowrap;}
.gcl-btn.dark{background:#221E17;color:#fff;}
.gcl-btn.dark:hover{background:#000;}
.gcl-btn.green{background:#1E8A5F;color:#fff;}
.gcl-btn.green:hover{background:#15694a;}
.gcl-btn.ghost{background:#fff;border:1px solid #E8E1D2;color:#4C4538;}
.gcl-btn.ghost:hover{border-color:#B6AD9B;}

.gcl-body{flex:1;overflow:auto;}
table.gcl-tbl{border-collapse:collapse;width:100%;min-width:1500px;}
.gcl-tbl thead th{position:sticky;top:0;z-index:1;background:#23303B;color:#EDE8DD;text-align:left;
  font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:11px 12px;white-space:nowrap;}
.gcl-tbl thead th.r{text-align:right;}
.gcl-tbl tbody td{padding:9px 12px;border-bottom:1px solid #EFEADF;font-size:12.5px;white-space:nowrap;}
.gcl-tbl tbody td.r{text-align:right;}
.gcl-tbl tbody tr:nth-child(even){background:#fbf9f4;}
.gcl-tbl tbody tr:hover{background:#F2ECDF;}
.gcl-strong{font-weight:600;color:#221E17;}
.gcl-sub{font-size:10.5px;color:#8C8473;}
.gcl-pill{font-size:10.5px;font-weight:600;color:#4C4538;background:#F2ECDF;padding:2px 8px;border-radius:20px;}
.gcl-empty{padding:40px;text-align:center;color:#8C8473;font-size:13px;}
.gcl-skel td{padding:11px 12px;border-bottom:1px solid #EFEADF;}
.gcl-skel .b{height:11px;border-radius:6px;background:linear-gradient(90deg,#EFEADF,#fff,#EFEADF);
  background-size:200% 100%;animation:gclsk 1.2s infinite;}
@keyframes gclsk{0%{background-position:200% 0}100%{background-position:-200% 0}}

.gcl-foot{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 20px;
  border-top:1px solid #E8E1D2;background:#fff;}
.gcl-info{font-size:12px;color:#8C8473;}
.gcl-pager{display:flex;align-items:center;gap:8px;}
.gcl-pager button{min-width:32px;height:32px;border-radius:8px;border:1px solid #E8E1D2;background:#fff;cursor:pointer;
  color:#4C4538;font-family:inherit;font-size:13px;padding:0 9px;}
.gcl-pager button:hover:not(:disabled){border-color:#221E17;color:#221E17;}
.gcl-pager button:disabled{opacity:.4;cursor:not-allowed;}
.gcl-pager .cur{background:#1F6079;color:#fff;border-color:#1F6079;}
.gcl-pager .lbl{font-size:12.5px;color:#4C4538;}

#gcl-open{display:inline-flex;align-items:center;gap:6px;font-family:inherit;font-weight:600;font-size:13px;
  color:#fff;background:#1F6079;border:none;border-radius:9px;padding:8px 13px;cursor:pointer;}
#gcl-open:hover{background:#184c60;}
</style>

<div id="gcl-ov" hidden>
  <div class="gcl-panel">
    <div class="gcl-head">
      <h3><span class="dot"></span> Sales — line detail</h3>
      <button class="gcl-x" id="gcl-close">&times;</button>
    </div>

    <div class="gcl-filters">
      <label class="gcl-f"><span>Invoice / Doc No</span><input id="gcl-q" placeholder="search…"></label>
      <label class="gcl-f"><span>From</span><input type="date" id="gcl-from"></label>
      <label class="gcl-f"><span>To</span><input type="date" id="gcl-to"></label>
      <label class="gcl-f"><span>Payment</span><select id="gcl-pay"><option value="ALL">All Payment</option></select></label>
      <label class="gcl-f"><span>Customer</span><input id="gcl-customer" placeholder="search…"></label>
      <label class="gcl-f"><span>Product</span><input id="gcl-product" placeholder="name or code…"></label>
      <label class="gcl-f"><span>Category</span><select id="gcl-cat"><option value="ALL">All Categories</option></select></label>
      <label class="gcl-f"><span>View in</span><select id="gcl-view"><option value="USD">$ USD</option><option value="KHR">៛ KHR</option></select></label>
      <label class="gcl-f"><span>Per page</span><select id="gcl-per"><option>25</option><option selected>50</option><option>100</option><option>200</option></select></label>
      <div class="gcl-actions">
        <button class="gcl-btn ghost" id="gcl-clear">Clear</button>
      </div>
    </div>

    <div class="gcl-body">
      <table class="gcl-tbl">
        <thead><tr>
          <th>{{ __('Doc No') }}</th><th>{{ __('Date') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Cust. Type') }}</th><th>{{ __('Product') }}</th><th>{{ __('Variant') }}</th>
          <th class="r">{{ __('Qty') }}</th><th>{{ __('Unit') }}</th><th class="r">{{ __('Cost') }}</th><th class="r">{{ __('Sell') }}</th><th class="r">{{ __('Subtotal') }}</th>
          <th class="r">{{ __('Disc %') }}</th><th class="r">{{ __('Disc Amt') }}</th><th class="r">{{ __('VAT %') }}</th><th class="r">{{ __('VAT') }}</th>
          <th class="r">{{ __('Net') }}</th><th class="r">{{ __('Grand Total') }}</th>
        </tr></thead>
        <tbody id="gcl-tbody"></tbody>
      </table>
    </div>

    <div class="gcl-foot">
      <button class="gcl-btn green" id="gcl-excel">⤓ Excel (all rows)</button>
      <div class="gcl-pager">
        <span class="gcl-info" id="gcl-info">—</span>
        <button id="gcl-prev">‹</button>
        <span class="lbl" id="gcl-pages">1 / 1</span>
        <button id="gcl-next">›</button>
      </div>
    </div>
  </div>
</div>

<script>
/* ===================== Sales line explorer (async, paginated) ===================== */
(function () {
  'use strict';
  const API = window.GC_DETAIL_API;
  const g = (id) => document.getElementById(id);
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

  const state = { q:'', from:'', to:'', payment:'ALL', customer:'', product:'', category:'ALL', view:'USD', per:50, page:1, optsLoaded:false };

  const cur = () => state.view === 'KHR' ? { sym:'៛', dec:0 } : { sym:'$', dec:2 };
  const money = (v) => { v = +v || 0; const c = cur(); return (v<0?'-':'') + c.sym + Math.abs(v).toLocaleString('en-US',{minimumFractionDigits:c.dec, maximumFractionDigits:c.dec}); };
  const pctv = (v) => { v = +v || 0; return (v === Math.round(v) ? v : (+v.toFixed(2))) + '%'; };
  const niceD = (s) => { if(!s) return ''; const a = String(s).slice(0,10).split('-').map(Number); const d = new Date(a[0],a[1]-1,a[2]); return String(d.getDate()).padStart(2,'0')+' '+MONTHS[d.getMonth()]+' '+d.getFullYear(); };
  const toISO = (d) => d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');

  function qs(o){
    const p = new URLSearchParams();
    p.set('view', state.view);
    if(state.from) p.set('from', state.from);
    if(state.to)   p.set('to', state.to);
    if(state.payment && state.payment !== 'ALL')   p.set('payment', state.payment);
    if(state.q)        p.set('q', state.q);
    if(state.customer) p.set('customer', state.customer);
    if(state.product)  p.set('product', state.product);
    if(state.category && state.category !== 'ALL') p.set('category', state.category);
    if(o && o.paging){ p.set('page', state.page); p.set('per', state.per); }
    if(o && o.export) p.set('export', 'csv');
    return p.toString();
  }

  function skel(){
    let r = '';
    for(let i=0;i<8;i++){ r += '<tr class="gcl-skel">'; for(let j=0;j<17;j++) r += '<td><div class="b"></div></td>'; r += '</tr>'; }
    g('gcl-tbody').innerHTML = r;
  }

  function rowHtml(x){
    return '<tr>'
      + '<td><span class="gcl-strong">'+esc(x.doc)+'</span></td>'
      + '<td class="num">'+niceD(x.date)+'</td>'
      + '<td>'+(x.payment?'<span class="gcl-pill">'+esc(x.payment)+'</span>':'')+'</td>'
      + '<td>'+esc(x.ctype||'')+'</td>'
      + '<td><span class="gcl-strong">'+esc(x.product||'')+'</span>'+(x.code?'<div class="gcl-sub">'+esc(x.code)+'</div>':'')+'</td>'
      + '<td>'+esc(x.variant||'')+'</td>'
      + '<td class="r num">'+(+x.qty).toLocaleString('en-US')+'</td>'
      + '<td>'+esc(x.unit||'')+'</td>'
      + '<td class="r num">'+money(x.cost)+'</td>'
      + '<td class="r num">'+money(x.sell)+'</td>'
      + '<td class="r num">'+money(x.subtotal)+'</td>'
      + '<td class="r num">'+pctv(x.disc_pct)+'</td>'
      + '<td class="r num">'+money(x.disc_amt)+'</td>'
      + '<td class="r num">'+pctv(x.vat_pct)+'</td>'
      + '<td class="r num">'+money(x.vat_amt)+'</td>'
      + '<td class="r num">'+money(x.net)+'</td>'
      + '<td class="r num">'+money(x.grand)+'</td>'
      + '</tr>';
  }

  function pager(page, pages){
    const set = new Set([1, pages, page, page-1, page+1, page-2, page+2]);
    const nums = [...set].filter(p => p>=1 && p<=pages).sort((a,b)=>a-b);
    let html = '', prev = 0;
    nums.forEach(p => { if(prev && p-prev>1) html += '<span class="lbl">…</span>'; html += '<button class="'+(p===page?'cur':'')+'" data-pg="'+p+'">'+p+'</button>'; prev = p; });
    const box = g('gcl-pages'); box.innerHTML = html;
    box.querySelectorAll('button[data-pg]').forEach(b => b.onclick = () => { state.page = +b.dataset.pg; load(); });
  }

  function render(d){
    const rows = d.rows || [];
    g('gcl-tbody').innerHTML = rows.length ? rows.map(rowHtml).join('')
      : '<tr><td colspan="17"><div class="gcl-empty">No lines match these filters.</div></td></tr>';

    if(d.categories && !state.optsLoaded){
      g('gcl-cat').innerHTML = '<option value="ALL">All Categories</option>' + d.categories.map(c => '<option value="'+esc(c)+'">'+esc(c)+'</option>').join('');
      g('gcl-cat').value = state.category;
    }
    if(d.payments && !state.optsLoaded){
      g('gcl-pay').innerHTML = '<option value="ALL">All Payment</option>' + d.payments.map(p => '<option value="'+esc(p)+'">'+esc(p)+'</option>').join('');
      g('gcl-pay').value = state.payment;
    }
    if(d.categories || d.payments) state.optsLoaded = true;

    const total = d.total||0, page = d.page||1, per = d.per||state.per, pages = d.pages||1;
    const a = total ? (page-1)*per+1 : 0, b = Math.min(page*per, total);
    g('gcl-info').textContent = 'Showing ' + a + '–' + b + ' of ' + total.toLocaleString('en-US');
    g('gcl-prev').disabled = page<=1; g('gcl-next').disabled = page>=pages;
    state.page = page;
    pager(page, pages);
  }

  function load(){
    skel();
    fetch(API + '?' + qs({paging:true}), { headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(r => r.json()).then(render)
      .catch(() => { g('gcl-tbody').innerHTML = '<tr><td colspan="17"><div class="gcl-empty">Could not load — check the route &amp; method.</div></td></tr>'; });
  }

  // ---- wiring ----
  let dt;
  const deb = (fn) => { clearTimeout(dt); dt = setTimeout(fn, 350); };
  function bind(){
    g('gcl-q').oninput        = e => deb(() => { state.q = e.target.value.trim(); state.page=1; load(); });
    g('gcl-customer').oninput = e => deb(() => { state.customer = e.target.value.trim(); state.page=1; load(); });
    g('gcl-product').oninput  = e => deb(() => { state.product = e.target.value.trim(); state.page=1; load(); });
    g('gcl-from').onchange = e => { state.from = e.target.value; state.page=1; load(); };
    g('gcl-to').onchange   = e => { state.to = e.target.value; state.page=1; load(); };
    g('gcl-pay').onchange  = e => { state.payment = e.target.value; state.page=1; load(); };
    g('gcl-cat').onchange  = e => { state.category = e.target.value; state.page=1; load(); };
    g('gcl-view').onchange = e => { state.view = e.target.value; load(); };
    g('gcl-per').onchange  = e => { state.per = +e.target.value; state.page=1; load(); };
    g('gcl-clear').onclick = () => {
      Object.assign(state, { q:'', customer:'', product:'', payment:'ALL', category:'ALL', page:1 });
      g('gcl-q').value=''; g('gcl-customer').value=''; g('gcl-product').value=''; g('gcl-pay').value='ALL'; g('gcl-cat').value='ALL';
      load();
    };
    g('gcl-prev').onclick = () => { if(state.page>1){ state.page--; load(); } };
    g('gcl-next').onclick = () => { state.page++; load(); };
    g('gcl-excel').onclick = () => { window.location = API + '?' + qs({export:true}); };
    g('gcl-close').onclick = close;
    g('gcl-ov').addEventListener('click', e => { if(e.target.id === 'gcl-ov') close(); });
    document.addEventListener('keydown', e => { if(e.key === 'Escape' && !g('gcl-ov').hidden) close(); });
  }

  function open(){
    if(!state.from || !state.to){
      const today = new Date(), past = new Date(); past.setMonth(past.getMonth()-6);
      state.from = toISO(past); state.to = toISO(today);
      g('gcl-from').value = state.from; g('gcl-to').value = state.to;
    }
    g('gcl-ov').hidden = false;
    load();
  }
  function close(){ g('gcl-ov').hidden = true; }

  function attach(){
    bind();
    window.openSalesLines = open;
    // add a launcher button into the dashboard transactions toolbar if present
    const host = document.querySelector('.tx-h');
    if(host && !document.getElementById('gcl-open')){
      const btn = document.createElement('button');
      btn.id = 'gcl-open'; btn.type = 'button'; btn.innerHTML = '⤢ Line detail';
      btn.onclick = open;
      host.appendChild(btn);
    }
  }

  if(document.readyState !== 'loading') attach(); else document.addEventListener('DOMContentLoaded', attach);
})();
</script>
@endverbatim
