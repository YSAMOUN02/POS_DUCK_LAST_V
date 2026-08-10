// The purchase currently loaded into the detail modal, shared with
// print_purchase.js. It must be declared: assigning to a bare name creates an
// implicit global, but *reading* one that was never assigned throws, so
// printPurchase()'s own "Please open purchase first" guard died with
// "ReferenceError: currentPurchase is not defined" before it could show.
let currentPurchase = null;

window.addEventListener("DOMContentLoaded", async () => {
    const categorySelect = document.getElementById("category_filter");
    if (!categorySelect) {
        console.error("category_filter not found");
        return;
    }

    // Load categories — shape: [{id:1, name:'APPETIZER'}, ...]
    let categories = [];
    try {
        const response = await fetch("/categories");
        categories = await response.json();
    } catch (error) {
        console.error("Failed to load categories:", error);
        return;
    }

    // keep "All Categories" first, then one option per category
    categorySelect.innerHTML = '<option value="">📂 All Categories</option>';

    categories.forEach((cat) => {
        const option = document.createElement("option");
        option.value = cat.name; // ← NAME, because backend filters lines.category_name
        option.textContent = cat.name;
        categorySelect.appendChild(option);
    });
    console.log("Categories loaded:", categories);
});

const Sales_btn = document.getElementById("Sales");
// Confirm purchasing
Sales_btn.addEventListener("click", () => {
    window.location.href = "/Sale";
});


// Unposted preview of the current purchase cart. The document arrives with its
// own `shop` letterhead attached (same as a posted GRN), so it never depends on
// the page-level pos_profile_for_print, which can be stale.
window.addEventListener("purchase-preview", async (e) => {
    const doc = e.detail?.[0] ?? e.detail ?? {};
    try {
        await printPurchaseOrderA4(doc, doc.shop ?? null);
    } catch (err) {
        console.error("Purchase preview failed:", err);
        showToast({
            message: `Failed to preview purchase — ${err?.name ?? "Error"}: ${err?.message ?? err}`,
            type: "error",
        });
    }
});

window.addEventListener("success", (e) => {
    const detail = e.detail[0];
    showToast({
        message: detail.message,
        type: "success",
    });
    closeGrnModal();

    // The receipt just changed stock, so the cards behind the modal are stale.
    // Reloading the active tab re-fetches them with the new quantities.
    if (typeof window.refreshPurchaseProducts === "function") {
        window.refreshPurchaseProducts();
    }

    if (detail.document_no) {
        openGrnPostedModal(detail.document_no, detail.lines || []);
    }
});

