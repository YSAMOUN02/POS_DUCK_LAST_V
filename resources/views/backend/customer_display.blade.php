<!DOCTYPE html>
<html lang="km">
<head>
<meta charset="UTF-8">
<title>POS Confirel - Customer Display</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Khmer:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    * { margin:0; box-sizing:border-box; font-family:"Noto Serif Khmer", serif; }
    body {
        min-height:100vh; color:#e2e8f0;
        background: radial-gradient(1200px 600px at 80% -10%, #1e3a5f 0%, #0f172a 55%, #020617 100%);
        display:flex; flex-direction:column;
    }
    header {
        display:flex; justify-content:space-between; align-items:center;
        padding:22px 34px; border-bottom:1px solid rgba(255,255,255,.08);
    }
    header .brand { font-size:26px; font-weight:700; letter-spacing:.5px;
        background:linear-gradient(90deg,#4ade80,#22d3ee); -webkit-background-clip:text; color:transparent; }
    header .doc { font-size:14px; color:#94a3b8; }

    #welcome { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; }
    #welcome h1 { font-size:44px; font-weight:700; }
    #welcome p  { color:#94a3b8; font-size:18px; }

    #order { flex:1; display:none; grid-template-columns: 1fr 380px; overflow:hidden; }

    #items { overflow-y:auto; padding:26px 34px; }
    #items::-webkit-scrollbar { width:6px; }
    #items::-webkit-scrollbar-thumb { background:rgba(148,163,184,.4); border-radius:99px; }
    .item {
        display:flex; justify-content:space-between; align-items:center; gap:16px;
        padding:16px 18px; margin-bottom:10px;
        background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.07);
        border-radius:16px; animation: slideIn .25s ease both;
    }
    @keyframes slideIn { from{opacity:0; transform:translateY(8px);} to{opacity:1;} }
    .item .name { font-size:17px; font-weight:600; }
    .item .meta { font-size:13px; color:#94a3b8; margin-top:2px; }
    .item .line { font-size:19px; font-weight:700; white-space:nowrap;
        font-variant-numeric: tabular-nums; color:#4ade80; }
    .disc-badge { display:inline-block; margin-left:8px; font-size:11px; font-weight:700;
        background:#f43f5e; color:#fff; padding:1px 8px; border-radius:99px; }

    #summary {
        border-left:1px solid rgba(255,255,255,.08);
        padding:30px 28px; display:flex; flex-direction:column; justify-content:flex-end; gap:10px;
        background:rgba(255,255,255,.03);
    }
    .sumrow { display:flex; justify-content:space-between; font-size:15px; color:#94a3b8; }
    .sumrow span:last-child { color:#e2e8f0; font-variant-numeric: tabular-nums; }
    .grand { margin-top:14px; padding-top:18px; border-top:1px dashed rgba(255,255,255,.15); }
    .grand .label { font-size:16px; color:#94a3b8; }
    .grand .value { font-size:46px; font-weight:700; line-height:1.1;
        background:linear-gradient(90deg,#4ade80,#22d3ee); -webkit-background-clip:text; color:transparent;
        font-variant-numeric: tabular-nums; }
    .grand .usd { font-size:16px; color:#64748b; margin-top:2px; }
    footer { padding:12px 34px; font-size:13px; color:#475569; border-top:1px solid rgba(255,255,255,.06);
        display:flex; justify-content:space-between; }
        body.light {
        background: radial-gradient(1200px 600px at 80% -10%, #e0f2fe 0%, #f8fafc 55%, #ffffff 100%);
        color: #0f172a;
    }
    body.light header { border-color: rgba(0,0,0,.08); }
    body.light header .doc, body.light #welcome p { color: #64748b; }
    body.light .item { background: #ffffff; border-color: #e2e8f0; }
    body.light .item .meta { color: #64748b; }
    body.light #summary { background: #f8fafc; border-color: #e2e8f0; }
    body.light .sumrow { color: #64748b; }
    body.light .sumrow span:last-child { color: #0f172a; }
    body.light footer { color: #94a3b8; border-color: rgba(0,0,0,.06); }
    body.light .grand .value {
        background: linear-gradient(90deg, #16a34a, #0891b2);
        -webkit-background-clip: text;
    }
    body.light .item .line { color: #16a34a; }
      #thanks {
        position: fixed; inset: 0; z-index: 500;
        display: none; align-items: center; justify-content: center;
        background: rgba(2, 6, 23, .88); backdrop-filter: blur(8px);
    }
    #thanks.show { display: flex; }
    body.light #thanks { background: rgba(255,255,255,.9); }

    .t-card { text-align: center; animation: tPop .5s cubic-bezier(.2,1.4,.4,1) both; }
    @keyframes tPop { from { opacity:0; transform: scale(.7) translateY(30px); } to { opacity:1; transform:none; } }

    .t-card h2 { font-size: 52px; margin-top: 24px;
        background: linear-gradient(90deg,#4ade80,#22d3ee);
        -webkit-background-clip: text; color: transparent; }
    .t-card p  { font-size: 20px; color: #94a3b8; margin-top: 6px; }
    .t-card span { display:block; margin-top: 14px; font-size: 15px; color: #64748b;
        font-variant-numeric: tabular-nums; }
    body.light .t-card p { color:#64748b; }

    /* animated check */
    .t-check svg { width: 120px; height: 120px; }
    .t-check circle {
        fill: none; stroke: #4ade80; stroke-width: 3;
        stroke-dasharray: 151; stroke-dashoffset: 151;
        animation: draw .6s ease forwards;
    }
    .t-check path {
        fill: none; stroke: #4ade80; stroke-width: 4;
        stroke-linecap: round; stroke-linejoin: round;
        stroke-dasharray: 40; stroke-dashoffset: 40;
        animation: draw .4s ease .5s forwards;
    }
    @keyframes draw { to { stroke-dashoffset: 0; } }

    /* confetti */
    #confetti { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
    .cf {
        position: absolute; top: -12px;
        width: 10px; height: 14px; border-radius: 2px;
        animation: fall linear forwards;
    }
    @keyframes fall {
        to { transform: translateY(110vh) rotate(720deg); opacity: 0; }
    }
</style>


</head>
<body>
    <header>
        <div class="brand">CONFIREL</div>
        <div class="doc" id="cd-doc"></div>
    </header>

    <div id="welcome">
        <h1>សូមស្វាគមន៍ 🙏</h1>
        <p>Welcome — your order will appear here</p>
    </div>

    <div id="order">
        <div id="items"></div>
        <div id="summary">
            <div class="sumrow"><span>សរុបរង · Subtotal</span><span id="cd-sub"></span></div>
            <div class="sumrow"><span>បញ្ចុះតម្លៃ · Discount</span><span id="cd-disc"></span></div>
            <div class="grand">
                <div class="label">តម្លៃសរុប · Grand Total</div>
                <div class="value" id="cd-grand"></div>
                <div class="usd" id="cd-usd"></div>
            </div>
        </div>
    </div>

    <footer>
        <span id="cd-customer"></span>
        <span>សូមអរគុណ · Thank you</span>
    </footer>


  <div id="thanks">
    <div class="t-card">
        <div class="t-check">
            <svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M14 27l8 8 16-16"/></svg>
        </div>
        <h2>អរគុណច្រើន! 🙏</h2>
        <p>Thank you for your purchase</p>
        <span id="t-doc"></span>
    </div>
    <div id="confetti"></div>
</div>
<script>
    function esc(s){ const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }

    function render(data) {
        if (!data || !data.items || data.items.length === 0) {
            document.getElementById('welcome').style.display = 'flex';
            document.getElementById('order').style.display = 'none';
            document.getElementById('cd-doc').textContent = '';
            document.getElementById('cd-customer').textContent = '';
            return;
        }
        document.getElementById('welcome').style.display = 'none';
        document.getElementById('order').style.display = 'grid';

        document.getElementById('items').innerHTML = data.items.map(i => `
            <div class="item">
                <div>
                    <div class="name">${esc(i.name)}
                        ${i.disc ? `<span class="disc-badge">-${i.disc}%</span>` : ''}
                    </div>
                    <div class="meta">${esc(i.qty)} ${esc(i.unit)} × ${esc(i.price)} ${esc(data.currency)}</div>
                </div>
                <div class="line">${esc(i.line)} ${esc(data.currency)}</div>
            </div>`).join('');

        document.getElementById('cd-sub').textContent   = `${data.sub} ${data.currency}`;
        document.getElementById('cd-disc').textContent  = `${data.discount} ${data.currency}`;
        document.getElementById('cd-grand').textContent = `${data.grand} ${data.currency}`;
        document.getElementById('cd-usd').textContent   = data.grand_usd ? `≈ ${data.grand_usd} $` : '';
        document.getElementById('cd-doc').textContent   = data.doc_no !== 'NA' ? data.doc_no : '';
        document.getElementById('cd-customer').textContent = data.customer && data.customer !== 'Walk-in Customer' ? data.customer : '';
    }
 // initial + live updates
    try { render(JSON.parse(localStorage.getItem('pos_customer_display'))); } catch(e) {}

    // ===== theme sync from POS =====
    function applyDisplayTheme(t) {
        document.body.classList.toggle('light', t === 'light');
    }
    applyDisplayTheme(localStorage.getItem('pos_display_theme') || 'dark');

    // single storage listener handles BOTH keys
 window.addEventListener('storage', (e) => {
        if (e.key === 'pos_customer_display') {
            try { render(JSON.parse(e.newValue)); } catch(err) {}
        }
        if (e.key === 'pos_display_theme') {
            applyDisplayTheme(e.newValue);
        }
        if (e.key === 'pos_display_event') {
            try {
                const ev = JSON.parse(e.newValue);
                if (ev.type === 'thank_you') showThanks(ev.doc_no);
            } catch(err) {}
        }
    });


    const thanksEl = document.getElementById('thanks');
    const confettiEl = document.getElementById('confetti');
    let thanksTimer = null;

    function showThanks(docNo) {
        document.getElementById('t-doc').textContent = docNo || '';

        // confetti burst
        confettiEl.innerHTML = '';
        const colors = ['#4ade80','#22d3ee','#fbbf24','#f472b6','#a78bfa'];
        for (let i = 0; i < 60; i++) {
            const c = document.createElement('span');
            c.className = 'cf';
            c.style.left = Math.random() * 100 + 'vw';
            c.style.background = colors[i % colors.length];
            c.style.animationDuration = (2 + Math.random() * 2) + 's';
            c.style.animationDelay = (Math.random() * .6) + 's';
            confettiEl.appendChild(c);
        }

        thanksEl.classList.add('show');
        clearTimeout(thanksTimer);
        thanksTimer = setTimeout(() => thanksEl.classList.remove('show'), 6000); // auto-hide → back to welcome
    }
</script>
</body>
</html>
