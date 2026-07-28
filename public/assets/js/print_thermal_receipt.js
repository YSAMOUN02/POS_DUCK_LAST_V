// =====================================================
// PRINTER SETUP MODAL — tap to select, saved locally
// =====================================================
const PRINT_SETUP_KEY = "pos_print_setup"; // {printer: "...", copies: 1|2}
let printSetup = null;

function showPrintSetupModal(printerList) {
    return new Promise((resolve, reject) => {
        // remove any stale modal
        document.getElementById("print-setup-overlay")?.remove();

        const overlay = document.createElement("div");
        overlay.id = "print-setup-overlay";
        overlay.style.cssText = `
            position:fixed; inset:0; background:rgba(0,0,0,.55);
            display:flex; align-items:center; justify-content:center;
            z-index:99999; font-family:inherit;
        `;

        const printerBtns = printerList.map((p, i) => `
            <button type="button" class="ps-printer-btn" data-printer="${p.replace(/"/g, "&quot;")}"
                style="display:block; width:100%; text-align:left; padding:12px 14px; margin-bottom:8px;
                       border:2px solid #d1d5db; border-radius:10px; background:#fff; font-size:15px;
                       cursor:pointer;">
                🖨️ ${p}
            </button>
        `).join("");

        overlay.innerHTML = `
            <div style="background:#fff; border-radius:16px; padding:22px; width:92%; max-width:420px;
                        max-height:85vh; overflow-y:auto; box-shadow:0 20px 50px rgba(0,0,0,.3);">
                <div style="font-size:18px; font-weight:700; margin-bottom:4px;">Printer Setup</div>
                <div style="font-size:13px; color:#6b7280; margin-bottom:14px;">
                    One-time setup for this machine
                </div>

                <div style="font-size:14px; font-weight:600; margin-bottom:8px;">1. Select receipt printer</div>
                <div id="ps-printer-list">${printerBtns}</div>

                <div style="font-size:14px; font-weight:600; margin:14px 0 8px;">2. Copies per order</div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="ps-copies-btn" data-copies="1"
                        style="flex:1; padding:12px; border:2px solid #d1d5db; border-radius:10px;
                               background:#fff; font-size:15px; cursor:pointer;">
                        1 copy
                    </button>
                    <button type="button" class="ps-copies-btn" data-copies="2"
                        style="flex:1; padding:12px; border:2px solid #d1d5db; border-radius:10px;
                               background:#fff; font-size:15px; cursor:pointer;">
                        2 copies<br><span style="font-size:11px; color:#6b7280;">shop + customer</span>
                    </button>
                </div>

                <button type="button" id="ps-save"
                    style="width:100%; margin-top:18px; padding:13px; border:none; border-radius:10px;
                           background:#9ca3af; color:#fff; font-size:16px; font-weight:700; cursor:not-allowed;"
                    disabled>
                    Save & Print
                </button>
                <button type="button" id="ps-cancel"
                    style="width:100%; margin-top:8px; padding:10px; border:none; border-radius:10px;
                           background:transparent; color:#6b7280; font-size:14px; cursor:pointer;">
                    Cancel
                </button>
            </div>
        `;
        document.body.appendChild(overlay);

        let selPrinter = null;
        let selCopies = null;
        const saveBtn = overlay.querySelector("#ps-save");

        const refreshSave = () => {
            const ready = selPrinter && selCopies;
            saveBtn.disabled = !ready;
            saveBtn.style.background = ready ? "#7c3aed" : "#9ca3af";
            saveBtn.style.cursor = ready ? "pointer" : "not-allowed";
        };

        overlay.querySelectorAll(".ps-printer-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                overlay.querySelectorAll(".ps-printer-btn").forEach(b => {
                    b.style.borderColor = "#d1d5db"; b.style.background = "#fff";
                });
                btn.style.borderColor = "#7c3aed"; btn.style.background = "#f3e8ff";
                selPrinter = btn.dataset.printer;
                refreshSave();
            });
        });

        overlay.querySelectorAll(".ps-copies-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                overlay.querySelectorAll(".ps-copies-btn").forEach(b => {
                    b.style.borderColor = "#d1d5db"; b.style.background = "#fff";
                });
                btn.style.borderColor = "#7c3aed"; btn.style.background = "#f3e8ff";
                selCopies = parseInt(btn.dataset.copies, 10);
                refreshSave();
            });
        });

        saveBtn.addEventListener("click", () => {
            overlay.remove();
            resolve({ printer: selPrinter, copies: selCopies });
        });

        overlay.querySelector("#ps-cancel").addEventListener("click", () => {
            overlay.remove();
            reject(new Error("Printer setup cancelled"));
        });
    });
}