function openGrnPostedModal(documentNo, lines) {
    document.getElementById("grnPostedDocNo").textContent = documentNo;

    const tbody = document.getElementById("grnPostedLines");
    tbody.innerHTML = lines.length
        ? lines
              .map(
                  (l) => `
        <tr>
            <td class="px-3 py-2 font-medium text-gray-800">${l.name ?? ""}</td>
            <td class="px-3 py-2 text-gray-600">${l.variant || "-"}</td>
            <td class="px-3 py-2 text-gray-600">${l.lot || "-"}</td>
            <td class="px-3 py-2 text-gray-600">${l.expire || "NA"}</td>
            <td class="px-3 py-2 text-gray-600">${l.bin || "NA"}</td>
        </tr>
    `,
              )
              .join("")
        : `<tr><td colspan="5" class="px-3 py-4 text-center text-gray-400">No lines</td></tr>`;

    const modal = document.getElementById("grnPostedModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    const printBtn = document.getElementById("grnPostedPrint");
    const skipBtn = document.getElementById("grnPostedSkip");

    function closeModal() {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
        printBtn.removeEventListener("click", onPrint);
        skipBtn.removeEventListener("click", onSkip);
    }

    async function onPrint() {
        try {
            // The Accept header matters: without it Laravel answers an expired
            // session with a 302 to the login page, fetch follows it, and
            // res.json() chokes on HTML — which surfaced as a generic "Failed
            // to print GRN" no matter what actually went wrong. Asking for JSON
            // makes auth/permission failures come back as real status codes.
            const res = await fetch("/fetch-purchase-doc?no=" + encodeURIComponent(documentNo), {
                headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            });

            if (!res.ok) {
                const reason = {
                    401: "your session expired — log in again",
                    403: "you do not have permission to view purchase documents",
                    404: "the document could not be found",
                }[res.status] ?? `server error ${res.status}`;

                // The GRN itself posted fine; only the print lookup failed, so
                // say so — otherwise it reads like the whole receipt was lost.
                showToast({
                    message: `GRN ${documentNo} was posted, but printing failed: ${reason}. Reprint it from the purchase list.`,
                    type: "error",
                });
                return;
            }

            const doc = await res.json();
            if (!doc || !doc.no) {
                showToast({ message: "Could not load GRN for printing", type: "error" });
                return;
            }

            currentPurchase = doc;
            // typeof, not a bare read: an undeclared identifier throws on read,
            // and losing the shop profile should print a plain letterhead, not
            // abort the whole GRN print.
            const posInfo = typeof pos_profile_for_print !== "undefined"
                ? pos_profile_for_print
                : (window.pos_profile_for_print ?? null);

            await printPurchaseOrderA4(currentPurchase, posInfo);
        } catch (err) {
            // Show what actually failed. "Failed to print GRN" on its own says
            // nothing — the throw could be the lookup, the JSON parse or the
            // print itself, and each needs a different fix.
            console.error("GRN print failed:", err);
            showToast({
                message: `Failed to print GRN ${documentNo} — ${err?.name ?? "Error"}: ${err?.message ?? err}`,
                type: "error",
                duration: 12000,
            });
        } finally {
            closeModal();
        }
    }

    function onSkip() {
        closeModal();
    }

    printBtn.addEventListener("click", onPrint);
    skipBtn.addEventListener("click", onSkip);
}

window.addEventListener("app-error", (e) => {
    const message = e.detail[0].message;
    showToast({
        message: message,
        type: "error",
    });
});
// GLOBAL TOAST
let toastTimeout;

function showToast({ message, type = "success", duration = 3000 }) {
    const toast = document.getElementById("toastMessage");
    const text = document.getElementById("toastText");
    const title = document.getElementById("toastTitle");
    const icon = document.getElementById("toastIcon");
    const iconBox = document.getElementById("toastIconBox");

    const styles = {
        success: {
            title: "Success",
            icon: "fa-check",
            box: "bg-emerald-100 text-emerald-600",
        },
        error: {
            title: "Error",
            icon: "fa-xmark",
            box: "bg-rose-100 text-rose-600",
        },
        warning: {
            title: "Warning",
            icon: "fa-triangle-exclamation",
            box: "bg-amber-100 text-amber-600",
        },
    };

    const s = styles[type] || styles.success;

    text.innerText = message;
    title.innerText = s.title;

    icon.className = `fa-solid ${s.icon}`;
    iconBox.className =
        `flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${s.box}`;

    toast.classList.remove("hidden");
    toast.classList.add("animate-[toastIn_.25s_ease-out]");

    if (toastTimeout) clearTimeout(toastTimeout);

    toastTimeout = setTimeout(() => {
        hideToast();
    }, duration);
}

function hideToast() {
    const toast = document.getElementById("toastMessage");
    toast.classList.add("hidden");

    // reset text only — the icon is a Font Awesome <i> rendered via its
    // class (set in showToast), never via text content. Setting innerText
    // here left a stray "✔️" glyph stacked on top of the next toast's icon.
    document.getElementById("toastText").innerText = "";
}

async function addVendor() {
    const form = document.getElementById("AddVendorForm");
    const btn = document.querySelector(
        '#AddVendorForm button[onclick="addVendor()"]',
    );

    const originalText = btn.innerHTML;

    // Disable button
    btn.disabled = true;
    btn.innerHTML = "Saving...";
    btn.classList.add("opacity-50", "cursor-not-allowed");

    const formData = new FormData(form);

    try {
        const response = await fetch("/vendors", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
            },
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            showToast({
                message: "Vendor added successfully!",
                type: "success",
            });
            loadVendors(1);
            form.reset();

            // Optional close modal after success
            // document.querySelector('[data-modal-hide="default-modal-vendor"]').click();
        } else {
            showToast({
                message: data.message || "Failed to add vendor",
                type: "error",
            });
        }
    } catch (error) {
        console.error(error);

        showToast({
            message: "Server error",
            type: "error",
        });
    } finally {
        // Enable button again
        btn.disabled = false;
        btn.innerHTML = originalText;
        btn.classList.remove("opacity-50", "cursor-not-allowed");
    }
}

let vendorSearchTimeout = null;

