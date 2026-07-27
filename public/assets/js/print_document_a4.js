// =====================================================
// A4 DOCUMENT PRINTING — Quotation / Invoice / Delivery Note / Picking List
// Prints via the browser's own print dialog (window.print()) — no QZ Tray
// setup needed here; the user picks a printer and adjusts margins in that
// dialog each time. (The 80mm thermal receipt is separate and still uses
// QZ Tray — see print_thermal_receipt.js.)
// =====================================================

// ---- print an A4 HTML document via the browser's native print dialog —
// opens the document in a new tab and triggers window.print() on it. This
// IS the preview: the browser's print dialog shows the page, lets the user
// adjust margins/paper size/printer before committing, and they can just
// close the tab instead of printing if they change their mind. No QZ Tray
// involved for A4 docs (still used separately for the 80mm thermal receipt).
function printA4(html, docNo) {
    const w = window.open("", "_blank", "width=900,height=1200");
    if (!w) {
        alert("Popup blocked — allow popups for this site to print.");
        return;
    }
    w.document.write(html);
    w.document.close();
    w.onload = () => {
        w.focus();
        w.print();
    };
}

function formatMoneyA4(value) {
    let amount = parseFloat(String(value ?? 0).replace(/,/g, ""));
    if (isNaN(amount)) amount = 0;
    return amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// quantities only show decimals when the value actually has a fractional
// part (6 -> "6", 2.5 -> "2.5"), unlike money which always shows 2 decimals
function formatQtyA4(value) {
    let amount = parseFloat(String(value ?? 0).replace(/,/g, ""));
    if (isNaN(amount)) amount = 0;
    return amount.toLocaleString("en-US", { minimumFractionDigits: 0, maximumFractionDigits: 4 });
}

function fmtDateA4(d) {
    return d
        ? new Date(d).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })
        : "-";
}

// ---- shared A4 letterhead + document styling ----
let style_a4_document = `
<style>
@font-face{
    font-family:'Noto Serif Khmer';
    src:url('/assets/Font/khmer.woff2') format('woff2');
    unicode-range:U+1780-17FF,U+19E0-19FF,U+200C-200D,U+25CC;
}
@font-face{
    font-family:'Noto Serif Khmer';
    src:url('/assets/Font/latinex.woff2') format('woff2');
    unicode-range:U+0100-02BA,U+1E00-1EFF,U+2020,U+20A0-20AB;
}

@page { size: A4; margin: 14mm; }

html, body {
    margin: 0; padding: 0;
    font-family: 'Noto Serif Khmer', serif;
    color: #1f2937;
    font-size: 12.5px;
}

.a4-page {
    width: 100%;
    min-height: 269mm;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    page-break-after: always;
    break-after: page;
}

/* combined multi-document print jobs concatenate several .a4-page blocks —
   don't leave a trailing blank page after the last one */
.a4-page:last-child {
    page-break-after: auto;
    break-after: auto;
}

.a4-letterhead {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    padding-bottom: 10px;
    margin-bottom: 16px;
}

.a4-company-block {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.a4-company-logo {
    max-width: 60px;
    max-height: 60px;
    object-fit: contain;
    flex: 0 0 auto;
}

.a4-company-name {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: .3px;
    color: #111827;
}

.a4-company-line {
    font-size: 11px;
    color: #4b5563;
    line-height: 1.5;
}

.a4-doc-meta {
    text-align: right;
    min-width: 220px;
}

.a4-doc-title {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #111827;
    margin-bottom: 6px;
}

.a4-meta-row {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    font-size: 11.5px;
    line-height: 1.6;
}

.a4-meta-label {
    color: #6b7280;
    font-weight: 600;
    min-width: 80px;
    text-align: left;
}

.a4-meta-value {
    font-weight: 700;
    color: #111827;
}

.a4-bill-to {
    margin-bottom: 10px;
    font-size: 12px;
    line-height: 1.6;
}

.a4-bill-to .a4-bill-label {
    font-size: 12px;
    font-weight: 700;
    text-decoration: underline;
    margin-bottom: 2px;
}

table.a4-items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
}

table.a4-items th,
table.a4-items td {
    border: 1px solid #000;
    padding: 6px 6px;
    font-size: 12px;
    text-align: center;
    vertical-align: top;
}

table.a4-items thead th {
    font-weight: 700;
}

table.a4-items td.a4-col-item { text-align: left; }
table.a4-items td.a4-col-num { text-align: right; }

table.a4-items tr.a4-total-row td { font-weight: 700; }
table.a4-items tr.a4-total-row td.a4-total-label { text-align: right; }
table.a4-items tr.a4-grand-row td { font-size: 13px; }

.a4-terms {
    font-size: 11.5px;
    color: #1f2937;
    line-height: 1.8;
    margin-bottom: 16px;
}
.a4-terms .a4-terms-title {
    font-size: 12px;
    font-weight: 700;
    text-decoration: underline;
    margin-bottom: 4px;
}

.a4-thankyou {
    font-weight: 700;
    font-size: 12.5px;
    margin-top: 10px;
}

.a4-signoff {
    margin-top: 14px;
    font-size: 12px;
    line-height: 1.8;
}

.a4-footer-row {
    margin-top: auto;
    display: flex;
    justify-content: space-between;
    gap: 40px;
    padding-top: 30px;
    font-size: 12px;
}

.a4-footer-block { width: 45%; }
.a4-footer-title { font-weight: 700; margin-bottom: 8px; }
.a4-footer-line { display: flex; gap: 6px; margin-top: 6px; }
.a4-footer-fill { flex: 1; border-bottom: 1px solid #9ca3af; min-width: 80px; }

@media print { button { display: none; } }
</style>
`;