async function ensurePrintSetup() {
    if (printSetup) return printSetup;

    // load + verify saved
    try {
        const saved = JSON.parse(localStorage.getItem(PRINT_SETUP_KEY) || "null");
        if (saved && saved.printer && saved.copies) {
            const found = await qz.printers.find(saved.printer);
            if (found) {
                saved.printer = Array.isArray(found) ? found[0] : found;
                printSetup = saved;
                return printSetup;
            }
        }
    } catch (e) { /* stale/missing — re-setup */ }
    localStorage.removeItem(PRINT_SETUP_KEY);

    // list printers
    let all;
    try {
        all = await qz.printers.find();
    } catch (e) {
        throw new Error("No printers found — check printer is plugged in and installed");
    }
    const list = Array.isArray(all) ? all : [all];
    if (list.length === 0) {
        throw new Error("No printers found — check printer is plugged in and installed");
    }

    // tap-to-select modal
    printSetup = await showPrintSetupModal(list);
    localStorage.setItem(PRINT_SETUP_KEY, JSON.stringify(printSetup));
    return printSetup;
}

function resetPrintSetup() {
    localStorage.removeItem(PRINT_SETUP_KEY);
    printSetup = null;
    qzPrinterConfig = null;
}

// =====================================================
// THERMAL PRINTING via QZ Tray (client-side USB printer)
// Requires in <head>:
//   <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
//   <script src="https://cdnjs.cloudflare.com/ajax/libs/qz-tray/2.2.4/qz-tray.js"></script>
// QZ Tray must be installed + running on each POS client PC
// =====================================================

// ---- QZ security (self-signed / LAN mode) ----

let qzPrinterConfig = null;
let qzSecuritySet = false;
// path to installer hosted on your Laravel server
const QZ_INSTALLER_URL = "/assets/printer/qz-tray-2.2.6-x86_64.exe";

async function qzEnsure() {
    if (typeof qz === "undefined") {
        throw new Error("QZ Tray library not loaded (check qz-tray.js script tag)");
    }
    if (!qzSecuritySet) {
        qz.security.setCertificatePromise((resolve) => resolve());
        qz.security.setSignaturePromise(() => (resolve) => resolve());
        qzSecuritySet = true;
    }
    if (!qz.websocket.isActive()) {
        try {
            await qz.websocket.connect();
        } catch (e) {
            // can't reach QZ Tray → probably not installed (or not running)
            showQzInstallModal();
            throw new Error("QZ Tray is not running");
        }
    }
    if (!qzPrinterConfig) {
        const setup = await ensurePrintSetup();
        qzPrinterConfig = qz.configs.create(setup.printer);
    }
}

function showQzInstallModal() {
    document.getElementById("qz-install-overlay")?.remove();

    const overlay = document.createElement("div");
    overlay.id = "qz-install-overlay";
    overlay.style.cssText = `
        position:fixed; inset:0; background:rgba(0,0,0,.55);
        display:flex; align-items:center; justify-content:center;
        z-index:99999;
    `;
    overlay.innerHTML = `
        <div style="background:#fff; border-radius:16px; padding:24px; width:92%; max-width:420px;
                    box-shadow:0 20px 50px rgba(0,0,0,.3); text-align:center;">
            <div style="font-size:34px; margin-bottom:6px;">🖨️</div>
            <div style="font-size:18px; font-weight:700; margin-bottom:8px;">QZ Tray not detected</div>
            <div style="font-size:14px; color:#374151; line-height:1.6; margin-bottom:16px; text-align:left;">
                Receipt printing needs the QZ Tray program running on this computer.<br><br>
                <b>If already installed:</b> start it from the Desktop/Start menu, then print again.<br>
                <b>If not installed:</b> download below, run the installer, then print again.
            </div>
            <a href="${QZ_INSTALLER_URL}" download
                style="display:block; padding:13px; border-radius:10px; background:#7c3aed;
                       color:#fff; font-size:16px; font-weight:700; text-decoration:none;">
                ⬇ Download QZ Tray Installer
            </a>
            <button type="button" onclick="this.closest('#qz-install-overlay').remove()"
                style="width:100%; margin-top:8px; padding:10px; border:none; background:transparent;
                       color:#6b7280; font-size:14px; cursor:pointer;">
                Close
            </button>
        </div>
    `;
    document.body.appendChild(overlay);
}