function handleVendorSearchInput() {
    clearTimeout(vendorSearchTimeout);
    vendorSearchTimeout = setTimeout(() => {
        loadVendors(1);
    }, 400);
}
async function loadVendors(page = 1) {
    const search = document.getElementById("vendorSearchInput")?.value || "";
    const activeOnly = document.getElementById("vendorSearchCheckbox")?.checked
        ? 1
        : 0;
    const tbody = document.getElementById("vendor-table-body");
    const pageInfo = document.getElementById("vendorPageInfo");

    tbody.innerHTML = `
        <tr>
            <td colspan="12" class="text-center px-4 py-6">Loading...</td>
        </tr>
    `;

    try {
        const url = `/vendors/list?page=${page}&search=${encodeURIComponent(search)}&active=${activeOnly}`;

        const response = await fetch(url, {
            headers: {
                Accept: "application/json",
            },
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || "Failed to fetch vendors");
        }

        const vendors = result.data.data || [];
        const pagination = result.data;

        if (vendors.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" class="text-center px-4 py-6 text-gray-500">No vendors found</td>
                </tr>
            `;
        } else {
            tbody.innerHTML = vendors
                .map(
                    (vendor) => `
                <tr class="border-b border-default hover:bg-neutral-secondary-medium"     onclick="selectVendorRow(this, ${vendor.id})" >
           <td class="px-4 py-3">
        <input type="checkbox"
            class="vendor-checkbox pointer-events-none"
            value="${vendor.id}">
    </td>

                    <td class="px-4 py-3">${vendor.id ?? ""}</td>
                    <td class="px-4 py-3">${vendor.code ?? ""}</td>
                    <td class="px-4 py-3">${vendor.name ?? ""}</td>
                    <td class="px-4 py-3">${vendor.contact_person ?? ""}</td>
                    <td class="px-4 py-3">${vendor.phone1 ?? ""}</td>
                    <td class="px-4 py-3">${vendor.phone2 ?? ""}</td>
                    <td class="px-4 py-3">${vendor.email ?? ""}</td>
                    <td class="px-4 py-3">${vendor.country ?? ""}</td>
                    <td class="px-4 py-3">${vendor.city ?? ""}</td>
                    <td class="px-4 py-3">${vendor.website ?? ""}</td>
                    <td class="px-4 py-3">
                        ${
                            vendor.status == 1
                                ? '<span class="text-green-600 font-medium">Active</span>'
                                : '<span class="text-red-600 font-medium">Inactive</span>'
                        }
                    </td>
                </tr>
            `,
                )
                .join("");
        }

        renderVendorPagination(pagination);
        pageInfo.textContent =
            activeOnly == 1
                ? `${pagination.total} Active Vendors  `
                : `${pagination.total} Inactive Vendors  `;
    } catch (error) {
        console.error(error);
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center px-4 py-6 text-red-500">Error loading vendors</td>
            </tr>
        `;
    }
}
function renderVendorPagination(pagination) {
    const container = document.getElementById("vendorPaginationContainer");
    container.innerHTML = "";

    if (!pagination.last_page || pagination.last_page <= 1) return;

    let buttons = "";

    buttons += `
        <button ${pagination.current_page === 1 ? "disabled" : ""}
            onclick="loadVendors(${pagination.current_page - 1})"
            class="page-btn">
            Prev
        </button>
    `;

    for (let i = 1; i <= pagination.last_page; i++) {
        buttons += `
            <button onclick="loadVendors(${i})"
                class="page-btn ${i === pagination.current_page ? "page-btn-active" : ""}">
                ${i}
            </button>
        `;
    }

    buttons += `
        <button ${pagination.current_page === pagination.last_page ? "disabled" : ""}
            onclick="loadVendors(${pagination.current_page + 1})"
            class="page-btn">
            Next
        </button>
    `;

    container.innerHTML = buttons;
}

let selectedVendorId = null;

function selectVendorRow(row, id) {
    // uncheck all
    document.querySelectorAll(".vendor-checkbox").forEach((cb) => {
        cb.checked = false;
    });

    // check current
    const checkbox = row.querySelector(".vendor-checkbox");
    checkbox.checked = true;

    selectedVendorId = id;

    console.log("Selected Vendor ID:", selectedVendorId);
}
function fillEditVendorForm(response) {
    const vendor = response.data ? response.data : response;

    const form = document.getElementById("EditVendorForm");
    if (!form) {
        console.error("EditVendorForm not found");
        return;
    }

    const setValue = (id, value = "") => {
        const el = document.getElementById(id);
        if (!el) {
            console.error(`${id} not found`);
            return;
        }
        el.value = value ?? "";
    };

    setValue("edit_vendor_id", vendor.id);
    setValue("edit_code", vendor.code);
    setValue("edit_name", vendor.name);
    setValue("edit_contact_person", vendor.contact_person);
    setValue("edit_email", vendor.email);
    setValue("edit_phone1", vendor.phone1);
    setValue("edit_phone2", vendor.phone2);
    setValue("edit_country", vendor.country);
    setValue("edit_city", vendor.city);
    setValue("edit_website", vendor.website);
    setValue("edit_address1", vendor.address1);
    setValue("edit_address2", vendor.address2);

    const statusEl = document.getElementById("edit_status");
    if (statusEl) {
        statusEl.checked = Number(vendor.status) === 1;
    } else {
        console.error("edit_status not found");
    }

    console.log("Filled vendor form:", vendor);
}

document
    .getElementById("btnEditvendor")
    .addEventListener("click", openEditVendor);

async function openEditVendor() {
    if (!selectedVendorId) {
        showToast({
            message: "Please select a vendor first.",
            type: "error",
        });

        return;
    }

    try {
        const response = await fetch(`/vendors/${selectedVendorId}`, {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
        });

        const vendor = await response.json();

        if (!response.ok) {
            showToast({
                message: vendor.message || "Vendor not found.",
                type: "error",
            });
            return;
        }
        console.log("Vendor data for editing:", vendor);
        // 🔥 Fill old data into form
        fillEditVendorForm(vendor);

        // or remove hidden manually if needed
    } catch (error) {
        console.error(error);

        showToast({
            message: "Server error.",
            type: "error",
        });
    }
}

async function updateVendor() {
    const form = document.getElementById("EditVendorForm");
    const vendorId = document.getElementById("edit_vendor_id").value;
    const btn = document.querySelector(
        '#EditVendorForm button[onclick="updateVendor()"]',
    );

    if (!vendorId) {
        showToast({
            message: "Vendor ID not found.",
            type: "error",
        });
        return;
    }

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = "Updating...";
    btn.classList.add("opacity-50", "cursor-not-allowed");

    const formData = new FormData(form);
    formData.append("_method", "PUT");

    try {
        const response = await fetch(`/vendors/${vendorId}`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
            },
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            showToast({
                message: "Vendor updated successfully!",
                type: "success",
            });

            loadVendors(1);

            // optional close modal
            // document.querySelector('[data-modal-hide="default-modal-edit-vendor"]').click();
        } else {
            showToast({
                message: data.message || "Failed to update vendor",
                type: "error",
            });
        }
    } catch (error) {
        console.error(error);
        showToast({
            message: "Server error",
            type: "error",
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
        btn.classList.remove("opacity-50", "cursor-not-allowed");
    }
}

// async function FetchPurchase(page = 1) {
let currentLinePage = 1;

function purchaseLineFilters() {
    const p = new URLSearchParams();
    const v = (id) => { const el = document.getElementById(id); return el ? el.value.trim() : ""; };
    if (v("from_date"))       p.set("from_date", v("from_date"));
    if (v("to_date"))         p.set("to_date", v("to_date"));
    if (v("doc_filter"))      p.set("doc_filter", v("doc_filter"));
    if (v("vendor_search"))   p.set("search", v("vendor_search"));
    if (v("product_search"))  p.set("product_filter", v("product_search"));
    if (v("variant_filter"))  p.set("variant_filter", v("variant_filter"));
    if (v("lot_filter"))      p.set("lot_filter", v("lot_filter"));
    if (v("category_filter")) p.set("category_filter", v("category_filter"));
    if (v("limit_filter"))    p.set("limit", v("limit_filter"));
    const cb = document.getElementById("returns_only");
    if (cb && cb.checked) p.set("returns_only", "1");
    return p;
}

function loadPurchases(page = 1) {
    currentLinePage = page;
    const params = purchaseLineFilters();
    params.set("page", page);
    fetch("/fetch-purchase-lines?" + params.toString())
        .then((r) => r.json())
        .then((res) => {
            renderPurchaseLinesTable(res.data);
            renderPurchaseLinesPagination(res);
        })
        .catch((err) => console.error("loadPurchases failed:", err));
}
function renderPurchaseLinesTable(rows) {
    const tbody = document.getElementById("purchaseTableBody");
    tbody.innerHTML = "";

    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="17" class="px-4 py-12 text-center text-slate-400">No lines match these filters.</td></tr>`;
        return;
    }

    const limit = parseInt(document.getElementById("limit_filter")?.value) || 100;
    const startNo = (currentLinePage - 1) * limit;

    rows.forEach((r, i) => {
        const isReturn = Number(r.quantity) < 0;
        const factor = Number(r.factor || 1);
        const lineTotal = Number(r.line_amount) * factor;
        const unitCost = Number(r.unit_cost) * factor;
        const safeNo = (r.document_no ?? "").replace(/'/g, "\\'");

        // show currency only when there's a real value (hides the "0.00 ៛" noise)
        const sym = lineTotal !== 0 ? ` ${r.currency_name ?? ""}` : "";

        const zebra = isReturn ? "bg-rose-50" : (i % 2 ? "bg-slate-50/70" : "bg-white");
        const cell = "px-3 py-2 border-b border-slate-100 align-middle";

        const tr = document.createElement("tr");
        tr.className = `${zebra} transition hover:bg-cyan-50/70`;
        tr.innerHTML = `
            <td class="${cell} text-center text-slate-400">${startNo + i + 1}</td>
            <td class="${cell} whitespace-nowrap text-slate-500">${r.posting_date ?? ""}</td>
            <td class="${cell} whitespace-nowrap font-semibold text-slate-800">${r.document_no ?? ""}</td>
            <td class="${cell} max-w-[160px] truncate" title="${r.vendor_name ?? ""}">${r.vendor_name ?? ""}</td>
            <td class="${cell} whitespace-nowrap font-mono text-xs text-slate-500">${r.item_code ?? ""}</td>
            <td class="${cell} max-w-[240px] truncate" title="${r.name ?? ""}">${r.name ?? ""}</td>
            <td class="${cell} text-slate-500">${r.variant ?? ""}</td>
            <td class="${cell} whitespace-nowrap font-medium">${r.lot ?? ""}</td>
            <td class="${cell} whitespace-nowrap text-slate-500">${r.expire_date ?? ""}</td>
            <td class="${cell} text-right font-semibold ${isReturn ? "text-rose-600" : "text-slate-800"}">${formatQty(r.quantity)}</td>
            <td class="${cell} text-slate-500">${r.unit ?? ""}</td>
            <td class="${cell} text-right text-slate-600">${formatMoneyPlain(unitCost, factor)}</td>
            <td class="${cell} text-right font-semibold ${isReturn ? "text-rose-600" : "text-slate-800"}">${formatMoneyPlain(lineTotal, factor)}${sym}</td>
            <td class="${cell} text-slate-500">${r.category_name ?? ""}</td>
            <td class="${cell} max-w-[150px] truncate text-slate-400" title="${r.remark ?? ""}">${r.remark ?? ""}</td>
            <td class="${cell} whitespace-nowrap text-xs text-slate-400">${r.created_by ?? ""}</td>
            <td class="${cell} text-center">
                <button onclick="viewPurchaseDoc('${safeNo}')"
                    class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700 whitespace-nowrap">
                    View
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}
// View button → fetch the full document, open your EXISTING modal (return flow intact)
function viewPurchaseDoc(no) {
    if (!no) return;
    fetch("/fetch-purchase-doc?no=" + encodeURIComponent(no))
        .then((r) => r.json())
        .then((doc) => {
            if (doc && doc.no) openPurchaseLineModal(doc);
            else console.warn("Document not found:", no);
        })
        .catch((err) => console.error("viewPurchaseDoc failed:", err));
}
function renderPurchaseLinesPagination(meta) {
    const container = document.getElementById("paginationContainer_purchase");
    const pageInfo = document.getElementById("pageInfo_purchase");
    container.innerHTML = "";
    pageInfo.textContent = `Page ${meta.current_page} of ${meta.last_page} | ${meta.total} lines`;

    const btn = (label, page, disabled, active) =>
        `<button onclick="loadPurchases(${page})" ${disabled ? "disabled" : ""}
          class="page-btn ${active ? "page-btn-active" : ""}">${label}</button>`;

    container.innerHTML += btn("Prev", meta.current_page - 1, meta.current_page === 1, false);
    let start = Math.max(1, meta.current_page - 2);
    let end = Math.min(meta.last_page, meta.current_page + 2);
    if (start > 1) {
        container.innerHTML += btn("1", 1, false, false);
        if (start > 2) container.innerHTML += `<span class="px-2">...</span>`;
    }
    for (let i = start; i <= end; i++) container.innerHTML += btn(i, i, false, i === meta.current_page);
    if (end < meta.last_page) {
        if (end < meta.last_page - 1) container.innerHTML += `<span class="px-2">...</span>`;
        container.innerHTML += btn(meta.last_page, meta.last_page, false, false);
    }
    container.innerHTML += btn("Next", meta.current_page + 1, meta.current_page === meta.last_page, false);
}

// live typing → debounced
["vendor_search", "product_search", "variant_filter", "lot_filter", "doc_filter",""].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    let t;
    el.addEventListener("input", () => { clearTimeout(t); t = setTimeout(() => loadPurchases(1), 400); });
});
["category_filter","returns_only" , "limit_filter" , "from_date" , "to_date"].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    let t;
    el.addEventListener("change", () => { clearTimeout(t); t = setTimeout(() => loadPurchases(1), 400); });
});