function buildA4Letterhead(posInfo, docTitle, docNoLabel, docNo, extraMetaRows = []) {
    const shop = posInfo || {};
    const metaRows = [
        { label: "Date", value: fmtDateA4(new Date()) },
        { label: docNoLabel, value: docNo || "-" },
        ...extraMetaRows,
    ];

    return `
        <div class="a4-letterhead">
            <div class="a4-company-block">
                ${shop.logo_url ? `<img class="a4-company-logo" src="${shop.logo_url}" alt="Logo">` : ""}
                <div>
                    <div class="a4-company-name">${shop.company || "Your Company"}</div>
                    ${shop.description ? `<div class="a4-company-line">${shop.description}</div>` : ""}
                    ${shop.address1 ? `<div class="a4-company-line">${shop.address1}</div>` : ""}
                    ${shop.address2 ? `<div class="a4-company-line">${shop.address2}</div>` : ""}
                    ${(shop.phone1 || shop.phone2) ? `<div class="a4-company-line">${[shop.phone1, shop.phone2].filter(Boolean).join(" / ")}</div>` : ""}
                    ${shop.telegram ? `<div class="a4-company-line">Telegram: ${shop.telegram}</div>` : ""}
                    ${shop.email ? `<div class="a4-company-line">${shop.email}</div>` : ""}
                    ${shop.social ? `<div class="a4-company-line">${shop.social}</div>` : ""}
                </div>
            </div>
            <div class="a4-doc-meta">
                <div class="a4-doc-title">${docTitle}</div>
                ${metaRows.map(r => `
                    <div class="a4-meta-row">
                        <span class="a4-meta-label">${r.label}</span>
                        <span class="a4-meta-value">: ${r.value}</span>
                    </div>
                `).join("")}
            </div>
        </div>
    `;
}

function buildA4BillTo(label, name, phone, address) {
    const hasData = name || phone || address;
    return `
        <div class="a4-bill-to">
            <div class="a4-bill-label">${label}</div>
            ${hasData ? `
                <div><b>${name || "General"}</b>${phone ? ` · ${phone}` : ""}</div>
                ${address ? `<div>${address}</div>` : ""}
            ` : ""}
        </div>
    `;
}