// ---- Render HTML → PNG (supersampled + binarized) → QZ Tray ----
async function printThermal(html, docNo) {
    if (typeof html2canvas === "undefined") {
        alert("Print failed: html2canvas.min.js not loaded");
        return;
    }

    let box = document.getElementById("thermal-render-box");
    if (box) box.remove();
    box = document.createElement("div");
    box.id = "thermal-render-box";
    box.style.cssText = "position:fixed; left:-9999px; top:0; width:512px; background:#fff; color:#000;";
    box.innerHTML = html;
    document.body.appendChild(box);

    await document.fonts.ready;
    await Promise.all(Array.from(box.querySelectorAll("img")).map(img =>
        img.complete ? Promise.resolve() : new Promise(r => { img.onload = r; img.onerror = r; })
    ));

    // 1) render at 2x for sharp glyph shapes
    const big = await html2canvas(box, { width: 512, scale: 3, backgroundColor: "#ffffff" });
    box.remove();

    // 2) downscale to printer dot width 576
    const canvas = document.createElement("canvas");
    canvas.width = 512;
    canvas.height = Math.round(big.height / 3);
    const ctx = canvas.getContext("2d");
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "high";
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(big, 0, 0, canvas.width, canvas.height);

    // 3) binarize: every pixel → pure black or pure white (no dithering)
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const d = imgData.data;
    const THRESHOLD = 190; // raise = darker/bolder print, lower = lighter
    for (let i = 0; i < d.length; i += 4) {
        const lum = d[i] * 0.299 + d[i + 1] * 0.587 + d[i + 2] * 0.114;
        const v = lum < THRESHOLD ? 0 : 255;
        d[i] = d[i + 1] = d[i + 2] = v;
        d[i + 3] = 255;
    }
    ctx.putImageData(imgData, 0, 0);

    const base64 = canvas.toDataURL("image/png").split(",")[1];

    // dev preview mode (optional, from Option A)
    if (localStorage.getItem("pos_print_preview") === "1") {
        const w = window.open("");
        w.document.write(
            `<title>Receipt Preview ${docNo}</title>` +
            `<body style="margin:0;background:#888;display:flex;justify-content:center;padding:20px;">` +
            `<img style="background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.4);" src="data:image/png;base64,${base64}">` +
            `</body>`
        );
        return;
    }


    try {
        await qzEnsure();
        const copies = (printSetup && printSetup.copies) || 1;

        const printerName = (printSetup?.printer || "").toLowerCase();
        const isVirtual = /pdf|onenote|xps|fax/.test(printerName);

        const job = isVirtual
            ? [{ type: "pixel", format: "image", flavor: "base64", data: base64 }]
            : [
                {
                    type: "raw", format: "image", flavor: "base64", data: base64,
                    options: { language: "ESCPOS", dotDensity: "double" }
                },
                "\x1B\x64\x04",
                "\x1D\x56\x42\x00"
            ];

        for (let i = 0; i < copies; i++) {
            await qz.print(qzPrinterConfig, job);
        }
    } catch (e) {
        qzPrinterConfig = null;
        printSetup = null;
        if (e.message !== "QZ Tray is not running") {
            alert("Print failed: " + (e.message || e));
        }
    }
}
// ---- styles for the 576px render box ----
let style_thermal_576 = `
<style>
    #thermal-render-box, #thermal-render-box * {
        box-sizing: border-box;
        font-family: 'Noto Serif Khmer', serif;
        font-weight: 700;
        color: #000;
        -webkit-font-smoothing: none;
    }
    #thermal-render-box {
        font-size: 18px;
        padding: 4px;
    }
    #thermal-render-box table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        margin-top: 6px;
    }
    #thermal-render-box th, #thermal-render-box td {
        border: none;
        border-bottom: 1px solid #000;      /* horizontal lines only */
        padding: 6px 4px;
        font-size: 16px;
        overflow: hidden;
        word-wrap: break-word;
        text-align: left;
    }
    #thermal-render-box thead th {
        border-top: 2px solid #000;
        border-bottom: 2px solid #000;      /* strong header rules */
        white-space: nowrap;
    }
    /* numeric columns align right */
    #thermal-render-box th:nth-child(3), #thermal-render-box td:nth-child(3),
    #thermal-render-box th:nth-child(5), #thermal-render-box td:nth-child(5),
    #thermal-render-box th:nth-child(6), #thermal-render-box td:nth-child(6),
    #thermal-render-box th:nth-child(7), #thermal-render-box td:nth-child(7) {
        text-align: right;
    }
    #thermal-render-box th:nth-child(4), #thermal-render-box td:nth-child(4) { text-align: center; }

    #thermal-render-box th:nth-child(1), #thermal-render-box td:nth-child(1) { width: 7%; }
    #thermal-render-box th:nth-child(2), #thermal-render-box td:nth-child(2) { width: 35%; font-size: 14px; }
    #thermal-render-box th:nth-child(3), #thermal-render-box td:nth-child(3) { width: 11%; }
    #thermal-render-box th:nth-child(4), #thermal-render-box td:nth-child(4) { width: 11%; }
    #thermal-render-box th:nth-child(5), #thermal-render-box td:nth-child(5) { width: 12%; }
    #thermal-render-box th:nth-child(6), #thermal-render-box td:nth-child(6) { width: 9%; }
    #thermal-render-box th:nth-child(7), #thermal-render-box td:nth-child(7) { width: 15%; }

    /* logo: force to solid black so binarize doesn't wash it out */
    #thermal-render-box img {
        max-width: 110px;
        height: auto;
        filter: grayscale(1) contrast(400%) brightness(0.55);
    }
</style>
`;
function formatMoneyPlain(value) {
    let amount = parseFloat(String(value ?? 0).replace(/,/g, ""));
    if (isNaN(amount)) amount = 0;
    return amount
        .toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        .replace(/,/g, " ");
}
let style_invoice_A4 = `
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

let style_invoice_A5 = `
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
    size: A5;
    margin: 8mm;
}