// Which currency the Purchase Details modal is showing. factor 1 = USD.
let purchaseViewCurrency = { factor: 1, currency: "$" };

/**
 * Currency a purchase should open in.
 *
 * A Khmer-speaking buyer works in riel, so opening every GRN in USD meant
 * toggling on each one. Uses the document's OWN factor, not today's rate, so an
 * old receipt still reads at the rate it was bought at.
 */
function defaultPurchaseCurrency(purchase) {
    const factor = Number(purchase?.factor || 1);
    const isKhmer = typeof app_locale !== "undefined" && app_locale === "km";

    if (isKhmer && factor > 1) {
        return { factor, currency: purchase?.currency_name || "៛" };
    }
    return { factor: 1, currency: "$" };
}

function togglePurchaseCurrency() {
    if (!currentPurchase) return;

    const docFactor = Number(currentPurchase.factor || 1);
    const docCurrency = currentPurchase.currency_name || "៛";

    purchaseViewCurrency =
        purchaseViewCurrency.factor === 1 && docFactor > 1
            ? { factor: docFactor, currency: docCurrency }
            : { factor: 1, currency: "$" };

    renderPurchaseLineModal(currentPurchase);
}

function openPurchaseLineModal(purchase) {
    currentPurchase = purchase;
    purchaseViewCurrency = defaultPurchaseCurrency(purchase);
    renderPurchaseLineModal(purchase);
}