// items table WITH price columns + Sub Total / Grand Total rows built
// into the same bordered table (Quotation / Invoice)
function buildA4PricedItemsTable(lines, header) {
    const rows = (lines || []).map((l, i) => `
        <tr>
            <td>${i + 1}</td>
            <td class="a4-col-item">${l.name || ""}</td>
            <td>${l.unit || ""}</td>
            <td class="a4-col-num">${formatQtyA4(l.quantity)}</td>
            <td class="a4-col-num">${formatMoneyA4(l.sell_price)}</td>
            <td class="a4-col-num">${formatMoneyA4(l.grand_total_amount ?? (l.quantity * l.sell_price))}</td>
        </tr>
    `).join("");

    const sub = parseFloat(header.total_amount) || 0;
    const discount = parseFloat(header.discount_amount) || 0;
    const vat = parseFloat(header.vat_amount) || 0;
    const grand = parseFloat(header.grand_total) || 0;

    const totalRows = `
        <tr class="a4-total-row">
            <td colspan="5" class="a4-total-label">Sub Total ($)</td>
            <td class="a4-col-num">${formatMoneyA4(sub)}</td>
        </tr>
        ${discount > 0 ? `
        <tr class="a4-total-row">
            <td colspan="5" class="a4-total-label">Discount ($)</td>
            <td class="a4-col-num">-${formatMoneyA4(discount)}</td>
        </tr>` : ""}
        ${vat > 0 ? `
        <tr class="a4-total-row">
            <td colspan="5" class="a4-total-label">VAT ($)</td>
            <td class="a4-col-num">${formatMoneyA4(vat)}</td>
        </tr>` : ""}
        <tr class="a4-total-row a4-grand-row">
            <td colspan="5" class="a4-total-label">Grand Total ($)</td>
            <td class="a4-col-num">${formatMoneyA4(grand)}</td>
        </tr>
    `;

    return `
        <table class="a4-items">
            <thead>
                <tr>
                    <th style="width:6%;">No.</th>
                    <th style="width:38%;">Item</th>
                    <th style="width:12%;">Unit</th>
                    <th style="width:14%;">Quantity</th>
                    <th style="width:14%;">Price</th>
                    <th style="width:16%;">Total</th>
                </tr>
            </thead>
            <tbody>${rows}${totalRows}</tbody>
        </table>
    `;
}

