// dashboard-help.js
document.addEventListener('DOMContentLoaded', () => {
    if (window.__dashboardHelpLoaded) return;
    window.__dashboardHelpLoaded = true;

    const helpItems = [
        {
            title: 'Net Revenue',
            kh: 'ចំណូលសុទ្ធ',
            desc: 'ចំណូលពីការលក់ទំនិញ បន្ទាប់ពីដក discount និង sale return។ មិនរាប់ service fee។',
            formula: 'Net Revenue = Sales - Discount - Sale Return'
        },
        {
            title: 'Cost of Goods',
            kh: 'ថ្លៃដើមទំនិញ',
            desc: 'ថ្លៃដើមរបស់ទំនិញដែលបានលក់ចេញ។',
            formula: 'COGS = Unit Cost × Quantity Sold'
        },
        {
            title: 'Gross Profit',
            kh: 'ចំណេញដុល',
            desc: 'ចំណេញពីការលក់ទំនិញ មុនដកចំណាយផ្សេងៗ។',
            formula: 'Gross Profit = Net Revenue - Cost of Goods'
        },
        {
            title: 'Operating Expenses',
            kh: 'ចំណាយប្រតិបត្តិការ',
            desc: 'ចំណាយដំណើរការអាជីវកម្ម ដូចជា ads, delivery cost, salary, repair។',
            formula: 'Operating Expenses = Sum of expense records'
        },
        {
            title: 'Service Revenue',
            kh: 'ចំណូលសេវាកម្ម',
            desc: 'ចំណូលពីសេវា ដូចជា delivery fee ឬ fee ផ្សេងៗ។',
            formula: 'Service Revenue = Sum of service lines'
        },
        {
            title: 'Net Gain',
            kh: 'ចំណេញសុទ្ធ',
            desc: 'ចំណេញចុងក្រោយ បន្ទាប់ពីដកចំណាយ ហើយបូក service gain។',
            formula: 'Net Gain = Gross Profit - Operating Expenses + Service Gain'
        },
        {
            title: 'Overall Margin',
            kh: 'ភាគរយចំណេញសរុប',
            desc: 'បង្ហាញថា ក្នុងចំណូលសរុប 100 ដុល្លារ អាជីវកម្មចំណេញសុទ្ធប៉ុន្មាន។',
            formula: 'Overall Margin = Net Gain ÷ Total Revenue × 100'
        },
        {
            title: 'Inventory Purchases',
            kh: 'ការទិញស្តុក',
            desc: 'តម្លៃទំនិញដែលបានទិញចូលស្តុក។',
            formula: 'Inventory Purchases = Sum of purchase amount'
        },
        {
            title: 'Stock on Hand',
            kh: 'ស្តុកនៅសល់',
            desc: 'ចំនួនស្តុកនៅសល់ និងតម្លៃស្តុកតាមថ្លៃដើម។',
            formula: 'Stock Value = Remaining Qty × Unit Cost'
        },
        {
            title: 'Expense Breakdown',
            kh: 'បំបែកចំណាយ',
            desc: 'បង្ហាញចំណាយតាមប្រភេទ ដើម្បីដឹងថាចំណាយច្រើនទៅលើអ្វី។',
            formula: 'Expense % = Category Expense ÷ Total Expense × 100'
        },
        {
            title: 'Gross Profit Daily',
            kh: 'ចំណេញដុលប្រចាំថ្ងៃ',
            desc: 'ចំណេញដុលរបស់ថ្ងៃនីមួយៗ មុនដកចំណាយប្រតិបត្តិការ។',
            formula: 'Daily Gross Profit = Daily Revenue - Daily COGS'
        },
        {
            title: 'Net Gain Accumulated',
            kh: 'ចំណេញសុទ្ធបង្គរ',
            desc: 'ចំណេញសុទ្ធបូកបន្តរៀងរាល់ថ្ងៃ។ បើមានថ្ងៃខាត ខ្សែអាចធ្លាក់។',
            formula: 'Accumulated Net Gain = Running total of daily Net Gain'
        }
    ];

    const css = `
        .dash-float-wrap{position:fixed;right:24px;bottom:24px;z-index:9999;display:flex;flex-direction:column;gap:10px}
        .dash-float-btn{width:52px;height:52px;border-radius:50%;border:0;cursor:pointer;font-size:22px;font-weight:700;box-shadow:0 10px 25px rgba(0,0,0,.18)}
        .dash-help-btn{background:#1F8A5B;color:#fff}
        .dash-back-btn{background:#111;color:#fff}
        .dash-help-modal{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;display:none;align-items:center;justify-content:center;padding:20px}
        .dash-help-box{width:min(760px,96vw);max-height:86vh;overflow:hidden;background:#fffdf8;border:1px solid #E6D8BF;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.25);font-family:"Khmer OS Battambang","Noto Sans Khmer",Arial,sans-serif}
        .dash-help-head{padding:18px 22px;border-bottom:1px solid #EADFCB;display:flex;justify-content:space-between;gap:12px;align-items:center}
        .dash-help-head h3{margin:0;font-size:20px}
        .dash-help-close{border:0;background:#f3eadb;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:700}
        .dash-help-body{padding:18px 22px 22px}
        .dash-help-search{width:100%;padding:12px 14px;border:1px solid #D8C8AA;border-radius:12px;font-size:15px;outline:none;margin-bottom:14px;background:#fff}
        .dash-help-list{max-height:56vh;overflow:auto;display:grid;gap:10px}
        .dash-help-item{border:1px solid #E8DDC9;border-radius:14px;padding:13px 15px;background:#fff}
        .dash-help-title{font-weight:800;font-size:15px;color:#111;margin-bottom:5px}
        .dash-help-desc{color:#51483a;font-size:14px;line-height:1.7}
        .dash-help-formula{margin-top:8px;font-family:"IBM Plex Mono",monospace;font-size:12px;color:#1F8A5B;background:#EEF8F2;padding:7px 9px;border-radius:9px}
    `;

    document.head.insertAdjacentHTML('beforeend', `<style id="dashboard-help-css">${css}</style>`);

    document.body.insertAdjacentHTML('beforeend', `
        <div class="dash-float-wrap">
            <button class="dash-float-btn dash-help-btn" id="dashHelpBtn" type="button">?</button>
            <button class="dash-float-btn dash-back-btn" id="dashBackBtn" type="button">←</button>
        </div>

        <div class="dash-help-modal" id="dashHelpModal">
            <div class="dash-help-box">
                <div class="dash-help-head">
                    <h3>ពន្យល់អត្ថន័យ Dashboard KPI</h3>
                    <button class="dash-help-close" id="dashHelpClose" type="button">Close</button>
                </div>
                <div class="dash-help-body">
                    <input id="dashHelpSearch" class="dash-help-search" placeholder="Search: Net Gain, Margin, Stock...">
                    <div class="dash-help-list" id="dashHelpList"></div>
                </div>
            </div>
        </div>
    `);

    const modal = document.getElementById('dashHelpModal');
    const search = document.getElementById('dashHelpSearch');
    const list = document.getElementById('dashHelpList');

    function renderHelp(keyword = '') {
        const k = keyword.toLowerCase().trim();

        const filtered = helpItems.filter(x =>
            x.title.toLowerCase().includes(k) ||
            x.kh.toLowerCase().includes(k) ||
            x.desc.toLowerCase().includes(k) ||
            x.formula.toLowerCase().includes(k)
        );

        list.innerHTML = filtered.length
            ? filtered.map(x => `
                <div class="dash-help-item">
                    <div class="dash-help-title">${x.title} — ${x.kh}</div>
                    <div class="dash-help-desc">${x.desc}</div>
                    <div class="dash-help-formula">${x.formula}</div>
                </div>
            `).join('')
            : `<div class="dash-help-item">រកមិនឃើញទេ។</div>`;
    }

    document.getElementById('dashHelpBtn').onclick = () => {
        modal.style.display = 'flex';
        renderHelp();
        setTimeout(() => search.focus(), 50);
    };

    document.getElementById('dashHelpClose').onclick = () => modal.style.display = 'none';

    modal.onclick = e => {
        if (e.target === modal) modal.style.display = 'none';
    };

    search.oninput = e => renderHelp(e.target.value);

    document.getElementById('dashBackBtn').onclick = () => {
        window.location.href = '/Sale';
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') modal.style.display = 'none';
    });
});