function renderPurchaseLineModal(purchase) {
    const modal = document.getElementById("purchaseLineModal");
    modal.classList.remove("hidden");

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.innerText = value || "-";
    };

    setText("purchase-no", purchase.no);
    setText("purchase-created-by", purchase.created_by);
    setText("purchase-posting-date", purchase.posting_date);
    setText("purchase-remark", purchase.remark);
    setText("purchase-vendor", purchase.vendor?.name);
    setText("purchase-vendor-id", purchase.vendor_id);

    // Which site received the goods and at what rate — the two facts that make
    // one receipt different from another with the same items.
    setText("purchase-warehouse", purchase.location_name);
    setText("purchase-payment-method", purchase.payment_method);
    setText(
        "purchase-currency",
        Number(purchase.factor) > 1
            ? `1$ = ${Number(purchase.factor).toLocaleString("en-US")} ${purchase.currency_name ?? ""}`.trim()
            : (purchase.currency_name ?? "$"),
    );

    const tbody = document.getElementById("purchase-line-data");
    tbody.innerHTML = "";

    let totalQty = 0;
    let totalAmount = 0;

    // Amounts are stored in base USD; the view currency decides what they are
    // multiplied by, so the same document can be read either way.
    const factor = Number(purchaseViewCurrency.factor) || 1;
    const currency_name = purchaseViewCurrency.currency ?? "$";

    // The toggle offers whichever currency is NOT on screen. A purchase saved in
    // dollars has no second currency, so the button is hidden entirely.
    const docFactor = Number(purchase.factor || 1);
    const toggleBtn = document.getElementById("btn-toggle-purchase-currency");
    if (toggleBtn) {
        toggleBtn.style.display = docFactor > 1 ? "inline-flex" : "none";
        const lbl = document.getElementById("purchase-currency-toggle-label");
        if (lbl) {
            lbl.textContent =
                factor === 1
                    ? `View in ${purchase.currency_name || "៛"}`
                    : "View in $";
        }
    }
    (purchase.lines ?? []).forEach((line, index) => {
        totalQty += Number(line.quantity ?? 0);
        totalAmount += Number(line.line_amount ?? 0);

        tbody.innerHTML += `
            <tr class="hover:bg-gray-50 text-nowrap">
                <td class="px-4 py-3">${index + 1}</td>
                <td class="px-4 py-3">${line.item_code ?? ""}</td>
                <td class="px-4 py-3">${line.barcode ?? ""}</td>
                <td class="px-4 py-3 font-medium">${line.name ?? ""}</td>
                <td class="px-4 py-3">${line.variant ?? ""}</td>
                <td class="px-4 py-3 max-w-[260px] whitespace-normal">${line.description ?? ""}</td>
                <td class="px-4 py-3">${line.category_name ?? ""}</td>
                <td class="px-4 py-3">${line.lot ?? ""}</td>
                <td class="px-4 py-3">${line.expire_date ?? ""}</td>
                <td class="px-4 py-3 text-right">${formatQty(line.quantity)}</td>
                <td class="px-4 py-3">${line.unit ?? ""}</td>
             <td class="px-4 py-3 text-right">
                ${formatMoneyPlain(line.unit_cost * factor, factor)} ${currency_name}
            </td>

            <td class="px-4 py-3 text-right">
                ${formatMoneyPlain(line.line_amount * factor, factor)} ${currency_name}
            </td>
                <td class="px-4 py-3 max-w-[220px] whitespace-normal">${line.remark ?? ""}</td>
            </tr>
        `;
    });

    // Qty
    document.getElementById("purchase-total-qty").innerText =
        formatQty(totalQty);

    setText("purchase-total-lines", String((purchase.lines ?? []).length));

    // Grand total follows the same view currency as the lines above it.
    document.getElementById("purchase-grand-total").innerText =
        formatMoneyPlain(totalAmount * factor, factor) + " " + currency_name;
}