// items table WITHOUT prices (Delivery Note)
function buildA4PlainItemsTable(lines) {
    const rows = (lines || []).map((l, i) => `
        <tr>
            <td>${i + 1}</td>
            <td class="a4-col-item">${l.name || ""}</td>
            <td>${l.unit || ""}</td>
            <td class="a4-col-num">${formatQtyA4(l.quantity)}</td>
        </tr>
    `).join("");

    return `
        <table class="a4-items">
            <thead>
                <tr>
                    <th style="width:8%;">No.</th>
                    <th style="width:56%;">Item</th>
                    <th style="width:18%;">Unit</th>
                    <th style="width:18%;">Quantity</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

// =====================================================
// QUOTATION (A4)
// =====================================================
async function printQuotationA4(header, lines, posInfo) {
    header = header || {};
    const shop = posInfo || {};

    const contactLine = shop.phone1
        ? `please contact us at ${shop.phone1}${shop.email ? ` or ${shop.email}` : ""}.`
        : "please contact us using the information above.";

    const html = `
        ${style_a4_document}
        <div class="a4-page">
            ${buildA4Letterhead(posInfo, "Quotation", "Quotation", header.quotation_no, [
                header.valid_until ? { label: "Due Date", value: fmtDateA4(header.valid_until) } : null,
            ].filter(Boolean))}

            ${buildA4BillTo("Quotation for:", header.customer_name, header.phone, header.address)}

            ${buildA4PricedItemsTable(lines, header)}

            <div class="a4-terms">
                <div class="a4-terms-title">Terms and Conditions</div>
                Delivery: 3 weeks upon receiving PO<br>
                Payment: 100% upon work completion<br>
                Warranty: 3 months with a new spare part replacement and service.
                ${header.remarks ? `<br>Remark: ${header.remarks}` : ""}
            </div>

            <div class="a4-signoff">
                If you have any questions concerning this quotation, ${contactLine}<br>
                <div class="a4-thankyou">THANK YOU FOR YOUR BUSINESS!!</div>
            </div>
        </div>
    `;

    await printA4(html, header.quotation_no);
}

// =====================================================
// INVOICE (A4) — built from a Sale Order
// =====================================================
/**
 * Money for the Khmer invoice, in the DOCUMENT's own currency.
 * Riel (factor > 1) prints with no decimals, rounded to the nearest 100;
 * USD keeps 2 decimals. Mirrors the list/purchase formatters.
 */
function invoiceMoneyKhmer(baseUsd, factor) {
    const rate = Number(factor) || 1;
    const amount = Number(baseUsd ?? 0) * rate;

    if (rate > 1) {
        return (Math.round(amount / 100) * 100).toLocaleString("en-US");
    }
    return amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Items table for the Khmer invoice, in production's column layout
 * (ល.រ / រាយមុខទំនិញ / ឯកតា / ចំនួន / តម្លៃ / តម្លៃសរុប) plus its totals rows.
 *
 * Built from the `lines` array rather than scraping #invoice-table's innerHTML
 * the way the old production script did — that read the LIVE cart, so
 * reprinting a historical order printed whatever happened to be on screen.
 * Same markup and classes, correct data.
 */
function buildKhmerInvoiceTable(header, lines) {
    const f = Number(header.factor) || 1;
    const cur = header.currency_name || (f > 1 ? "៛" : "$");
    const m = (v) => invoiceMoneyKhmer(v, f);

    const body = (lines || []).map((l, i) => {
        const lineTotal = l.grand_total_amount ?? l.sub_total ?? (Number(l.quantity || 0) * Number(l.sell_price || 0));

        // services carry no qty/unit/price — production merges those cells
        if ((l.type ?? "product") === "service") {
            return `
                <tr>
                    <td colspan="5" style="text-align:end; font-weight:bold;">${l.name ?? ""}</td>
                    <td style="text-align:right; font-weight:bold;">${m(lineTotal)}</td>
                </tr>`;
        }

        return `
            <tr>
                <td style="text-align:center;">${i + 1}</td>
                <td style="text-align:start">${l.name ?? ""}</td>
                <td style="text-align:center">${l.unit ?? ""}</td>
                <td style="text-align:center;">${formatQtyA4(l.quantity)}</td>
                <td style="text-align:right;">${m(l.sell_price)}</td>
                <td style="text-align:right;">${m(lineTotal)}</td>
            </tr>`;
    }).join("");

    const totalRow = (labelKh, value) => `
        <tr class="total_print">
            <td colspan="5" style="text-align:right; font-weight:bold;">${labelKh} (${cur})</td>
            <td style="text-align:right; font-weight:bold;">${m(value)}</td>
        </tr>`;

    const subTotal = Number(header.total_amount ?? 0);
    const discount = Number(header.discount_amount ?? 0);
    const vat      = Number(header.vat_amount ?? 0);
    const grand    = Number(header.grand_total ?? 0);
    const deposit  = Number(header.deposit_amount ?? header.paid_amount ?? 0);

    // Being paid is a payment fact, so payment_status decides it. status tracks
    // fulfilment ('Completed' can still be Partial), and is only consulted for
    // payloads that carry no payment_status at all.
    const paymentStatus = String(header.payment_status ?? "").toLowerCase();
    const isPaid = paymentStatus !== ""
        ? paymentStatus === "paid"
        : String(header.status ?? "") === "Completed";

    // production reads the configured rate from totalsDisplay.vat_status, which
    // this schema does not store — recover it from the amount and the subtotal.
    const vatPercent = vat > 0 && subTotal > 0 ? Math.round((vat / subTotal) * 100) : 0;

    // The totals have to read top-to-bottom as arithmetic the customer can
    // follow: subtotal, what came off it, what that leaves, what was paid, what
    // is still owed. Printing the discount BELOW the balance broke that — an
    // invoice showed Sub Total 155,000, Deposit 139,000, Balance 500, with the
    // 15,500 discount that reconciles them stranded underneath.
    const deductionRows =
        (discount !== 0 ? totalRow("បញ្ចុះតម្លៃ/Discount", discount) : "")
        + (vat > 0 ? totalRow(`អាករ/VAT ${vatPercent}%`, vat) : "");

    // Grand Total always prints: it is the figure Deposit and Balance are
    // measured against, so omitting it leaves those two unreconcilable.
    const settlementRows = totalRow("សរុប /Grand Total", grand)
        + (isPaid
            ? ""
            : totalRow("កក់មុន/Deposit", deposit)
              + totalRow("នៅសល់/Balance", grand - deposit));

    return `
        <div id="invoice-table">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <!-- max-width=5% (not :) is production's markup verbatim.
                             It is invalid CSS and therefore ignored, which is what
                             leaves the No. column auto-sized on the client's copy. -->
                        <th style="max-width=5%;">ល.រ</th>
                        <th>រាយមុខទំនិញ</th>
                        <th>ឯកតា</th>
                        <th>ចំនួន</th>
                        <th>តម្លៃ</th>
                        <th>តម្លៃសរុប</th>
                    </tr>
                </thead>
                <tbody>
                    ${body}
                    ${totalRow("សរុប/Sub Total", subTotal)}
                    ${deductionRows}
                    ${settlementRows}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Khmer invoice (វិក្កយបត្រ) — layout, labels and signature blocks ported from
 * POS_DUCK_PRODUCTION's print_document_duck.js so the printed document matches
 * the one the client already uses, including the Taxi Phone line (stored in
 * sale_order_headers.remarks) and Telegram, which the English A4 invoice lacked.
 */
function buildInvoiceHtml(header, lines, posInfo) {
    header = header || {};
    const shop = posInfo || {};

    // .print-page is the full-height flex column that .signature-section's
    // "margin-top: auto" pushes against — without this wrapper the signatures
    // sit directly under the items table instead of at the foot of the page.
    return `
        <div class="print-page">
        <div class="header-center">
            <h2>${shop.company ?? ""}</h2>
            <div>${shop.description ?? ""}</div>
        </div>

        <div class="line"></div>
        <div class="invoice-title">វិក្កយបត្រ</div>
        <div class="line"></div>

        <div class="info-section">
            <div class="info-box">
                <div class="row">
                    <span class="label">ឈ្មោះអតិថិជន:</span>
                    <span class="value">${header.contact_name ?? "General"}</span>
                </div>
                <div class="row">
                    <span class="label">លេខទូរស័ព្ទ:</span>
                    <span class="value">${header.phone ?? ""}</span>
                </div>
                <div class="row">
                    <span class="label">អាសយដ្ឋាន:</span>
                    <span class="value">${header.address ?? ""}</span>
                </div>
                <div class="row">
                    <span class="label">លេខ តាកសុី</span>
                    <span class="value">${header.remarks ?? ""}</span>
                </div>
            </div>

            <!-- "info-bo" (not info-box) is production's markup verbatim. Neither
                 .info-bo nor .right-box has a CSS rule, so this column has no
                 width: 48% and shrinks to its content — that is how the client's
                 current invoice prints. -->
            <div class="info-bo right-box">
                <div class="row">
                    <span class="label">លេខរៀងវិក្កយបត្រ:</span>
                    <span class="value">${header.document_no ?? "-"}</span>
                </div>
                <div class="row">
                    <span class="label">ថ្ងៃ​.ខែ.ឆ្នាំ:</span>
                    <span class="value">${header.posting_date ? fmtDateA4(header.posting_date) : "-"}</span>
                </div>
                <div class="row">
                    <span class="label">លេខទូរស័ព្ទ:</span>
                    <span class="value">${shop.phone1 ?? "-"}</span>
                </div>
                <div class="row">
                    <span class="label"></span>
                    <span class="value">${shop.phone2 ?? "-"}</span>
                </div>
                <div class="row">
                    <span class="label">តេឡេក្រាម:</span>
                    <span class="value">${shop.telegram ?? "-"}</span>
                </div>
            </div>
        </div>

        <br><br><br><br>

        ${buildKhmerInvoiceTable(header, lines)}

        <div class="signature-section">
            <div class="signature-box">
                ------------------<br>
                ហត្ថលេខា<br>
                អ្នកទិញ
            </div>

            <div class="signature-box">
                ------------------<br>
                ហត្ថលេខា<br>
                អ្នកលក់
            </div>
        </div>
        </div>
    `;
}

async function printInvoiceA4(header, lines, posInfo) {
    header = header || {};
    await printA4(
        `<html><head><title></title>${style_invoice_khmer}</head>` +
        `<body>${buildInvoiceHtml(header, lines, posInfo)}</body></html>`,
        header.document_no
    );
}

// =====================================================
// SALE ORDER — full line-by-line breakdown table (A4, landscape),
// mirrors every column shown in the Sale Order Lines detail view.
// =====================================================
async function printSaleOrderFullTableA4(header, lines, posInfo) {
    header = header || {};

    const rows = (lines || []).map((l, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${l.item_code || ""}</td>
            <td class="a4-col-item">${l.name || ""}</td>
            <td class="a4-col-num">${formatQtyA4(l.quantity)}</td>
            <td class="a4-col-num">${formatQtyA4(l.quantity_shiped)}</td>
            <td class="a4-col-num">${formatMoneyA4(l.sell_price)}</td>
            <td class="a4-col-num">${formatMoneyA4(l.sub_total)}</td>
            <td class="a4-col-num">${formatMoneyA4(l.discount_amount)}</td>
            <td class="a4-col-num">${formatMoneyA4(l.vat_amount)}</td>
            <td class="a4-col-num">${formatMoneyA4(l.grand_total_amount)}</td>
        </tr>
    `).join("");

    const sub = parseFloat(header.total_amount) || 0;
    const discount = parseFloat(header.discount_amount) || 0;
    const vat = parseFloat(header.vat_amount) || 0;
    const grand = parseFloat(header.grand_total) || 0;

    const html = `
        ${style_a4_document}
        <style>table.a4-items th, table.a4-items td { font-size: 10px; padding: 5px 4px; }</style>
        <div class="a4-page">
            ${buildA4Letterhead(posInfo, "Sale Order Detail", "Document No", header.document_no)}

            ${buildA4BillTo("Customer:", header.contact_name, header.phone, header.address)}

            <table class="a4-items">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Qty</th>
                        <th>Qty Shipped</th>
                        <th>Price</th>
                        <th>Sub Total</th>
                        <th>Discount</th>
                        <th>VAT</th>
                        <th>Grand Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                    <tr class="a4-total-row">
                        <td colspan="6" class="a4-total-label">Sub Total ($)</td>
                        <td class="a4-col-num">${formatMoneyA4(sub)}</td>
                        <td class="a4-col-num">${formatMoneyA4(discount)}</td>
                        <td class="a4-col-num">${formatMoneyA4(vat)}</td>
                        <td class="a4-col-num"></td>
                    </tr>
                    <tr class="a4-total-row a4-grand-row">
                        <td colspan="9" class="a4-total-label">Grand Total ($)</td>
                        <td class="a4-col-num">${formatMoneyA4(grand)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;

    await printA4(html, header.document_no);
}

// =====================================================
// DELIVERY NOTE (A4) — built from a Sale Order
// =====================================================
function buildDeliveryNoteHtml(header, lines, posInfo) {
    header = header || {};
    const shop = posInfo || {};

    return `
        <div class="a4-page">
            ${buildA4Letterhead(posInfo, "Delivery Note", "Delivery No", header.document_no)}

            ${buildA4BillTo("To:", header.contact_name, header.phone, header.address)}

            ${buildA4PlainItemsTable(lines)}

            <div class="a4-footer-row">
                <div class="a4-footer-block">
                    <div class="a4-footer-title">Posted By</div>
                    <div class="a4-footer-line">Name: <span class="a4-footer-fill">${shop.seller || header.created_by || ""}</span></div>
                    <div class="a4-footer-line">Date: <span class="a4-footer-fill">${fmtDateA4(new Date())}</span></div>
                </div>
                <div class="a4-footer-block">
                    <div class="a4-footer-title">Received By</div>
                    <div class="a4-footer-line">Name: <span class="a4-footer-fill"></span></div>
                    <div class="a4-footer-line">Date: <span class="a4-footer-fill"></span></div>
                </div>
            </div>
        </div>
    `;
}

async function printDeliveryNoteA4(header, lines, posInfo) {
    header = header || {};
    await printA4(style_a4_document + buildDeliveryNoteHtml(header, lines, posInfo), header.document_no);
}

// =====================================================
// PICKING LIST — which bin/lot to pull each item from,
// grouped per bin/lot line (a product can span more than one).
// =====================================================
function buildA4PickingItemsTable(rows) {
    const body = (rows || []).map((r, i) => {
        const isExpired = r.expire_date && new Date(r.expire_date) < new Date();
        return `
        <tr>
            <td>${i + 1}</td>
            <td class="a4-col-item">${r.name || ""}</td>
            <td>${r.item_code || r.barcode || "-"}</td>
            <td class="a4-col-num">${formatQtyA4(Math.abs(r.quantity ?? 0))}</td>
            <td>${r.unit || "-"}</td>
            <td>${r.warehouse_name || "-"}</td>
            <td><b>${r.bin_name || "No Bin"}</b></td>
            <td>${r.lot || "-"}</td>
            <td style="${isExpired ? "color:#dc2626;font-weight:700;" : ""}">${r.expire_date ? fmtDateA4(r.expire_date) : "-"}</td>
        </tr>
    `;
    }).join("");

    return `
        <table class="a4-items">
            <thead>
                <tr>
                    <th style="width:4%;">No.</th>
                    <th style="width:18%;">Item</th>
                    <th style="width:10%;">Code</th>
                    <th style="width:8%;">Qty</th>
                    <th style="width:8%;">Unit</th>
                    <th style="width:14%;">Warehouse</th>
                    <th style="width:13%;">Bin</th>
                    <th style="width:13%;">Lot</th>
                    <th style="width:12%;">Expire</th>
                </tr>
            </thead>
            <tbody>${body || `<tr><td colspan="9" style="text-align:center;color:#9ca3af;">No stock-movement lines found</td></tr>`}</tbody>
        </table>
    `;
}

function buildPickingListHtml(header, rows, posInfo) {
    header = header || {};
    return `
        <div class="a4-page">
            ${buildA4Letterhead(posInfo, "Picking List", "Order No", header.document_no)}
            ${buildA4BillTo("Customer:", header.contact_name, header.phone, header.address)}
            ${buildA4PickingItemsTable(rows)}

            <div class="a4-footer-row">
                <div class="a4-footer-block">
                    <div class="a4-footer-title">Picked By</div>
                    <div class="a4-footer-line">Name: <span class="a4-footer-fill"></span></div>
                    <div class="a4-footer-line">Date: <span class="a4-footer-fill"></span></div>
                </div>
                <div class="a4-footer-block">
                    <div class="a4-footer-title">Checked By</div>
                    <div class="a4-footer-line">Name: <span class="a4-footer-fill"></span></div>
                    <div class="a4-footer-line">Date: <span class="a4-footer-fill"></span></div>
                </div>
            </div>
        </div>
    `;
}

function printPickingListA4(header, rows, posInfo) {
    const html = style_a4_document + buildPickingListHtml(header, rows, posInfo);
    return printA4(html, header?.document_no);
}

// items table for a Purchase Order — unit cost + line total, no VAT/discount
function buildA4PurchaseItemsTable(lines, factor) {
    const f = Number(factor) || 1;
    let subtotal = 0;

    const rows = (lines || []).map((l, i) => {
        const qty = Number(l.quantity ?? 0);
        const unitCost = Number(l.unit_cost ?? 0) * f;
        const amount = Number(l.line_amount ?? qty * (l.unit_cost ?? 0)) * f;
        subtotal += amount;

        return `
            <tr>
                <td>${i + 1}</td>
                <td class="a4-col-item">${l.name || ""}</td>
                <td>${l.unit || ""}</td>
                <td class="a4-col-num">${formatQtyA4(qty)}</td>
                <td class="a4-col-num">${formatMoneyA4(unitCost)}</td>
                <td class="a4-col-num">${formatMoneyA4(amount)}</td>
            </tr>
        `;
    }).join("");

    return { rows, subtotal };
}

// =====================================================
// PURCHASE ORDER (A4)
// =====================================================
async function printPurchaseOrderA4(purchase, posInfo) {
    purchase = purchase || {};
    const factor = Number(purchase.factor ?? 1);
    const currencyName = purchase.currency_name ?? "$";

    const { rows, subtotal } = buildA4PurchaseItemsTable(purchase.lines, factor);
    const deposit = Number(purchase.deposit_amount ?? 0) * factor;
    const balance = subtotal - deposit;

    const html = `
        ${style_a4_document}
        <div class="a4-page">
            ${buildA4Letterhead(posInfo, "Purchase Order", "PO No", purchase.no, [
                { label: "Created By", value: purchase.created_by || "-" },
            ])}

            ${buildA4BillTo("Vendor:", purchase.vendor?.name, purchase.vendor?.phone1, purchase.vendor?.address1)}
            ${purchase.remark ? `<div class="a4-bill-to"><div class="a4-bill-label">Remark:</div><div>${purchase.remark}</div></div>` : ""}

            <table class="a4-items">
                <thead>
                    <tr>
                        <th style="width:6%;">No.</th>
                        <th style="width:38%;">Item</th>
                        <th style="width:12%;">Unit</th>
                        <th style="width:14%;">Quantity</th>
                        <th style="width:14%;">Unit Cost</th>
                        <th style="width:16%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                    <tr class="a4-total-row">
                        <td colspan="5" class="a4-total-label">Sub Total (${currencyName})</td>
                        <td class="a4-col-num">${formatMoneyA4(subtotal)}</td>
                    </tr>
                    <tr class="a4-total-row">
                        <td colspan="5" class="a4-total-label">Deposit (${currencyName})</td>
                        <td class="a4-col-num">${formatMoneyA4(deposit)}</td>
                    </tr>
                    <tr class="a4-total-row a4-grand-row">
                        <td colspan="5" class="a4-total-label">Balance (${currencyName})</td>
                        <td class="a4-col-num">${formatMoneyA4(balance)}</td>
                    </tr>
                </tbody>
            </table>

            <div class="a4-footer-row">
                <div class="a4-footer-block">
                    <div class="a4-footer-title">Requested By</div>
                    <div class="a4-footer-line">Name: <span class="a4-footer-fill"></span></div>
                    <div class="a4-footer-line">Date: <span class="a4-footer-fill"></span></div>
                </div>
                <div class="a4-footer-block">
                    <div class="a4-footer-title">Approved By</div>
                    <div class="a4-footer-line">Name: <span class="a4-footer-fill"></span></div>
                    <div class="a4-footer-line">Date: <span class="a4-footer-fill"></span></div>
                </div>
                <div class="a4-footer-block">
                    <div class="a4-footer-title">Supplier</div>
                    <div class="a4-footer-line">Name: <span class="a4-footer-fill"></span></div>
                    <div class="a4-footer-line">Date: <span class="a4-footer-fill"></span></div>
                </div>
            </div>
        </div>
    `;

    await printA4(html, purchase.no);
}

// =====================================================
// KHMER INVOICE (វិក្កយបត្រ)
// CSS copied verbatim from POS_DUCK_PRODUCTION's print_document_duck.js
// (style_invoice_A4) so the printed invoice matches the document the client
// already uses. Renamed only to avoid colliding with the A4 styles above.
// =====================================================
const style_invoice_khmer = `
<style>
@font-face{
    font-family:'Noto Serif Khmer';
    src:url('/assets/Font/khmer.woff2') format('woff2');
    unicode-range:U+1780-17FF,U+19E0-19FF,U+200C-200D,U+25CC;
}

@font-face{
    font-family:'Noto Serif Khmer';
    src:url('/assets/Font/latinex.woff2') format('woff2');
    unicode-range:U+0100-02BA,U+1E00-1EFF,U+2020,U+20A0-20AB;
}

@page {
    size: A4;
    margin: 12mm;
}

html, body {
    margin: 0;
    padding: 0;
    font-family:'Noto Serif Khmer', serif;
    color: #000;
    font-size: 13px;
}

.print-page {
    width: 100%;
    min-height: 273mm;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    page-break-after: always;
    break-after: page;
}

/* Added on top of production's sheet: the batch printer concatenates several
   pages into one document, so each invoice copy needs its own page break —
   except the last, which would otherwise emit a trailing blank page. */
.print-page:last-child {
    page-break-after: auto;
    break-after: auto;
}

.header-center {
    text-align: center;
    line-height: 1.6;
}

.line {
    border-top: 2px solid #000;
    margin: 10px 0;
}

.invoice-title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
}

.info-section {
    display: flex;
    justify-content: space-between;
    gap: 40px;
    margin-bottom: 10px;
}

.info-box {
    width: 48%;
}

.row {
    display: flex;
    margin-bottom: 4px;
}

.label {
    min-width: 140px;
    font-weight: 600;
}

.value {
    flex: 1;
    word-break: break-word;
}

table {
    width: calc(100% - 1px);
    max-width: calc(100% - 1px);
    border-collapse: collapse;
    table-layout: fixed;
    margin-top: 8px;
    box-sizing: border-box;
}

th, td {
    border: 1px solid #000;
    padding: 5px;
    text-align: center;
    font-size: 12px;
    word-break: break-word;
    box-sizing: border-box;
}

.summary td,
.total_print td {
    text-align: right;
    font-weight: bold;
}

.signature-section {
    margin-top: auto !important;
    display: flex;
    justify-content: space-between;
    text-align: center;
    padding-top: 20px;
}

.signature-box {
    width: 180px;
}

@media print {
    button {
        display: none;
    }
}
</style>
`;