html, body {
    margin: 0;
    padding: 0;
 font-family:'Noto Serif Khmer', serif;
    color:#000;
    font-size:10px; /* 🔥 smaller base */
}

.print-page {
    width: 100%;
    min-height: 190mm; /* A5 usable height */
    display: flex;
    flex-direction: column;
}

.header-center{
    text-align:center;
    line-height:1.3;
}

.line{
    border-top:1px solid #000;
    margin:5px 0;
}

.invoice-title{
    text-align:center;
    font-size:14px; /* 🔥 reduced */
    font-weight:bold;
}

.info-section {
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin-bottom:5px;
}

.info-box {
    width:50%;
}

.row {
    display:flex;
    margin-bottom:2px;
}

.label {
    min-width: 90px; /* 🔥 smaller */
    font-weight:600;
    font-size:10px;
}

.value {
    flex:1;
    word-break:break-word;
    font-size:10px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:5px;
}

th,td{
    border:1px solid #000;
    padding:2px; /* 🔥 tighter */
    text-align:center;
    font-size:9px; /* 🔥 smaller table */
}

.summary td{
    text-align:right;
    font-weight:bold;
}

.signature-section {
    margin-top:auto;
    display:flex;
    justify-content:space-between;
    text-align:center;
    padding-top:15px;
}

.signature-box{
    width:120px;
    font-size:10px;
}

@media print{
    button{display:none}
}