function closePurchaseLineModal() {
    document.getElementById("purchaseLineModal").classList.add("hidden");
}
/**
 * Purchase-side money formatter.
 *
 * `value` is the amount ALREADY converted to the document's currency; `factor`
 * says which currency that is. A factor above 1 means Riel, which is shown
 * without decimals and rounded to the nearest 100 — cash is never handled in
 * single riel, and "5 500 000.00៛" was both wrong and unreadable. USD keeps its
 * 2 decimals.
 *
 * @param {number|string} value    amount in the document's own currency
 * @param {number|string} [factor] that document's factor (1 = USD)
 */
function formatMoneyPlain(value, factor = 1) {
    let amount = parseFloat(String(value ?? 0).replace(/,/g, ""));
    if (isNaN(amount)) amount = 0;

    const isRiel = Number(factor) > 1;

    if (isRiel) {
        const rounded = Math.round(amount / 100) * 100;
        return rounded.toLocaleString("en-US").replace(/,/g, " ");
    }

    return amount
        .toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
        .replace(/,/g, " ");
}

window.addEventListener("cart-cleared", (e) => {
    current_discount = 0;
    messsage = e.detail[0].message;
    showToast({
        message: messsage,
        type: "success",
    });

    document.querySelector("#vendorValue").value = "";
    document.querySelector("#vendorSearch").value = "";
});

function formatQty(value) {
    return Number(value ?? 0)
        .toFixed(6)
        .replace(/\.?0+$/, "");
}

function formatMoney(value, factor = 1) {
    const amount = Number(value ?? 0) * Number(factor ?? 1);

    // 3dp in USD to match the purchase cart: per-unit costs are quoted to a
    // tenth of a cent. Trailing zeros are stripped, so whole amounts still
    // show as "12", not "12.000".
    return Number(factor) === 1
        ? amount.toFixed(3).replace(/\.?0+$/, "")
        : amount.toFixed(0);
}
function formatQty(value) {
    return Number(value ?? 0)
        .toFixed(6)
        .replace(/\.?0+$/, "");
}

// default today
const today = todayLocal();

document.getElementById("grnDate").value = today;

// The GRN modal serves both actions. Preview shows the same date field and the
// same cart, but swaps Confirm for Preview so nothing on screen can post stock.
function setGrnModalMode(previewOnly) {
    document.getElementById("grnModalTitle").textContent = previewOnly
        ? "Preview"
        : "Confirm Purchase";
    document.getElementById("grnModalSubtitle").textContent = previewOnly
        ? "Preview this cart — nothing is posted"
        : "Please select GRN date before posting purchase";
    document.getElementById("grnConfirmBtn").classList.toggle("hidden", previewOnly);
    document.getElementById("grnPreviewBtn").classList.toggle("hidden", !previewOnly);
}

function showGrnModal(previewOnly) {
    let count_cart_input = document.querySelector("#count_cart_input");

    if (count_cart_input.value == 0) {
        showToast({
            message: "សូមជ្រើសរើស ទំនិញជាមុនសិន",
            type: "error",
        });
        return;
    }

    setGrnModalMode(previewOnly);
    document.getElementById("grnModal").classList.remove("hidden");
}

function openGrnModal() {
    showGrnModal(false);
}

function openGrnPreviewModal() {
    showGrnModal(true);
}

function todayLocal() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
}

function closeGrnModal() {
    document.getElementById("grnModal").classList.add("hidden");
}

function confirmGrn() {
    const date = document.getElementById("grnDate").value;

    if (!date) {
        showToast({
            message: "សូមជ្រើសរើស ថ្ងៃ ខែ​ ឆ្នាំ",
            type: "error",
        });
        return;
    }

    // 🔥 send to Livewire
    Livewire.find(
        document.querySelector("[wire\\:id]").getAttribute("wire:id"),
    ).set("grn_date", date);

    // call function
    Livewire.find(
        document.querySelector("[wire\\:id]").getAttribute("wire:id"),
    ).call("post_grn");
}

// Same date handling as confirmGrn, but calls previewPurchase — which writes
// nothing and prints the cart — and closes the modal itself, since there is no
// posted document to report back.
function confirmGrnPreview() {
    const date = document.getElementById("grnDate").value;

    if (!date) {
        showToast({
            message: "សូមជ្រើសរើស ថ្ងៃ ខែ​ ឆ្នាំ",
            type: "error",
        });
        return;
    }

    const component = Livewire.find(
        document.querySelector("[wire\\:id]").getAttribute("wire:id"),
    );

    component.set("grn_date", date);
    component.call("previewPurchase");

    closeGrnModal();
}

window.addEventListener("close-grn-modal", () => {
    closeGrnModal();
});

// download button
document.getElementById("downloadPurchase").addEventListener("click", function () {
    const params =purchaseLineFilters();
    window.location = "/export-purchase?" + params.toString();
});

/* ============================================================
   PURCHASE RETURN  ·  location-first flow
   - openReturnModal(): fetch all returnable lines for the GRN
   - build a Location dropdown from the distinct warehouses returned
   - retRenderLocation(): show only the chosen location's lots
   - confirmReturn(): post checked rows (location is implicit in each key)
   Requires existing helpers: showToast, todayLocal, formatQty,
   formatMoneyPlain, closePurchaseLineModal, loadPurchases, currentPage.
============================================================ */

let currentReturnGrnNo = null;
let retAllLines = []; // every returnable line from the backend (all locations)

async function openReturnModal() {
    const grnNo = document.getElementById("purchase-no").innerText.trim();
    if (!grnNo || grnNo === "-") {
        showToast({ message: "No GRN selected.", type: "error" });
        return;
    }
    currentReturnGrnNo = grnNo;

    // reset UI
    document.getElementById("ret-grn-no").innerText = grnNo;
    document.getElementById("ret-vendor").innerText =
        document.getElementById("purchase-vendor").innerText || "-";
    document.getElementById("ret-date").value = todayLocal();
    document.getElementById("ret-reason").value = "";
    document.getElementById("ret-pick-prompt").classList.remove("hidden");
    document.getElementById("ret-table-wrap").classList.add("hidden");
    document.getElementById("ret-empty").classList.add("hidden");
    document.getElementById("ret-line-data").innerHTML = "";

    const locSel = document.getElementById("ret-location");
    locSel.innerHTML = '<option value="">Loading…</option>';

    document.getElementById("purchaseReturnModal").classList.remove("hidden");

    try {
        const res = await fetch(
            "/purchase-return/returnable?no=" + encodeURIComponent(grnNo),
        );
        const data = await res.json();
        if (!res.ok)
            throw new Error(data.error || data.message || "Failed to load");

        retAllLines = data.lines || [];

        if (retAllLines.length === 0) {
            locSel.innerHTML = '<option value="">— none —</option>';
            document.getElementById("ret-pick-prompt").classList.add("hidden");
            const empty = document.getElementById("ret-empty");
            empty.innerText =
                data.message || "No returnable stock in your locations.";
            empty.classList.remove("hidden");
            return;
        }

        // build distinct location list from the returned lines
        const seen = new Map();
        retAllLines.forEach((l) => {
            if (!seen.has(l.warehouse_id)) {
                seen.set(
                    l.warehouse_id,
                    l.warehouse_name || "WH#" + l.warehouse_id,
                );
            }
        });

        locSel.innerHTML = '<option value="">— Select location —</option>';
        seen.forEach((name, id) => {
            const opt = document.createElement("option");
            opt.value = id;
            opt.textContent = name;
            locSel.appendChild(opt);
        });

        // if only one location, auto-select it
        if (seen.size === 1) {
            locSel.value = [...seen.keys()][0];
            retRenderLocation();
        }
    } catch (e) {
        console.error(e);
        locSel.innerHTML = '<option value="">— error —</option>';
        document.getElementById("ret-pick-prompt").classList.add("hidden");
        const empty = document.getElementById("ret-empty");
        empty.innerText = e.message;
        empty.classList.remove("hidden");
    }
}