</style>
`;
let style_reciept = `

  <style>

                        @page {
                            size: 80mm auto;
                            margin: 0 !important;
                        }

                        * {
                            margin: 0 !important;
                            padding: 0 !important;
                            box-sizing: border-box;
                            font-family: 'Noto Serif Khmer', serif;
                        }
                        /* Khmer */
                        @font-face {
                            font-family: 'Noto Serif Khmer';
                            font-style: normal;
                            font-weight: 400;
                            font-stretch: 100%;
                            font-display: swap;
                            src: url('/assets/Font/khmer.woff2') format('woff2');
                            unicode-range: U+1780-17FF, U+19E0-19FF, U+200C-200D, U+25CC;
                        }

                        /* Latin Extended */
                        @font-face {
                            font-family: 'Noto Serif Khmer';
                            font-style: normal;
                            font-weight: 400;
                            font-stretch: 100%;
                            font-display: swap;
                            src: url('/assets/Font/latinex.woff2') format('woff2');
                            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
                        }

                        /* Latin */
                        @font-face {
                            font-family: 'Noto Serif Khmer';
                            font-style: normal;
                            font-weight: 400;
                            font-stretch: 100%;
                            font-display: swap;
                            src: url('/assets/Font/latin.woff2') format('woff2');
                            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
                        }

                        html, body {
                            width: 80mm !important;
                            max-width: 80mm !important;
                            font-family: 'Noto Serif Khmer', serif;
                            font-size:11px;
                            color: black !important;
                            font-weight: normal;


                        }
                         img {
                              image-rendering: pixelated; /* tries to make logos sharper */
                            }
                        body {
                            padding: 3mm !important; /* tiny inner safe padding */
                        }

                    table {
                        width: 100%;
                        table-layout: fixed;   /* <-- important */
                        border-collapse: collapse;
                    }

                    th, td {
                        overflow: hidden;
                        text-overflow: ellipsis;
                        word-wrap: break-word;
                    }



                        th:nth-child(1), td:nth-child(1) { width: 8%; }
                        th:nth-child(2), td:nth-child(2) { width: 34%;     font-size: 8px; }
                        th:nth-child(3), td:nth-child(3) { width: 12%; }
                        th:nth-child(4), td:nth-child(4) { width: 10%; }
                        th:nth-child(5), td:nth-child(5) { width: 13%; }
                        th:nth-child(6), td:nth-child(6) { width: 10%; }
                        th:nth-child(7), td:nth-child(7) { width: 15%; }

                         th{
                            text-wrap: nowrap;
                         }

                        th, td {
                            border: 1px solid #00000050;
                            padding: 1px 2px !important;
                            font-size: 10px;

                            color: black !important;
                        }
                        .font-mid{
                            font-size: 11px;
                            color: black !important
                        }
                            .rc-head { text-align:center; margin-bottom:5px; }
                        .rc-logo img { max-width:50px; margin:0 auto; display:block; }
                        .rc-shop-name { font-size:13px; font-weight:bold; margin-top:3px; }
                        .rc-sm { font-size:9px; line-height:1.3; }

                        .rc-title { text-align:center; font-size:13px; font-weight:bold; margin:5px 0 2px; letter-spacing:.5px; }
                        .rc-dash { border-top:1px dashed #000; margin:4px 0; }

                        .rc-meta { display:flex; justify-content:space-between; gap:8px; font-size:9px; line-height:1.4; margin:3px 0; }
                        .rc-meta-l { text-align:left; max-width:55%; }
                        .rc-meta-r { text-align:right; white-space:nowrap; }

                        .rc-totals { margin-top:6px; font-size:10px; }
                        .rc-row { display:flex; justify-content:space-between; padding:1px 0; }
                        .rc-grand { font-size:12px; font-weight:bold; border-top:1px solid #000; margin-top:3px; padding-top:3px; }
                        .rc-riel { font-size:11px; font-weight:bold; }

                        .rc-thanks { text-align:center; font-size:10px; font-weight:bold; margin-top:8px; border-top:1px dashed #000; padding-top:6px; }
                        .rc-logo img, div[style*="text-align:center"] img { max-width: 55px; height: auto; }
                        </style>
`;




// =====================================================
// RECEIPT DESIGN V2 — modern layout (black header bars)
// usage: await print_document_v2(document_type, header, posInfo)
// =====================================================
// =====================================================
// RECEIPT DESIGN V2 — modern layout (black header bars)
// =====================================================
// Builds the same items-table markup as the live cart's #invoice-table
// (No./Item/QTY/Unit/Price/Dis/Total + Sub Total/Discount/Grand Total rows)
// from a plain {header, lines} data set — used when reprinting a saved
// sale order, where there's no live cart DOM to scrape from.
function buildReceiptTableFromLines(lines, header) {
    const money = (v) => formatMoneyPlain(v);

    const rows = (lines || []).map((l, i) => {
        const subTotal = parseFloat(l.sub_total ?? (l.quantity * l.sell_price)) || 0;
        const discountAmt = parseFloat(l.discount_amount) || 0;
        const discountPct = subTotal > 0 ? (discountAmt / subTotal) * 100 : 0;

        return `
        <tr>
            <td style="text-align:center;">${i + 1}</td>
            <td style="text-align:start">${l.name || ""}</td>
            <td style="text-align:center;">${l.quantity}</td>
            <td style="text-align:center">${l.unit || ""}</td>
            <td style="text-align:right;">${money(l.sell_price)}</td>
            <td style="text-align:right;">${discountPct ? discountPct.toFixed(0) : 0}%</td>
            <td style="text-align:right;">${money(l.grand_total_amount ?? subTotal)}</td>
        </tr>`;
    }).join("");

    const sub = parseFloat(header.total_amount) || 0;
    const discount = parseFloat(header.discount_amount) || 0;
    const grand = parseFloat(header.grand_total) || 0;
    const currency = header.currency_name || "$";

    return `
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="max-width=5%;">No.</th>
                    <th>Item</th>
                    <th>QTY</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Dis</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
                <tr class="total_print">
                    <td colspan="6" style="text-align:right;">Sub Total (${currency})</td>
                    <td style="text-align:right;">${money(sub)}</td>
                </tr>
                <tr class="total_print">
                    <td colspan="6" style="text-align:right;">Discount (${currency})</td>
                    <td style="text-align:right;">${money(discount)}</td>
                </tr>
                <tr class="total_print">
                    <td colspan="6" style="text-align:right;">Grand Total (${currency})</td>
                    <td style="text-align:right;">${money(grand)}</td>
                </tr>
            </tbody>
        </table>
    `;
}

async function print_document_v2(document_type, header, posInfo, lines = null) {

    const fmtDate = (d) => d
        ? new Date(d).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })
        : "-";

    const dateStr = fmtDate(header.posting_date);
    const dueStr = fmtDate(header.delivery_date);
    const docNo = header.document_no || "-";

    const custName = (header.contact_name && header.contact_name.trim()) ? header.contact_name : "General";
    const custPhone = header.phone || "";
    const custAddr = header.address || "";
    const shop = posInfo || {};

    const logoEl = document.getElementById("logo_80mm");

    // ---- items table HTML: from an explicit `lines` array when reprinting
    // a saved sale order (no live cart DOM to scrape), otherwise from the
    // on-screen #invoice-table (the "just completed this sale" flow) ----
    let rawTableHtml;
    if (Array.isArray(lines)) {
        rawTableHtml = buildReceiptTableFromLines(lines, header);
    } else {
        const tableEl = document.getElementById("invoice-table");
        if (!tableEl) {
            alert("Print failed: receipt elements not found on page");
            return;
        }
        rawTableHtml = tableEl.innerHTML;
    }


    let logoHtml = "";
    try {
        const logoSrc = shop.logo_url || logoEl?.querySelector("img")?.src;
        if (!logoSrc) throw new Error("no logo configured");
        const logoData = await prepareThermalImage(logoSrc, 150, 245);
        logoHtml = `<img src="${logoData}" style="width:150px;">`;
    } catch (e) {
        logoHtml = "";
    }

    // ---- split items table from summary rows ----
    const tmp = document.createElement("div");
    tmp.innerHTML = rawTableHtml;
    const totals = [];
    tmp.querySelectorAll("tr").forEach(tr => {
        const txt = (tr.textContent || "").trim();
        if (/sub\s*total|discount|grand\s*total/i.test(txt)) {
            const cells = tr.querySelectorAll("td, th");
            if (cells.length) {
                const value = cells[cells.length - 1].textContent.trim();
                // label = all cells except last, joined (safe even if value is "0")
                const label = Array.from(cells).slice(0, -1)
                    .map(c => c.textContent.trim()).filter(Boolean).join(" ")
                    || txt.slice(0, txt.length - value.length).trim();
                totals.push({ label, value, grand: /grand/i.test(txt) });
            }
            tr.remove();
        }
    });
    const itemsTable = tmp.innerHTML;
    const subRows = totals.filter(t => !t.grand).map(t => `
        <tr><td class="v2-tl">${t.label}</td><td class="v2-tv">${t.value}</td></tr>
    `).join("");
    const grand = totals.find(t => t.grand);
        // near the top of print_document_v2, after docNo:
    const rate = header.factor ? Number(header.factor) : 0;
    // cash received + change (from header, not DOM)
    const grandNum = parseFloat(header.grand_total) || 0;
    const paidNum  = parseFloat(header.paid_amount) || 0;
    const changeUsd = paidNum - grandNum;
    const changeKhr = (rate > 0) ? Math.round((changeUsd * rate) / 100) * 100 : 0;

   const cashRows = (() => {
        if (paidNum <= 0) {
            // nothing paid yet — show balance due instead of change
            return grandNum > 0 ? `
                <tr><td class="v2-tl" style="border-top:1px dashed #000; padding-top:6px;">Paid</td>
                    <td class="v2-tv" style="border-top:1px dashed #000; padding-top:6px;">$${formatMoneyPlain(0)}</td></tr>
                <tr><td class="v2-tl">Balance Due</td>
                    <td class="v2-tv" style="font-weight:800;">$${formatMoneyPlain(grandNum)}</td></tr>
            ` : "";
        }
        if (changeUsd < 0) {
            // partial payment
            return `
                <tr><td class="v2-tl" style="border-top:1px dashed #000; padding-top:6px;">Paid</td>
                    <td class="v2-tv" style="border-top:1px dashed #000; padding-top:6px;">$${formatMoneyPlain(paidNum)}</td></tr>
                <tr><td class="v2-tl">Balance Due</td>
                    <td class="v2-tv" style="font-weight:800;">$${formatMoneyPlain(-changeUsd)}</td></tr>
            `;
        }
        // full payment (or overpay) — normal cash + change
        return `
            <tr><td class="v2-tl" style="border-top:1px dashed #000; padding-top:6px;">Cash Received</td>
                <td class="v2-tv" style="border-top:1px dashed #000; padding-top:6px;">$${formatMoneyPlain(paidNum)}</td></tr>
            <tr><td class="v2-tl">Change</td>
                <td class="v2-tv">$${formatMoneyPlain(changeUsd)}${rate > 0 ? ` / ${changeKhr.toLocaleString("en-US")}៛` : ""}</td></tr>
        `;
    })();




   const khrTotal = (rate > 0 && grand)
    ? (() => {
        const usd = parseFloat(String(grand.value).replace(/[^0-9.-]/g, "")) || 0;
        const khr = Math.round((usd * rate) / 100) * 100;
        return `<tr><td class="v2-tl">Total (៛)</td>
               <td class="v2-tv" style="font-size:18px; font-weight:800;">${khr.toLocaleString("en-US")}៛</td></tr>`;
      })()
    : "";
    const html = `
        ${style_thermal_v2}

        <div class="v2-top">
            <div class="v2-logo">${logoHtml}</div>
            <div class="v2-shop">
                <div class="v2-shopname">${shop.company || "Your Company"}</div>
                ${shop.address1 ? `<div class="v2-line">${shop.address1}</div>` : ""}
                ${shop.address2 ? `<div class="v2-line">${shop.address2}</div>` : ""}
                ${shop.phone1 ? `<div class="v2-line">${shop.phone1}</div>` : ""}
                ${shop.email ? `<div class="v2-line">${shop.email}</div>` : ""}
                ${shop.seller ? `<div class="v2-line">Seller: ${shop.seller}</div>` : ""}
            </div>
        </div>

        <div class="v2-title-rule">RECEIPT</div>

        <div class="v2-banner-row">
            <div class="v2-meta">
                <div class="v2-mrow"><span class="v2-mlabel">DATE</span><span>: ${dateStr}</span></div>
                <div class="v2-mrow"><span class="v2-mlabel">RECEIPT NO</span><span>: ${docNo}</span></div>
                <div class="v2-mrow"><span class="v2-mlabel">CASHIER</span><span>: ${header.created_by || "-"}</span></div>
            </div>
        </div>

       ${(custName !== "General" || custPhone || custAddr || header.payment_method) ? `
        <div class="v2-cust" style="display:flex; justify-content:space-between; gap:10px;">
            <div>
                <b>Customer:</b> ${custName}${custPhone ? ` · ${custPhone}` : ""}${custAddr ? `<br>${custAddr}` : ""}
            </div>
            <div style="white-space:nowrap; text-align:right;">
                <b>Payment:</b> ${header.payment_method || "-"}
            </div>
        </div>` : ""}

        <div class="v2-items">${itemsTable}</div>
        ${(subRows || grand) ? `
        <div class="v2-totalbox">
           <table class="v2-ttable">
                ${subRows}
                ${grand ? `
                <tr class="v2-grow">
                    <td class="v2-tl v2-gl2">${grand.label}</td>
                    <td class="v2-tv v2-gv2">${grand.value}</td>
                </tr>` : ""}
                ${khrTotal}
                ${cashRows}

            </table>
        </div>` : ""}


          <div class="v2-thanksrow">
            <div class="v2-thanks">
                <div class="v2-thanks-script">Thank you!</div>
                <div class="v2-thanks-sub">We appreciate your business.</div>
            </div>
        </div>

        <div class="v2-footer">${shop.company || "Your Company"}</div>
        <div class="v2-footer-sub">Thank you for shopping with us</div>
    `;

    await printThermal(html, docNo);
}

const _thermalImgCache = {};

async function prepareThermalImage(src, targetWidth, threshold) {
    const key = src + "|" + targetWidth + "|" + threshold;
    if (_thermalImgCache[key]) return _thermalImgCache[key];

    const img = new Image();
    img.crossOrigin = "anonymous";
    await new Promise((res, rej) => {
        img.onload = res;
        img.onerror = rej;
        img.src = src;
    });

    const ratio = targetWidth / img.naturalWidth;
    const c = document.createElement("canvas");
    c.width = targetWidth;
    c.height = Math.round(img.naturalHeight * ratio);
    const ctx = c.getContext("2d");
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(img, 0, 0, c.width, c.height);

    const id = ctx.getImageData(0, 0, c.width, c.height);
    const d = id.data;
    for (let i = 0; i < d.length; i += 4) {
        const a = d[i + 3] / 255;
        const r = d[i] * a + 255 * (1 - a);
        const g = d[i + 1] * a + 255 * (1 - a);
        const b = d[i + 2] * a + 255 * (1 - a);
        const lum = r * 0.299 + g * 0.587 + b * 0.114;
        const v = lum < threshold ? 0 : 255;
        d[i] = d[i + 1] = d[i + 2] = v;
        d[i + 3] = 255;
    }
    ctx.putImageData(id, 0, 0);

    const out = c.toDataURL("image/png");
    _thermalImgCache[key] = out;
    return out;
}


// keep old name working
async function prepareThermalLogo(src, targetWidth = 130, threshold = 235) {
    return prepareThermalImage(src, targetWidth, threshold);
}


// ---- V2 styles (512px render box) ----
// ---- V2 styles (512px render box) ----
let style_thermal_v2 = `
<style>
    #thermal-render-box, #thermal-render-box * {
        box-sizing: border-box;
        font-family: 'Noto Serif Khmer', serif;
        color: #000;
        -webkit-font-smoothing: none;
    }
    #thermal-render-box { font-size: 16px; padding: 4px; }

    /* header: logo + shop info */
    .v2-top { display:flex; gap:12px; align-items:center; margin-bottom:10px; }
    .v2-logo { flex:0 0 auto; }
    .v2-shop { flex:1; }
    .v2-shopname { font-size:21px; font-weight:800; margin-bottom:4px; }
    .v2-line { font-size:15px; font-weight:700; line-height:1.5; }

    /* plain bold title with thin rules, no fill blocks */
    .v2-title-rule {
        text-align:center;
        font-size:20px; font-weight:800; letter-spacing:3px;
        border-top:3px solid #000; border-bottom:1px solid #000;
        padding:6px 0;
        margin-top:10px;
    }
    .v2-banner-row {
        display:flex; align-items:center; justify-content:center;
        margin:8px 0;
        width:100%;
    }
    .v2-meta { flex:0 0 auto; font-size:14px; font-weight:700; }
    .v2-mrow { display:flex; line-height:1.7; }
    .v2-mlabel { display:inline-block; min-width:105px; font-weight:800; }

    .v2-cust { font-size:15px; font-weight:700; margin:6px 0; }

    /* items table: black header, everything centered */
    .v2-items table {
        width:100%; table-layout:fixed; border-collapse:collapse; margin-top:6px;
    }
    .v2-items th {
        background:none;
        border:none;
        border-top:3px solid #000;
        border-bottom:3px solid #000;
        padding:7px 3px;
        font-size:16px; font-weight:800;
        letter-spacing:1px;
        white-space:nowrap;
        text-align:center; vertical-align:middle;
    }
    .v2-items th, .v2-items th * { color:#000 !important; }
    .v2-items td {
        border:none; border-bottom:1px dashed #000;
        padding:8px 3px; font-size:14px; font-weight:700;
        overflow:hidden; word-wrap:break-word;
        text-align:center; vertical-align:middle;
    }
    /* item name column reads better left-aligned */
    .v2-items td:nth-child(2) { text-align:left; }

    .v2-items th:nth-child(1), .v2-items td:nth-child(1) { width:5%; }
    .v2-items th:nth-child(2), .v2-items td:nth-child(2) { width:37%; }
    .v2-items td:nth-child(2) { font-size:13px; }
    .v2-items th:nth-child(3), .v2-items td:nth-child(3) { width:11%; }
    .v2-items th:nth-child(4), .v2-items td:nth-child(4) { width:11%; }
    .v2-items th:nth-child(5), .v2-items td:nth-child(5) { width:12%; }
    .v2-items th:nth-child(6), .v2-items td:nth-child(6) { width:9%; }
    .v2-items th:nth-child(7), .v2-items td:nth-child(7) { width:15%; }

    /* totals — table layout (flex wraps in html2canvas) */
    .v2-totalbox {
        border:2px solid #000; border-radius:6px;
        padding:6px 10px; margin-top:10px;
    }
    .v2-ttable { width:100%; border-collapse:collapse; table-layout:fixed; }
    .v2-ttable td { border:none; padding:4px 2px; font-size:15px; font-weight:700; }
    .v2-tl { text-align:left; width:65%; }
    .v2-tv { text-align:right; width:35%; }

    .v2-grow td { border-top:2px solid #000; padding-top:8px; }
    .v2-gl2 { font-size:17px; font-weight:800; }
    .v2-gv2 { font-size:24px; font-weight:800; }
    .v2-gtable td { border:none; padding:10px 14px; vertical-align:middle; }
    .v2-gtable td, .v2-gtable td * { color:#fff !important; }



    /* thanks centered */
    .v2-thanks { text-align:center; margin-top:14px; }
    .v2-thanks-script { font-size:26px; font-weight:800; font-style:italic; }
    .v2-thanks-sub { font-size:13px; font-weight:700; }
   /* The QR code was removed, so this row holds only the thank-you block —
      centred rather than sharing the width with an image. */
   .v2-thanksrow { display:flex; align-items:center; justify-content:center; margin-top:14px; }
    .v2-thanks { flex:1; text-align:center; }

    /* footer, plain rule instead of a black fill bar */
    .v2-footer {
        text-align:center;
        font-size:14px; font-weight:800;
        border-top:2px solid #000;
        margin-top:12px;
        padding-top:8px;
    }
    .v2-footer-sub {
        text-align:center;
        font-size:11px; font-weight:600;
        color:#374151;
        padding-bottom:6px;
    }
</style>
`;