// render only the lots for the chosen location
function retRenderLocation() {
    const locId = document.getElementById("ret-location").value;
    const wrap = document.getElementById("ret-table-wrap");
    const prompt = document.getElementById("ret-pick-prompt");
    const empty = document.getElementById("ret-empty");
    const tbody = document.getElementById("ret-line-data");

    document.getElementById("ret-select-all").checked = false;
    empty.classList.add("hidden");

    if (!locId) {
        wrap.classList.add("hidden");
        prompt.classList.remove("hidden");
        return;
    }

    const rows = retAllLines.filter(
        (l) => String(l.warehouse_id) === String(locId),
    );
    const locName = rows.length ? rows[0].warehouse_name || "WH#" + locId : "";
    document.getElementById("ret-loc-name").innerText = locName;

    prompt.classList.add("hidden");

    if (rows.length === 0) {
        wrap.classList.add("hidden");
        empty.innerText = "Nothing returnable at this location.";
        empty.classList.remove("hidden");
        return;
    }

    tbody.innerHTML = rows
        .map(
            (l) => `
        <tr class="hover:bg-gray-50 text-nowrap" data-key="${l.key}" data-remaining="${l.remaining}">
            <td class="px-3 py-3 text-center">
                <input type="checkbox" class="ret-check" onchange="retRowToggle(this)">
            </td>
            <td class="px-3 py-3">
                <div class="font-medium">${l.name ?? ""}</div>
                <div class="text-xs text-gray-400">${l.item_code ?? ""}</div>
            </td>
            <td class="px-3 py-3">${l.lot ?? "—"}</td>
            <td class="px-3 py-3">
             ${
                 l.expire_date
                     ? new Date(l.expire_date).toLocaleDateString("en-GB")
                     : "—"
             }
            </td>
            <td class="px-3 py-3 text-right font-semibold">${formatQty(l.remaining)} ${l.unit ?? ""}</td>
            <td class="px-3 py-3 text-right">
                <input type="number" min="0.01" step="0.01" value="" disabled
                    class="ret-qty w-24 rounded-lg border px-2 py-1 text-right text-sm outline-none focus:ring-2 focus:ring-rose-300 disabled:bg-gray-100"
                  oninput="retClampQty(this)" onblur="retClampQtyBlur(this)">
            </td>
          <td class="px-3 py-3 text-right">
                ${formatMoneyPlain ? formatMoneyPlain(+l.unit_cost || 0) : +l.unit_cost || 0}
                <span class="text-xs text-gray-400 ml-1"> USD</span>
            </td>
        </tr>
    `,
        )
        .join("");

    wrap.classList.remove("hidden");
}

function closeReturnModal() {
    document.getElementById("purchaseReturnModal").classList.add("hidden");
}

// enable/disable qty + price box on check; default qty to full remaining
function retRowToggle(cb) {
    const tr = cb.closest("tr");
    const qty = tr.querySelector(".ret-qty");
    qty.disabled = !cb.checked;
    if (cb.checked) {
        qty.value = tr.getAttribute("data-remaining");
        qty.focus();
    } else {
        qty.value = "";
    }
}
function retToggleAll(master) {
    document.querySelectorAll("#ret-line-data .ret-check").forEach((cb) => {
        cb.checked = master.checked;
        retRowToggle(cb);
    });
}

// cap only — runs live while typing (unchanged)
function retClampQty(input) {
    const tr = input.closest("tr");
    const max = parseFloat(tr.getAttribute("data-remaining")) || 0;
    let v = parseFloat(input.value);
    if (isNaN(v) || v < 0) return;
    if (v > max) input.value = max;
}

const RETURN_QTY_MIN = 0.01;

// round to 0.01 + cap — runs when the user leaves the field
function retClampQtyBlur(input) {
    const tr = input.closest("tr");
    const max = parseFloat(tr.getAttribute("data-remaining")) || 0;
    if (input.value.trim() === "") return;

    let v = parseFloat(input.value);
    if (isNaN(v) || v <= 0) {
        input.value = 0;
        return;
    }

    v = Math.round(v * 100) / 100; // force 0.01 step
    if (v < RETURN_QTY_MIN) v = RETURN_QTY_MIN; // floor — never a dust quantity
    if (v > max) v = max; // never exceed remaining

    input.value = v;
}
async function confirmReturn() {
    const locId = document.getElementById("ret-location").value;
    if (!locId) {
        showToast({ message: "Please select a location.", type: "error" });
        return;
    }
    const date = document.getElementById("ret-date").value;
    if (!date) {
        showToast({ message: "Please pick a return date.", type: "error" });
        return;
    }
    const reason = document.getElementById("ret-reason").value.trim();
    if (!reason) {
        showToast({
            message: "Please enter a reason for the return.",
            type: "error",
        });
        return;
    }

    const items = [];
    document.querySelectorAll("#ret-line-data tr").forEach((tr) => {
        const cb = tr.querySelector(".ret-check");
        if (!cb || !cb.checked) return;
        const key = tr.getAttribute("data-key");
        let qty = parseFloat(tr.querySelector(".ret-qty").value);
        if (qty > 0 && qty < RETURN_QTY_MIN) qty = RETURN_QTY_MIN;
        if (key && qty > 0) items.push({ key, qty });
    });

    if (items.length === 0) {
        showToast({
            message: "Select at least one item and enter a qty.",
            type: "error",
        });
        return;
    }

    const btn = document.getElementById("ret-confirm-btn");
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = "Processing…";

    try {
        const res = await fetch("/purchase-return/confirm", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
            },
            body: JSON.stringify({
                no: currentReturnGrnNo,
                return_date: date,
                reason,
                items,
            }),
        });
        const data = await res.json();
        if (!res.ok || !data.success)
            throw new Error(data.message || "Return failed");

        showToast({ message: data.message, type: "success" });
        closeReturnModal();
        closePurchaseLineModal();
  loadPurchases(currentLinePage);
    } catch (e) {
        console.error(e);
        showToast({ message: e.message, type: "error" });
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}
