// DEVELOP BY Y SAMOUN IT EXECUTIVE
let pendingSaleOrderStatus = null;
let pendingSaleOrderButton = null;
let isSavingSaleOrder = false;

// DEVELOP AT 2025-2026
// ASSISTED BY CHAT GPT

// INFO BEFORE BEGIN

// LIST =  DATA FETCH AND RENDER

// ADD = CREATE NEW OBJECT

// UPDATE = UPDATE EXISTING OBJECT

// DELETE = DELETE CURRENT OBJECT

// OBJECT  -->
// PRODUCT  // CUSTOMER // CURRENCY  // WAREHOUSE // WAREHOUSE PRODUCT // TABLE AND QUOTE  // TABLE PRODUCT
//    reloadProducts();
// ADD CUSTOMER
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("AddcustomerForm");
    const submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener("submit", async function (e) {
        e.preventDefault();

        submitBtn.disabled = true;
        submitBtn.innerText = "Saving...";

        try {
            const response = await fetch("/customers/store", {
                method: "POST",
                headers: {
                    Accept: "application/json",
                },
                body: new FormData(form),
            });

            const data = await response.json();

            if (!response.ok) {
                throw data;
            }

            // ✅ SUCCESS
            showToast({
                message: data.message || "Customer created successfully",
                type: "success",
            });

            if (window.customerCreateContext === "quotation" && data.customer) {
                applyQuotationCustomer(data.customer);
                window.customerCreateContext = null;
            } else {
                loadCustomers(1);
            }

            form.reset();

            document.getElementById("default-modal-customer")?.classList.add(
                "hidden",
            );
            document
                .querySelector('[data-modal-hide="default-modal-customer"]')
                ?.click();
        } catch (err) {
            // ❌ VALIDATION ERRORS
            if (err.errors) {
                Object.values(err.errors).forEach((msgs) => {
                    showToast({
                        message: msgs[0],
                        type: "error",
                    });
                });
            } else {
                showToast({
                    message: "Server error. Please try again.",
                    type: "error",
                });
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = "Save Customer";
        }
    });
});

// GLOBAL VARIABLE ID CUSTOMER SELETED  FOR UPDATE DELETE
let selectedCustomerId = null;
// UPDATE CUSTOMER
function getSelectedCustomerId() {
    const selected = document.querySelector(
        'input[name="customer_id"]:checked',
    );
    selectedCustomerId = selected ? selected.value : null; // store it
    return selectedCustomerId;
}

// UPDATE CUSTOMER

document.getElementById("btnEditCustomer").addEventListener("click", () => {
    openUpdateCustModal();
});

function getSelectedCustomerId() {
    const selected = document.querySelector(
        'input[name="customer_id"]:checked',
    );
    selectedCustomerId = selected ? selected.value : null;
    return selectedCustomerId;
}

// EDIT BUTTON
const btnEditCustomer = document.getElementById("btnEditCustomer");

if (btnEditCustomer) {
    btnEditCustomer.addEventListener("click", () => {
        openUpdateCustModal();
    });
}

// CLOSE UPDATE MODAL
function closeUpdateCustModal() {
    const modal = document.getElementById("confirm-update-cust");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

// CLOSE DELETE MODAL
function closeDeleteCustModal() {
    const modal = document.getElementById("confirm-delete-cust");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}
function closeDeleteCustModal() {
    document.getElementById("confirm-delete-cust").classList.add("hidden");
}

// UPDATE CUSTOMER
// Hook CLOSE button
document.getElementById("btnEditCustomer").addEventListener("click", () => {
    openUpdateCustModal();
});

async function openUpdateCustModal(customerId = null) {
    customerId = customerId || getSelectedCustomerId();

    if (!customerId) {
        showToast({
            message: "Please select a customer first",
            type: "error",
        });
        return;
    }

    selectedCustomerId = customerId;

    const res = await fetch(`/customers/${customerId}`, {
        headers: {
            Accept: "application/json",
        },
    });

    const data = await res.json();
    const c = data.customer ?? data;
    document.getElementById("cust-name").value = c.name ?? "";
    document.getElementById("cust-phone").value = c.phone ?? "";
    document.getElementById("cust-email").value = c.email ?? "";
    document.getElementById("cust-address1").value = c.address1 ?? "";
    document.getElementById("cust-address2").value = c.address2 ?? "";
    document.getElementById("cust-contact").value = c.contact_name ?? "";
    document.getElementById("cust-contact_phone").value = c.contact_phone ?? "";
    document.getElementById("cust-city").value = c.city ?? "";
    document.getElementById("cust-country").value = c.country ?? "";
    document.getElementById("cust-type").value = c.type ?? "walk_in";
    document.getElementById("cust-discount_percent").value = Number(
        c.discount_percent ?? 0,
    ).toFixed(2);
    document.getElementById("cust-point").value = c.point ?? 0;
    document.getElementById("cust-status").value = c.status ?? "1";

    const modal = document.getElementById("confirm-update-cust");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

// UPDATE CUSTOMER
async function confirmUpdateCustomer() {
    if (!selectedCustomerId) {
        showToast({ message: "No customer selected!", type: "warning" });
        return;
    }

    if (!validateUpdateCustomerForm()) return;

    const id = selectedCustomerId;

    const payload = {
        name: document.getElementById("cust-name").value.trim(),
        phone: document.getElementById("cust-phone").value.trim(),
        email: document.getElementById("cust-email").value.trim() || null,

        address1: document.getElementById("cust-address1").value.trim(),
        address2: document.getElementById("cust-address2").value.trim(),
        city: document.getElementById("cust-city").value.trim(),
        country: document.getElementById("cust-country").value.trim(),

        type: document.getElementById("cust-type").value,
        discount_percent:
            parseFloat(
                document.getElementById("cust-discount_percent").value,
            ) || 0,

        point: parseInt(document.getElementById("cust-point").value) || 0,

        // IMPORTANT: database uses contact_name, not contact
        contact_name: document.getElementById("cust-contact").value.trim(),
        contact_phone: document
            .getElementById("cust-contact_phone")
            .value.trim(),

        status: parseInt(document.getElementById("cust-status").value),
    };

    try {
        const res = await fetch(`/customers/${id}`, {
            method: "PUT",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.message || "Update failed");
        }

        const updatedCustomer = data.customer;

        const row = document.querySelector(`tr[data-id="${id}"]`);

        if (row && updatedCustomer) {
            row.querySelector("td:nth-child(3)").textContent =
                updatedCustomer.name ?? "-";

            row.querySelector("td:nth-child(4)").textContent =
                updatedCustomer.phone ?? "-";

            row.querySelector("td:nth-child(5)").textContent =
                updatedCustomer.email ?? "-";

            row.dataset.customerCode = updatedCustomer.customer_code ?? "";
            row.dataset.address1 = updatedCustomer.address1 ?? "";
            row.dataset.address2 = updatedCustomer.address2 ?? "";
            row.dataset.city = updatedCustomer.city ?? "";
            row.dataset.country = updatedCustomer.country ?? "";
            row.dataset.type = updatedCustomer.type ?? "";
            row.dataset.discountPercent = updatedCustomer.discount_percent ?? 0;
            row.dataset.point = updatedCustomer.point ?? 0;
            row.dataset.contactName = updatedCustomer.contact_name ?? "";
            row.dataset.contactPhone = updatedCustomer.contact_phone ?? "";
            row.dataset.status = updatedCustomer.status ?? 0;

            const statusTd = row.querySelector("td:nth-child(11)");

            if (statusTd) {
                statusTd.innerHTML =
                    updatedCustomer.status == 1
                        ? `<span class="inline-flex items-center bg-success-soft border border-success-subtle text-fg-success-strong text-xs font-medium px-1.5 py-0.5 rounded-sm">
                                <span class="w-2 h-2 me-1 bg-success rounded-full"></span>
                                &ensp;Active
                           </span>`
                        : `<span class="inline-flex items-center bg-danger-soft border border-danger-subtle text-fg-danger-strong text-xs font-medium px-1.5 py-0.5 rounded-sm">
                                <span class="w-2 h-2 me-1 bg-danger rounded-full"></span>
                                &ensp;Inactive
                           </span>`;
            }
        }

        showToast({
            message: "Customer updated successfully",
            type: "success",
        });
        loadCustomers(1);
        closeUpdateCustModal();
    } catch (err) {
        console.error(err);
        showToast({
            message: err.message || "Failed to update customer",
            type: "error",
        });
    }
}
function closeUpdateCustModal() {
    const modal = document.getElementById("confirm-update-cust");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function saveCurrencies() {
    const form = document.getElementById("currencyForm");
    const formData = new FormData(form);
    fetch("/currency/update-all", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
            Accept: "application/json",
        },
        body: formData,
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.success) {
                alert(data.message + "\nPlease reload the page to see the updated rates.");
            } else {
                console.error(data.message);
                alert("Error: " + data.message);
            }
        })
        .catch((err) => {
            console.error(err);
            alert("Server error. Check console for details.");
        });
}

// Refresh Button
const refreshBtn = document.getElementById("refreshBtn");
const unsaveModal = document.getElementById("unsaveModal");
const cancelBtn = unsaveModal.querySelector("[data-modal-close]");
const continueBtn = unsaveModal.querySelector("[data-modal-action]");

// Flag to simulate unsaved work (you can replace this with your real check)
let hasUnsavedWork = true;
refreshBtn.addEventListener("click", () => {
    if (hasUnsavedWork) {
        // Show modal
        unsaveModal.classList.remove("hidden");
    } else {
        // No unsaved work, refresh directly
        location.reload();
    }
});

// Close modal SAVE AND UNSAVE
cancelBtn.addEventListener("click", () => {
    unsaveModal.classList.add("hidden");
});

// Confirm refresh
continueBtn.addEventListener("click", () => {
    unsaveModal.classList.add("hidden");
    location.reload(); // actually refresh the page
});

// GLOBAL TOAST
let toastTimeout;

// Generic in-app replacement for native confirm() — for anything that ISN'T
// a print prompt (askPrintConfirm below is print-specific: printer icon,
// "Print"/"Skip" buttons, wrong for e.g. "Cancel this order?"). Reuses the
// #confirmModal markup, which had no Flowbite trigger wired to it anywhere
// (dead markup) — safe to drive with plain classList toggling here.
function askConfirm(message, { title = "Are you sure?", confirmLabel = "Confirm" } = {}) {
    return new Promise((resolve) => {
        const modal = document.getElementById("confirmModal");
        const titleEl = document.getElementById("confirmModalTitle");
        const messageEl = document.getElementById("confirmModalMessage");
        const actionBtn = document.getElementById("confirmModalAction");
        const cancelBtn = modal.querySelector("[data-modal-close]");

        if (titleEl) titleEl.textContent = title;
        if (messageEl) messageEl.textContent = message;
        if (actionBtn) actionBtn.textContent = confirmLabel;

        modal.classList.remove("hidden");
        modal.classList.add("flex");

        function cleanup(result) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
            actionBtn.removeEventListener("click", onYes);
            cancelBtn?.removeEventListener("click", onCancel);
            resolve(result);
        }

        function onYes() {
            cleanup(true);
        }

        function onCancel() {
            cleanup(false);
        }

        actionBtn.addEventListener("click", onYes);
        cancelBtn?.addEventListener("click", onCancel);
    });
}

// Custom in-app replacement for native confirm() when asking "print now?".
// Returns a Promise<boolean> so call sites just `await` it instead of
// blocking on the browser's own dialog.
function askPrintConfirm(message = "Print this document now?") {
    return new Promise((resolve) => {
        const modal = document.getElementById("printConfirmModal");
        const yesBtn = document.getElementById("printConfirmYes");
        const skipBtn = document.getElementById("printConfirmSkip");

        document.getElementById("printConfirmMessage").textContent = message;
        modal.classList.remove("hidden");
        modal.classList.add("flex");

        function cleanup(result) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
            yesBtn.removeEventListener("click", onYes);
            skipBtn.removeEventListener("click", onSkip);
            resolve(result);
        }

        function onYes() {
            cleanup(true);
        }

        function onSkip() {
            cleanup(false);
        }

        yesBtn.addEventListener("click", onYes);
        skipBtn.addEventListener("click", onSkip);
    });
}

// ------------------------
// Print Options — pick which forms to print + how many copies of each,
// remembered per user (localStorage, keyed by user_id) so the next sale
// pre-fills with whatever this cashier picked last time.
// ------------------------
function printOptionsStorageKey() {
    return `print_options_${typeof user_id !== "undefined" ? user_id : "guest"}`;
}

function loadPrintOptionsPrefs() {
    try {
        const saved = JSON.parse(localStorage.getItem(printOptionsStorageKey()));
        if (saved && saved.receipt && saved.invoice && saved.delivery) {
            // pickingList was added after this key was first introduced —
            // older saved prefs won't have it, so default it in.
            if (!saved.pickingList) saved.pickingList = { checked: false };
            return saved;
        }
    } catch (e) { /* corrupt/missing — fall through to defaults */ }

    return {
        receipt: { checked: true, qty: 1 },
        invoice: { checked: false, qty: 1 },
        delivery: { checked: false, qty: 1 },
        pickingList: { checked: false },
    };
}

function savePrintOptionsPrefs(prefs) {
    localStorage.setItem(printOptionsStorageKey(), JSON.stringify(prefs));
}

// Returns a Promise resolving to { receipt: {checked, qty}, invoice: {checked, qty} }
// or null if the user skipped printing entirely.
// Receipt (thermal) and Invoice (A4) each keep their own independent saved
// printer (different localStorage keys — set in print_thermal_receipt.js /
// print_document_a4.js), since they're necessarily two different physical
// printers. This just reads those keys to show the current choice here.
function currentPrinterName(storageKey) {
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey));
        return saved?.printer || null;
    } catch (e) {
        return null;
    }
}

// A4 docs (Invoice/Delivery Note/Picking List) go through the browser's own
// print dialog now, not a saved QZ Tray printer — only Receipt still has one.
function refreshPrintOptionsPrinterNames() {
    const receiptName = currentPrinterName("pos_print_setup");
    document.getElementById("po_receipt_printer_name").textContent = receiptName || "not set yet";
}

function askPrintOptions() {
    return new Promise((resolve) => {
        const modal = document.getElementById("printOptionsModal");
        const confirmBtn = document.getElementById("printOptionsConfirm");
        const skipBtn = document.getElementById("printOptionsSkip");

        const receiptChecked = document.getElementById("po_receipt_checked");
        const receiptQty = document.getElementById("po_receipt_qty");
        const invoiceChecked = document.getElementById("po_invoice_checked");
        const invoiceQty = document.getElementById("po_invoice_qty");
        const deliveryChecked = document.getElementById("po_delivery_checked");
        const deliveryQty = document.getElementById("po_delivery_qty");
        const pickingListChecked = document.getElementById("po_picking_list_checked");
        const receiptChangeBtn = document.getElementById("po_receipt_change_printer");

        const saved = loadPrintOptionsPrefs();
        receiptChecked.checked = !!saved.receipt.checked;
        receiptQty.value = saved.receipt.qty || 1;
        invoiceChecked.checked = !!saved.invoice.checked;
        invoiceQty.value = saved.invoice.qty || 1;
        deliveryChecked.checked = !!saved.delivery.checked;
        deliveryQty.value = saved.delivery.qty || 1;
        pickingListChecked.checked = !!saved.pickingList?.checked;
        receiptQty.disabled = !receiptChecked.checked;
        invoiceQty.disabled = !invoiceChecked.checked;
        deliveryQty.disabled = !deliveryChecked.checked;

        refreshPrintOptionsPrinterNames();

        modal.classList.remove("hidden");
        modal.classList.add("flex");

        function onReceiptToggle() {
            receiptQty.disabled = !receiptChecked.checked;
        }
        function onInvoiceToggle() {
            invoiceQty.disabled = !invoiceChecked.checked;
        }
        function onDeliveryToggle() {
            deliveryQty.disabled = !deliveryChecked.checked;
        }

        async function onChangeReceiptPrinter() {
            if (typeof resetPrintSetup !== "function" || typeof qzEnsure !== "function") return;
            resetPrintSetup();
            try {
                // qzEnsure() connects the QZ Tray websocket first (if not
                // already connected) and only then opens the printer picker —
                // calling ensurePrintSetup() directly skips that connection
                // step and silently fails if QZ wasn't already connected.
                await qzEnsure();
            } catch (e) {
                console.error(e);
                showToast({ message: e.message || "Could not open printer picker", type: "error" });
            }
            refreshPrintOptionsPrinterNames();
        }

        function cleanup(result) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
            confirmBtn.removeEventListener("click", onConfirm);
            skipBtn.removeEventListener("click", onSkip);
            receiptChecked.removeEventListener("change", onReceiptToggle);
            invoiceChecked.removeEventListener("change", onInvoiceToggle);
            deliveryChecked.removeEventListener("change", onDeliveryToggle);
            receiptChangeBtn.removeEventListener("click", onChangeReceiptPrinter);
            resolve(result);
        }

        function onConfirm() {
            const prefs = {
                receipt: {
                    checked: receiptChecked.checked,
                    qty: Math.max(1, parseInt(receiptQty.value) || 1),
                },
                invoice: {
                    checked: invoiceChecked.checked,
                    qty: Math.max(1, parseInt(invoiceQty.value) || 1),
                },
                delivery: {
                    checked: deliveryChecked.checked,
                    qty: Math.max(1, parseInt(deliveryQty.value) || 1),
                },
                pickingList: {
                    checked: pickingListChecked.checked,
                },
            };
            savePrintOptionsPrefs(prefs);
            cleanup(prefs);
        }

        function onSkip() {
            cleanup(null);
        }

        confirmBtn.addEventListener("click", onConfirm);
        skipBtn.addEventListener("click", onSkip);
        receiptChecked.addEventListener("change", onReceiptToggle);
        invoiceChecked.addEventListener("change", onInvoiceToggle);
        deliveryChecked.addEventListener("change", onDeliveryToggle);
        receiptChangeBtn.addEventListener("click", onChangeReceiptPrinter);
    });
}

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


// GLOBAL HIDE TOAST
function hideToast() {
    const toast = document.getElementById("toastMessage");
    toast.classList.add("hidden");

    // reset text only — the icon is a Font Awesome <i> rendered via its
    // class (set in showToast), never via text content. Setting innerText
    // here left a stray "✔️" glyph stacked on top of the next toast's icon.
    document.getElementById("toastText").innerText = "";
}
// DELETE CUSTOMER
async function confirmDeleteCustomer() {
    const customerId = getSelectedCustomerId();
    if (!customerId) return;

    // close modal
    closeDeleteCustModal();

    try {
        const res = await fetch(`/customers/${customerId}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
            },
        });

        if (!res.ok) throw new Error();

        // ✅ remove row safely using data-id
        const row = document.querySelector(`tr[data-id="${customerId}"]`);
        if (row) row.remove();

        // show success toast
        showToast({
            message: "Customer deleted successfully",
            type: "success",
        });
    } catch (err) {
        showToast({ message: "Delete failed", type: "error" });
        console.error(err);
    }
}

// LIST CUSTOMER
const searchInput = document.getElementById("customerSearchInput");
const typeSelect = document.getElementById("customerTypeSelect");
const activeCheckbox = document.getElementById("customerSearchCheckbox");
const tbody = document.getElementById("customer-table-body");
const customerSearch = document.getElementById("customerSearch");

if (customerSearch) {
    customerSearch.addEventListener("input", function () {
        if (this.value.trim() === "") {
            Livewire.dispatch("resetCustomer");

            // also clear modal customer fields
            document.getElementById("so_customer_id_info").value = "";
            document.getElementById("so_customer_name_info").value = "";
            document.getElementById("so_customer_phone_info").value = "";
            document.getElementById("so_customer_address_info").value = "";
            document.getElementById("so_remark_invoice").value = "";
        }
    });
}

let customers = []; // store async fetched data
let sortColumn = ""; // e.g., 'name', 'credit_limit'
let sortDirection = "asc"; // 'asc' or 'desc'
async function loadCustomers(page = 1) {
    const search = searchInput.value;
    const type = typeSelect.value;
    const active = activeCheckbox.checked ? 1 : 0;
    // absent for cashiers — the blade only renders it for admin/supervisor
    const userId = document.getElementById("customerUserSelect")?.value ?? "";

    const query = new URLSearchParams({
        page,
        limit: 20,
        search,
        type,
        status: active,
        user_id: userId,
        sort_by: sortColumn, // NEW
        sort_dir: sortDirection, // NEW
    });
    const res = await fetch(`/customers/list_search?${query.toString()}`);
    const result = await res.json();

    renderCustomerTable(result.data);

    const pagination = document.getElementById("paginationContainer");
    pagination.innerHTML = ""; // clear previous buttons

    const current = result.current_page;
    const last = result.last_page;

    // Always show "First" if not on page 1
    if (current > 1) {
        const firstBtn = document.createElement("button");
        firstBtn.type = "button"; // <-- prevents form submit
        firstBtn.textContent = "« First";
        firstBtn.className = "page-btn";
        firstBtn.onclick = () => loadCustomers(1);
        pagination.appendChild(firstBtn);
    }

    // ----------------- NEW PAGE LOGIC -----------------
    const maxVisible = 10; // show 5 numeric buttons
    let start = Math.max(1, current - 2);
    let end = Math.min(last, current + 2);

    // Adjust if near start
    if (current <= 2) {
        end = Math.min(last, maxVisible);
    }

    // Adjust if near end
    if (current >= last - 1) {
        start = Math.max(1, last - (maxVisible - 1));
    }
    // --------------------------------------------------

    // Numeric buttons
    for (let i = start; i <= end; i++) {
        const pageBtn = document.createElement("button");
        pageBtn.type = "button"; // <-- prevents form submit
        pageBtn.textContent = i;
        pageBtn.className =
            "page-btn" + (i === current ? " page-btn-active" : "");
        pageBtn.onclick = () => loadCustomers(i);
        pagination.appendChild(pageBtn);
    }

    // Always show "Last" if not on last page
    if (current < last) {
        const lastBtn = document.createElement("button");
        lastBtn.type = "button"; // <-- prevents form submit
        lastBtn.textContent = "Last »";
        lastBtn.className = "page-btn";
        lastBtn.onclick = () => loadCustomers(last);
        pagination.appendChild(lastBtn);
    }

    // Update page info text
    document.getElementById("pageInfo").textContent =
        `Page ${current} of ${last} | Total ${result.total}`;
}

// Render table rows
function renderCustomerTable(data) {
    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="px-4 py-4 text-center text-rose-500">
                    No customers found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = data
        .map((c) => {
            return `
                <tr class="border-t hover:bg-neutral-tertiary cursor-pointer"
                    data-id="${c.id}"
                    ondblclick="openUpdateCustModal(${c.id})">

                    <td>
                        <input type="radio" name="customer_id" value="${c.id}">
                    </td>

                    <td>${c.id}</td>
                    <td>${c.customer_code ?? "-"}</td>
                    <td>${c.name ?? "-"}</td>
                    <td>${c.address1 ?? "-"}</td>
                    <td>${c.phone ?? "-"}</td>
                    <td>${c.email ?? "-"}</td>
                    <td>${c.type ?? "-"}</td>
                    <td>${Number(c.discount_percent ?? 0).toFixed(2)}</td>
                    <td>${c.point ?? 0}</td>

                    <td>
                        ${Number(c.status) === 1
                    ? `<span class="inline-flex items-center bg-success-soft border border-success-subtle text-fg-success-strong text-xs font-medium px-1.5 py-0.5 rounded-sm">
                                     <span class="w-2 h-2 me-1 bg-success rounded-full"></span>
                                     &ensp;Active
                                   </span>`
                    : `<span class="inline-flex items-center bg-danger-soft border border-danger-subtle text-fg-danger-strong text-xs font-medium px-1.5 py-0.5 rounded-sm">
                                     <span class="w-2 h-2 me-1 bg-danger rounded-full"></span>
                                     &ensp;Inactive
                                   </span>`
                }
                    </td>
                </tr>
            `;
        })
        .join("");
}
document
    .querySelectorAll("#Table-customer-list th[data-column]")
    .forEach((th) => {
        th.addEventListener("click", () => {
            const col = th.dataset.column;

            if (sortColumn === col) {
                sortDirection = sortDirection === "asc" ? "desc" : "asc";
            } else {
                sortColumn = col;
                sortDirection = "asc";
            }

            loadCustomers(1);

            document
                .querySelectorAll("#Table-customer-list .sort-icon")
                .forEach((s) => (s.textContent = "↕"));

            th.querySelector(".sort-icon").textContent =
                sortDirection === "asc" ? "↑" : "↓";
        });
    });

window.addEventListener("DOMContentLoaded", () => {
    const openModalBtn = document.getElementById("openCustomerModal");

    // Button doesn't exist when the current user lacks customer.view — hide, don't crash.
    if (openModalBtn) openModalBtn.addEventListener("click", () => loadCustomers(1));

    searchInput.addEventListener("input", () => loadCustomers(1));
    typeSelect.addEventListener("change", () => loadCustomers(1));
    activeCheckbox.addEventListener("change", () => loadCustomers(1));
});

// Data Product Search & Pagination
const searchInput_product_list = document.getElementById("ProductSearchInput");
const typeSelect_product = document.getElementById("productTypeSelect");
const activeCheckbox_product = document.getElementById("productSearchCheckbox");
const productLimitSelect = document.getElementById("productLimitSelect");
const tbody_product = document.getElementById("product-table-body");

let products = []; // store async fetched data
let sortColumn_product = ""; // e.g., 'name', 'credit_limit'
let sortDirection_product = "asc"; // 'asc' or 'desc'

window.addEventListener("DOMContentLoaded", () => {
    const openProductModalBtn = document.getElementById("openProductModal");

    // Button doesn't exist when the current user lacks product.view — hide, don't crash.
    if (openProductModalBtn) openProductModalBtn.addEventListener("click", () => loadProducts(1));
    searchInput_product_list.addEventListener("input", () => loadProducts(1));
    typeSelect_product.addEventListener("change", () => loadProducts(1));

    activeCheckbox_product.addEventListener("change", () => loadProducts(1));
    productLimitSelect.addEventListener("change", () => loadProducts(1));
});
let allProducts = [];
async function loadProducts(page = 1) {
    const search = searchInput_product_list.value;
    const type = typeSelect_product.value;
    const active = activeCheckbox_product.value || "";

    let limit = parseInt(productLimitSelect.value) || 15;
    const query = new URLSearchParams({
        page,
        limit: limit,
        search,
        type,
        status: active,
        sort_by: sortColumn_product, // NEW
        sort_dir: sortDirection_product, // NEW
    });
    const res = await fetch(`/products/list_search?${query.toString()}`);
    const result = await res.json();
    allProducts = result.data; // 🔥 store full data
    renderProductTable(result.data);

    const pagination = document.getElementById("paginationContainerProduct");
    pagination.innerHTML = ""; // clear previous buttons

    const current = result.current_page;
    const last = result.last_page;

    // Always show "First" if not on page 1
    if (current > 1) {
        const firstBtn = document.createElement("button");
        firstBtn.type = "button"; // <-- prevents form submit
        firstBtn.textContent = "« First";
        firstBtn.className = "page-btn";
        firstBtn.onclick = () => loadProducts(1);
        pagination.appendChild(firstBtn);
    }

    // ----------------- NEW PAGE LOGIC -----------------
    const maxVisible = 10; // show 5 numeric buttons
    let start = Math.max(1, current - 2);
    let end = Math.min(last, current + 2);

    // Adjust if near start
    if (current <= 2) {
        end = Math.min(last, maxVisible);
    }

    // Adjust if near end
    if (current >= last - 1) {
        start = Math.max(1, last - (maxVisible - 1));
    }
    // --------------------------------------------------

    // Numeric buttons
    for (let i = start; i <= end; i++) {
        const pageBtn = document.createElement("button");
        pageBtn.type = "button"; // <-- prevents form submit
        pageBtn.textContent = i;
        pageBtn.className =
            "page-btn" + (i === current ? " page-btn-active" : "");
        pageBtn.onclick = () => loadProducts(i);
        pagination.appendChild(pageBtn);
    }

    // Always show "Last" if not on last page
    if (current < last) {
        const lastBtn = document.createElement("button");
        lastBtn.type = "button"; // <-- prevents form submit
        lastBtn.textContent = "Last »";
        lastBtn.className = "page-btn";
        lastBtn.onclick = () => loadProducts(last);
        pagination.appendChild(lastBtn);
    }

    // Update page info text
    document.getElementById("pageInfo").textContent =
        `Page ${current} of ${last} | Total ${result.total}`;
}

// Render product table rows
function renderProductTable(data) {
    if (data.length === 0) {
        tbody_product.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                    <i class="fa-solid fa-box-open text-2xl mb-2 block"></i>
                    No Products found
                </td>
            </tr>
        `;
        return;
    }

    tbody_product.innerHTML = data
        .map((p) => {
            const sellPrice = parseFloat(p.sell_price) || 0;
            const cost = parseFloat(p.cost) || 0;
            const vat = parseFloat(p.vat) || 0;
            const discount = parseFloat(p.discount_percent) || 0;

            const optionBadge = (active, icon, label) => `
                <span title="${label}: ${active ? "Yes" : "No"}"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-xs
                        ${active ? "bg-sky-100 text-sky-600" : "bg-gray-100 text-gray-300"}">
                    <i class="fa-solid ${icon}"></i>
                </span>`;

            return `
        <tr class="cursor-pointer"
            data-id="${p.id}"
            data-bar_code="${p.bar_code ?? ""}"
            data-code="${p.code ?? ""}"
            data-name="${p.name ?? ""}"
            data-variant="${p.variant ?? ""}"
            data-description="${p.description ?? ""}"
            data-min_stock="${p.min_stock}"
            data-max_stock="${p.max_stock}"
            data-sell_price="${p.sell_price}"
            data-cost="${p.cost}"
            data-vat="${p.vat}"
            data-discount_percent="${p.discount_percent}"
            data-last_purchase_price="${p.last_purchase_price ?? ""}"
            data-category_id="${p.category_id ?? ""}"
            data-category_name="${p.category_name ?? ""}"
            data-unit="${p.unit ?? ""}"
            data-track_stock="${p.track_stock}"
            data-allow_discount="${p.allow_discount}"
            data-allow_return="${p.allow_return}"
            data-status="${p.status}"
            data-image="${p.image}"
            >

            <td class="px-4 py-3 align-top">
                <input type="radio" name="product_id" value="${p.id}" class="mt-1.5">
            </td>

            <td class="px-4 py-3 align-top">
                <div class="flex items-start gap-3 min-w-[220px] max-w-[320px]">
                    <img
                        src="/assets/startic_img/${encodeURIComponent(p.image)}"
                        alt=""
                        onerror="this.onerror=null;this.src='/assets/defult/placeholder.png';"
                        class="h-11 w-11 rounded-lg object-cover border border-slate-200 shrink-0">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800 truncate">${p.name ?? ""}</div>
                        <div class="text-xs text-slate-400 truncate">
                            ${p.code ?? "-"}${p.bar_code ? ` · ${p.bar_code}` : ""}
                        </div>
                        ${p.variant || p.description ? `
                        <div class="text-xs text-slate-400 truncate">
                            ${[p.variant, p.description].filter(Boolean).join(" · ")}
                        </div>` : ""}
                    </div>
                </div>
            </td>

            <td class="px-4 py-3 align-top text-nowrap">
                <div class="text-slate-700">${p.category?.name ?? p.category_name ?? "-"}</div>
                <div class="text-xs text-slate-400">${p.unit ?? "-"}</div>
            </td>

            <td class="px-4 py-3 align-top text-right text-nowrap">
                <div class="font-semibold text-slate-800">${sellPrice.toFixed(2)}</div>
                <div class="text-xs text-slate-400">cost ${cost.toFixed(2)}</div>
                ${(vat > 0 || discount > 0) ? `
                <div class="mt-0.5 flex items-center justify-end gap-1">
                    ${vat > 0 ? `<span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500">VAT ${vat.toFixed(0)}%</span>` : ""}
                    ${discount > 0 ? `<span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-600">-${discount.toFixed(0)}%</span>` : ""}
                </div>` : ""}
            </td>

            <td class="px-4 py-3 align-top text-right text-nowrap">
                <div class="text-slate-700">${p.min_stock ?? 0} – ${p.max_stock ?? 0}</div>
                ${Number(p.track_stock) ? `<div class="text-[10px] text-sky-500 mt-0.5"><i class="fa-solid fa-layer-group"></i> tracked</div>` : ""}
            </td>

            <td class="px-4 py-3 align-top">
                <div class="flex items-center justify-center gap-1.5">
                    ${optionBadge(Number(p.allow_discount), "fa-tag", "Allow Discount")}
                    ${optionBadge(Number(p.allow_return), "fa-rotate-left", "Allow Return")}
                </div>
            </td>

            <td class="px-4 py-3 align-top text-center">
                ${Number(p.status) === 1
                    ? `<span class="inline-flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-medium px-2 py-1 rounded-full">
                             <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Active
                           </span>`
                    : `<span class="inline-flex items-center gap-1.5 bg-rose-50 border border-rose-200 text-rose-600 text-xs font-medium px-2 py-1 rounded-full">
                             <span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span> Inactive
                           </span>`
                }
            </td>
        </tr>
        `;
        })
        .join("");
}
document.addEventListener("click", function (e) {
    const row = e.target.closest("tr[data-id]");
    if (!row) return;

    // Ignore clicks on inputs themselves (optional safety)
    if (e.target.tagName === "INPUT") return;

    // Select radio
    const radio = row.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
        radio.dispatchEvent(new Event("change", { bubbles: true }));
    }

    // Call your edit logic
    // editProductFromRow(row);
});

// Load categories on first click
typeSelect_product.addEventListener("click", async () => {
    if (typeSelect_product.options.length > 0) return; // already loaded
    await CategoryLoad();
});
// Example CategoryLoad function
async function CategoryLoad() {
    try {
        const response = await fetch("/categories"); // your API endpoint
        const categories = await response.json();

        // Clear existing options (optional)
        typeSelect_product.innerHTML =
            '<option value="">Select Category</option>';

        categories.forEach((cat) => {
            const option = document.createElement("option");
            option.value = cat.id; // adjust to your API field
            option.textContent = cat.name;
            typeSelect_product.appendChild(option);
        });
    } catch (error) {
        console.error("Failed to load categories:", error);
    }
}

function validateUpdateCustomerForm() {
    const errors = [];

    const name = document.getElementById("cust-name").value.trim();
    const type = document.getElementById("cust-type").value;
    const status = document.getElementById("cust-status").value;
    const email = document.getElementById("cust-email").value.trim();

    // 1️⃣ Required fields
    if (!name) errors.push("Name is required.");

    if (!["walk_in", "member", "vip"].includes(type)) {
        errors.push("Type must be Walk-in, Member, or VIP.");
    }

    if (status !== "0" && status !== "1") {
        errors.push("Status must be Active or Inactive.");
    }

    // 2️⃣ Optional but check if email is valid

    // 3️⃣ Return result
    if (errors.length > 0) {
        errors.forEach((err) => showToast({ message: err, type: "error" }));
        return false; // invalid
    }

    return true; // valid
}

let wh;
// Load Warehouse for select Stock
async function loadWarehouses() {
    const select = document.getElementById("warehouseTypeSelect");

    // Reset dropdown
    select.innerHTML = `
        <option value="All">All Warehouse</option>
    `;

    try {
        const response = await fetch("/warehouses/list");
        if (!response.ok) throw new Error("Fetch failed");

        const warehouses = await response.json();

        if (!warehouses.length) return; // no warehouses
        wh = warehouses;
        warehouses.forEach((w) => {
            select.insertAdjacentHTML(
                "beforeend",
                `<option value="${w.id}">${w.name}${w.location ? " - " + w.location : ""}</option>`,
            );
        });
    } catch (err) {
        console.error(err);

        select.innerHTML = `
            <option value="All">All Warehouse</option>
            <option disabled>Failed to load warehouses</option>
        `;
    }
}

// ------------------------
// Manage Bins
// ------------------------

// Reusable: fill any <select> with the bins of a given warehouse.
// Used by the Manage Bins modal and every write-site bin picker
// (GRN, Transfer, Adjustment).
async function populateBinSelect(selectEl, warehouseId, { placeholder = "No Bin", excludeBinId = null } = {}) {
    if (!selectEl) return;

    selectEl.innerHTML = `<option value="">${placeholder}</option>`;

    if (!warehouseId || warehouseId === "All") return;

    try {
        const res = await fetch(`/bins?warehouse_id=${warehouseId}`);
        if (!res.ok) throw new Error("Failed to load bins");

        const bins = await res.json();
        bins.forEach((b) => {
            if (excludeBinId !== null && String(b.id) === String(excludeBinId)) return;
            selectEl.insertAdjacentHTML(
                "beforeend",
                `<option value="${b.id}">${b.name}</option>`,
            );
        });
    } catch (err) {
        console.error(err);
    }
}

function openManageBinsModal() {
    const modal = document.getElementById("manageBinsModal");
    if (!modal) return;
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    const whSelect = document.getElementById("manage-bins-warehouse");
    whSelect.innerHTML = "";
    (wh || []).forEach((w) => {
        whSelect.insertAdjacentHTML(
            "beforeend",
            `<option value="${w.id}">${w.name}</option>`,
        );
    });

    // default to whatever warehouse is currently selected in the stock view
    const currentWh = document.getElementById("warehouseTypeSelect")?.value;
    if (currentWh && currentWh !== "All" && whSelect.querySelector(`option[value="${currentWh}"]`)) {
        whSelect.value = currentWh;
    }

    whSelect.onchange = () => loadBins(whSelect.value);
    loadBins(whSelect.value);
}

function closeManageBinsModal() {
    const modal = document.getElementById("manageBinsModal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

// ---- Company Profile (pos_profiles) settings ----
// targetUserReport: '0' for the shared House profile, or a user id. Omitted
// when opened from the menu, in which case the picker keeps its own value.
async function openPosProfileModal(targetUserReport = null) {
    const modal = document.getElementById("posProfileModal");
    if (!modal) return;
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    await populatePosProfileUsers();

    // Opened from the user form with a target, or plain from the menu.
    const sel = document.getElementById("pp-user-report");
    if (sel && targetUserReport != null && targetUserReport !== "") {
        sel.value = String(targetUserReport);
    }
    await loadPosProfileInto(sel?.value);
}

/**
 * Open the letterhead editor for the user currently being edited.
 *
 * The profile belongs to the user (pos_profiles.user_report = their id), so
 * this is the same editor, just pre-targeted — no separate copy of the form.
 */
async function openPrintProfileForEditedUser() {
    const id = document.getElementById("edit_user_id")?.value;
    if (!id) return;
    await openPosProfileModal(id);
}
window.openPrintProfileForEditedUser = openPrintProfileForEditedUser;

// Local escaper: the other escapeHtml() in this file is declared inside a
// block further down, so reaching it from here would rely on Annex B hoisting.
function escProfile(s) {
    return String(s ?? "").replace(
        /[&<>"']/g,
        (c) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
            })[c],
    );
}

/**
 * Manage print profiles: every user and the letterhead they print under.
 *
 * There is no separate "create" flow — a profile IS a user's row
 * (pos_profiles.user_report), so Create and Edit are the same action: open the
 * editor targeted at that user. The list exists to show, at a glance, who is
 * still falling back to House.
 */
async function openManageProfilesModal() {
    const modal = document.getElementById("manageProfilesModal");
    if (!modal) return;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    await loadManageProfiles();
}
window.openManageProfilesModal = openManageProfilesModal;

function closeManageProfilesModal() {
    const modal = document.getElementById("manageProfilesModal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}
window.closeManageProfilesModal = closeManageProfilesModal;

async function loadManageProfiles() {
    const body = document.getElementById("manageProfilesBody");
    if (!body) return;

    try {
        const res = await fetch("/pos-profile/assignable");
        if (!res.ok) {
            body.innerHTML = `<tr><td colspan="4" class="px-3 py-6 text-center text-rose-500">
                                  Not permitted</td></tr>`;
            return;
        }
        const data = await res.json();

        const row = (r, isHouse) => `
            <tr class="hover:bg-slate-50">
                <td class="px-3 py-2 font-medium">${escProfile(r.username)}</td>
                <td class="px-3 py-2 text-slate-500">${isHouse ? "—" : escProfile(r.role ?? "")}</td>
                <td class="px-3 py-2">
                    ${
                        r.has_own
                            ? `<span class="font-medium">${escProfile(r.company || "(no company name)")}</span>`
                            : `<span class="text-slate-400">${escProfile(r.effective || "(none)")}</span>
                               <span class="ml-1 rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-[11px] font-semibold">House</span>`
                    }
                </td>
                <td class="px-3 py-2 text-center">
                    <button type="button"
                        onclick="closeManageProfilesModal(); openPosProfileModal('${r.user_report}')"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition
                               ${
                                   r.has_own
                                       ? "bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100"
                                       : "bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100"
                               }">
                        <i class="fa-solid ${r.has_own ? "fa-pen" : "fa-plus"} mr-1"></i>
                        ${r.has_own ? "Edit" : "Create"}
                    </button>
                </td>
            </tr>`;

        body.innerHTML =
            row(data.house, true) + (data.users || []).map((u) => row(u, false)).join("");
    } catch (err) {
        console.error(err);
        body.innerHTML = `<tr><td colspan="4" class="px-3 py-6 text-center text-rose-500">
                              Failed to load</td></tr>`;
    }
}

/** Badge on the user form showing which letterhead that user prints under. */
async function refreshPrintProfileSummary(userId) {
    const el = document.getElementById("printProfileSummary_edit");
    if (!el || !userId) return;

    try {
        const res = await fetch(
            `/pos-profile?user_report=${encodeURIComponent(userId)}`,
        );
        if (!res.ok) return;
        const p = await res.json();

        el.textContent = p.has_own_profile
            ? p.company || "(no company name)"
            : "House (default)";
        el.classList.toggle("bg-sky-100", !!p.has_own_profile);
        el.classList.toggle("text-sky-700", !!p.has_own_profile);
    } catch (err) {
        console.error(err);
    }
}
window.refreshPrintProfileSummary = refreshPrintProfileSummary;

/**
 * Fill the admin-only "Profile for" picker.
 *
 * Printed documents use the profile of whoever ISSUED them, so a user with no
 * row of their own falls back to House — which is why one letterhead appeared
 * on nearly every invoice. This is where an admin gives someone their own.
 */
async function populatePosProfileUsers() {
    const sel = document.getElementById("pp-user-report");
    if (!sel || sel.dataset.filled === "1") return;

    try {
        const res = await fetch("/pos-profile/assignable");
        if (!res.ok) return; // non-admin: picker is not rendered anyway
        const data = await res.json();

        sel.innerHTML = "";
        const add = (row) => {
            const o = document.createElement("option");
            o.value = row.user_report;
            o.textContent = row.has_own
                ? `${row.username} — ${row.company || "(no company name)"}`
                : `${row.username} — no profile yet`;
            sel.appendChild(o);
        };

        add(data.house);
        (data.users || []).forEach(add);
        sel.dataset.filled = "1";

        sel.addEventListener("change", () => loadPosProfileInto(sel.value));
    } catch (err) {
        console.error(err);
    }
}

/** Load one user_report row into the form ('0' = the shared house profile). */
async function loadPosProfileInto(userReport) {
    try {
        const qs = userReport ? `?user_report=${encodeURIComponent(userReport)}` : "";
        const res = await fetch(`/pos-profile${qs}`);
        const profile = res.ok ? await res.json() : null;
        const p = profile || {};

        const hint = document.getElementById("pp-user-hint");
        if (hint) {
            hint.textContent = p.has_own_profile
                ? "Editing this profile."
                : "No profile of their own yet — saving will create one. Fields are pre-filled from the House profile.";
        }

        document.getElementById("pp-company").value = p.company ?? "";
        document.getElementById("pp-address1").value = p.address1 ?? "";
        document.getElementById("pp-address2").value = p.address2 ?? "";
        document.getElementById("pp-phone1").value = p.phone1 ?? "";
        document.getElementById("pp-phone2").value = p.phone2 ?? "";
        document.getElementById("pp-email").value = p.email ?? "";
        document.getElementById("pp-telegram").value = p.telegram ?? "";
        document.getElementById("pp-social").value = p.social ?? "";
        document.getElementById("pp-seller").value = p.seller ?? "";
        document.getElementById("pp-description").value = p.description ?? "";

        setPosProfileLogoPreview(p.logo_url);
    } catch (err) {
        console.error(err);
    }
}

function setPosProfileLogoPreview(logoUrl) {
    const img = document.getElementById("pp-logo-preview");
    const placeholder = document.getElementById("pp-logo-placeholder");
    // Only offer "Remove" when there is actually a logo to remove.
    const removeBtn = document.getElementById("pp-logo-remove");
    if (removeBtn) removeBtn.classList.toggle("hidden", !logoUrl);

    if (!img || !placeholder) return;

    if (logoUrl) {
        img.src = logoUrl;
        img.classList.remove("hidden");
        placeholder.classList.add("hidden");
    } else {
        img.src = "";
        img.classList.add("hidden");
        placeholder.classList.remove("hidden");
    }
}

// For companies with no logo: clears the shared logo file so the receipt and
// every A4 form print without one (their templates already skip a null logo).
async function removePosProfileLogo() {
    if (!confirm("Remove the company logo? Receipts and printed documents will have no logo.")) {
        return;
    }

    const btn = document.getElementById("pp-logo-remove");
    if (btn) btn.disabled = true;

    try {
        const res = await fetch("/pos-profile/logo", {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
            },
        });

        if (res.status === 403) {
            showToast({ message: "You don't have permission to change the logo", type: "error" });
            return;
        }

        const data = await res.json();

        if (res.ok && data.success) {
            setPosProfileLogoPreview(null);
            if (pos_profile_for_print) pos_profile_for_print.logo_url = null;

            // The thermal receipt falls back to the #logo_80mm <img> in the DOM
            // when posInfo has no logo_url, so drop that image as well — otherwise
            // the next receipt printed before a page reload would still reference
            // the file we just deleted.
            document
                .querySelectorAll("#logo_80mm img")
                .forEach((img) => img.remove());

            showToast({ message: data.message || "Logo removed", type: "success" });
        } else {
            showToast({ message: data.message || "Failed to remove logo", type: "error" });
        }
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to remove logo", type: "error" });
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function uploadPosProfileLogo(input) {
    const file = input.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append("logo", file);

    try {
        const res = await fetch("/pos-profile/logo", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
            },
            body: formData,
        });

        const data = await res.json();

        if (res.ok && data.success) {
            setPosProfileLogoPreview(data.logo_url);
            if (pos_profile_for_print) pos_profile_for_print.logo_url = data.logo_url;
            showToast({ message: data.message || "Logo updated", type: "success" });
        } else {
            showToast({ message: data.message || "Failed to upload logo", type: "error" });
        }
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to upload logo", type: "error" });
    } finally {
        input.value = "";
    }
}

function closePosProfileModal() {
    const modal = document.getElementById("posProfileModal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function savePosProfile() {
    const company = document.getElementById("pp-company").value.trim();
    if (!company) {
        showToast({ message: "Company name is required", type: "error" });
        return;
    }

    const payload = {
        company,
        address1: document.getElementById("pp-address1").value.trim(),
        address2: document.getElementById("pp-address2").value.trim(),
        phone1: document.getElementById("pp-phone1").value.trim(),
        phone2: document.getElementById("pp-phone2").value.trim(),
        email: document.getElementById("pp-email").value.trim(),
        telegram: document.getElementById("pp-telegram").value.trim(),
        social: document.getElementById("pp-social").value.trim(),
        seller: document.getElementById("pp-seller").value.trim(),
        description: document.getElementById("pp-description").value.trim(),
        // Admin-only picker; absent for everyone else, in which case the server
        // saves onto the caller's own row rather than the shared house profile.
        user_report: document.getElementById("pp-user-report")?.value,
    };

    try {
        const res = await fetch("/pos-profile", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (res.ok && data.success) {
            // keep the in-memory global used by every print template fresh,
            // without needing a full page reload
            pos_profile_for_print = data.profile;

            showToast({ message: data.message || "Saved", type: "success" });
            closePosProfileModal();
        } else {
            showToast({
                message: data.message || "Failed to save company profile",
                type: "error",
            });
        }
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to save company profile", type: "error" });
    }
}

async function loadBins(warehouseId) {
    const list = document.getElementById("manage-bins-list");
    if (!list) return;

    if (!warehouseId) {
        list.innerHTML = `<li class="px-4 py-3 text-slate-400">Select a warehouse first</li>`;
        return;
    }

    try {
        const res = await fetch(`/bins?warehouse_id=${warehouseId}`);
        const bins = await res.json();

        if (!bins.length) {
            list.innerHTML = `<li class="px-4 py-3 text-slate-400">No bins yet for this warehouse</li>`;
            return;
        }

        list.innerHTML = bins
            .map(
                (b) => `
            <li class="flex items-center justify-between px-4 py-3">
                <span class="font-medium text-slate-800">${b.name}</span>
                <button onclick="deleteBin(${b.id})"
                    class="text-red-500 hover:text-red-700 text-xs font-semibold">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>
            </li>`,
            )
            .join("");
    } catch (err) {
        console.error(err);
        list.innerHTML = `<li class="px-4 py-3 text-red-500">Failed to load bins</li>`;
    }
}

async function addBin() {
    const warehouseId = document.getElementById("manage-bins-warehouse")?.value;
    const nameInput = document.getElementById("new-bin-name");
    const name = nameInput?.value?.trim();

    if (!warehouseId) {
        showToast({ message: "Select a warehouse first", type: "error" });
        return;
    }
    if (!name) {
        showToast({ message: "Enter a bin name", type: "error" });
        return;
    }

    try {
        const res = await fetch("/bins", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    ?.value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ warehouse_id: warehouseId, name }),
        });
        const data = await res.json();

        if (!data.success) {
            showToast({ message: data.message || "Failed to add bin", type: "error" });
            return;
        }

        nameInput.value = "";
        showToast({ message: "Bin added", type: "success" });
        loadBins(warehouseId);
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to add bin", type: "error" });
    }
}

async function deleteBin(id) {
    if (!confirm("Delete this bin?")) return;

    try {
        const res = await fetch(`/bins/${id}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    ?.value,
                Accept: "application/json",
            },
        });
        const data = await res.json();

        if (!data.success) {
            showToast({ message: data.message || "Failed to delete bin", type: "error" });
            return;
        }

        showToast({ message: "Bin deleted", type: "success" });
        loadBins(document.getElementById("manage-bins-warehouse")?.value);
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to delete bin", type: "error" });
    }
}

// ---- Manage Categories ----
let editingCategoryId = null;

function openManageCategoriesModal() {
    const modal = document.getElementById("manageCategoriesModal");
    if (!modal) return;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    editingCategoryId = null;
    loadCategoriesAdmin();
}

function closeManageCategoriesModal() {
    const modal = document.getElementById("manageCategoriesModal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

// refresh every other dropdown on the page currently sourced from the
// categories table, so a rename/delete/add shows up immediately elsewhere
function refreshCategoryConsumers() {
    if (typeof CategoryLoad === "function" && document.getElementById("productTypeSelect")) {
        typeSelect_product.innerHTML = '<option value="">Select Category</option>';
        CategoryLoad();
    }
    if (typeof loadCategories_product === "function" && document.getElementById("category-filter2")) {
        loadCategories_product();
    }
}

async function loadCategoriesAdmin() {
    const list = document.getElementById("manage-categories-list");
    if (!list) return;

    list.innerHTML = `<li class="px-4 py-3 text-slate-400">Loading...</li>`;

    try {
        const res = await fetch("/categories/manage");
        const categories = await res.json();

        if (!categories.length) {
            list.innerHTML = `<li class="px-4 py-3 text-slate-400">No categories yet</li>`;
            return;
        }

        list.innerHTML = categories
            .map((c) => {
                if (editingCategoryId === c.id) {
                    return `
                    <li class="px-4 py-4 space-y-3 bg-sky-50/60">
                        <input type="text" id="edit-category-name-${c.id}" value="${(c.name ?? "").replace(/"/g, "&quot;")}"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                        <input type="text" id="edit-category-description-${c.id}" value="${(c.description ?? "").replace(/"/g, "&quot;")}"
                            placeholder="Description"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                        <div class="flex items-center justify-between">
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                                <input type="checkbox" id="edit-category-status-${c.id}" ${c.status ? "checked" : ""}
                                    class="rounded border-slate-300 text-sky-600 focus:ring-sky-400">
                                Active
                            </label>
                            <div class="flex gap-2">
                                <button onclick="cancelEditCategory()"
                                    class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs font-semibold hover:bg-slate-100">
                                    Cancel
                                </button>
                                <button onclick="saveEditCategory(${c.id})"
                                    class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold">
                                    <i class="fa-solid fa-check"></i> Save
                                </button>
                            </div>
                        </div>
                    </li>`;
                }

                return `
                <li class="flex items-center justify-between gap-3 px-4 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-800 truncate">${c.name}</span>
                            ${!c.status ? `<span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400">Inactive</span>` : ""}
                            ${c.pos_items_count ? `<span class="text-[10px] px-1.5 py-0.5 rounded-full bg-sky-100 text-sky-600">${c.pos_items_count} product${c.pos_items_count == 1 ? "" : "s"}</span>` : ""}
                        </div>
                        ${c.description ? `<div class="text-xs text-slate-400 truncate">${c.description}</div>` : ""}
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button onclick="startEditCategory(${c.id})"
                            class="h-8 w-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 inline-flex items-center justify-center transition">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button onclick="deleteCategory(${c.id})"
                            class="h-8 w-8 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 inline-flex items-center justify-center transition">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </li>`;
            })
            .join("");
    } catch (err) {
        console.error(err);
        list.innerHTML = `<li class="px-4 py-3 text-red-500">Failed to load categories</li>`;
    }
}

async function addCategory() {
    const nameInput = document.getElementById("new-category-name");
    const descInput = document.getElementById("new-category-description");
    const name = nameInput?.value?.trim();

    if (!name) {
        showToast({ message: "Enter a category name", type: "error" });
        return;
    }

    try {
        const res = await fetch("/categories", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    ?.value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ name, description: descInput?.value?.trim() || null }),
        });
        const data = await res.json();

        if (!data.success) {
            showToast({ message: data.message || "Failed to add category", type: "error" });
            return;
        }

        nameInput.value = "";
        descInput.value = "";
        showToast({ message: "Category added", type: "success" });
        loadCategoriesAdmin();
        refreshCategoryConsumers();
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to add category", type: "error" });
    }
}

function startEditCategory(id) {
    editingCategoryId = id;
    loadCategoriesAdmin();
}

function cancelEditCategory() {
    editingCategoryId = null;
    loadCategoriesAdmin();
}

async function saveEditCategory(id) {
    const name = document.getElementById(`edit-category-name-${id}`)?.value?.trim();
    const description = document.getElementById(`edit-category-description-${id}`)?.value?.trim();
    const status = document.getElementById(`edit-category-status-${id}`)?.checked;

    if (!name) {
        showToast({ message: "Category name cannot be empty", type: "error" });
        return;
    }

    try {
        const res = await fetch(`/categories/${id}`, {
            method: "PUT",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    ?.value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ name, description: description || null, status }),
        });
        const data = await res.json();

        if (!data.success) {
            showToast({ message: data.message || "Failed to update category", type: "error" });
            return;
        }

        editingCategoryId = null;
        showToast({ message: "Category updated", type: "success" });
        loadCategoriesAdmin();
        refreshCategoryConsumers();
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to update category", type: "error" });
    }
}

async function deleteCategory(id) {
    if (!confirm("Delete this category?")) return;

    try {
        const res = await fetch(`/categories/${id}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    ?.value,
                Accept: "application/json",
            },
        });
        const data = await res.json();

        if (!data.success) {
            showToast({ message: data.message || "Failed to delete category", type: "error" });
            return;
        }

        showToast({ message: "Category deleted", type: "success" });
        loadCategoriesAdmin();
        refreshCategoryConsumers();
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to delete category", type: "error" });
    }
}

// ------------------------
// Manage Warehouses (Create / Update / Delete / Disable)
// ------------------------
function openWarehouseListModal() {
    const modal = document.getElementById("default-modal-warehouse-list");
    if (!modal) return;
    modal.classList.remove("hidden");
    loadWarehouseCrudList();
}

function closeWarehouseListModal() {
    const modal = document.getElementById("default-modal-warehouse-list");
    if (!modal) return;
    modal.classList.add("hidden");
}

// Which side of the business may use a warehouse. "Both" is deliberately muted —
// it is the default and the common case, so only a restriction should draw the eye.
function warehouseUsageBadge(type) {
    const map = {
        sale: ["Sale only", "bg-emerald-100 text-emerald-800"],
        purchase: ["Purchase only", "bg-sky-100 text-sky-800"],
    };
    const [label, cls] = map[type] ?? ["Sale & Purchase", "bg-gray-100 text-gray-600"];

    return `<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${cls}">${label}</span>`;
}

async function loadWarehouseCrudList() {
    const tbody = document.getElementById("warehouse-crud-tbody");
    if (!tbody) return;
    // +1 for the Usage column added alongside Name / Location.
    const noteColspan = typeof is_admin !== "undefined" && is_admin ? 6 : 5;
    tbody.innerHTML = `<tr><td colspan="${noteColspan}" class="px-3 py-4 text-center text-gray-500">Loading...</td></tr>`;

    try {
        const res = await fetch("/warehouses/list?scope=all");
        if (!res.ok) throw new Error("Failed to load warehouses");
        const warehouses = await res.json();

        if (!warehouses.length) {
            tbody.innerHTML = `<tr><td colspan="${noteColspan}" class="px-3 py-4 text-center text-gray-500">No warehouses found</td></tr>`;
            return;
        }

        tbody.innerHTML = warehouses
            .map((w) => {
                const active = Number(w.status) === 1;
                return `
            <tr>
                <td class="px-3 py-2 font-semibold">${w.name ?? ""}</td>
                <td class="px-3 py-2">${w.location ?? ""}</td>
                <td class="px-3 py-2">${warehouseUsageBadge(w.type)}</td>
                ${typeof is_admin !== "undefined" && is_admin ? `<td class="px-3 py-2 text-gray-500">${w.note ?? ""}</td>` : ""}
                <td class="px-3 py-2 text-center">
                    <span class="px-2 py-1 rounded-lg text-xs font-bold ${active ? "bg-emerald-100 text-emerald-700" : "bg-gray-200 text-gray-500"}">
                        ${active ? "Active" : "Disabled"}
                    </span>
                </td>
                <td class="px-3 py-2">
                    <div class="flex items-center justify-center gap-1">
                        <button type="button" onclick='openWarehouseFormModal(${JSON.stringify(w)})' class="page-btn" title="Edit">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" onclick="toggleWarehouseStatus(${w.id})" class="page-btn" title="${active ? "Disable" : "Enable"}">
                            <i class="fa-solid ${active ? "fa-toggle-off" : "fa-toggle-on"}"></i>
                        </button>
                        <button type="button" onclick="deleteWarehouse(${w.id})" class="page-btn" title="Delete">
                            <i class="fa-solid fa-trash text-red-500"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
            })
            .join("");
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="${noteColspan}" class="px-3 py-4 text-center text-red-500">Failed to load warehouses</td></tr>`;
    }
}

function openWarehouseFormModal(warehouse = null) {
    document.getElementById("warehouse_form_id").value = warehouse?.id ?? "";
    document.getElementById("warehouse_form_name").value = warehouse?.name ?? "";
    document.getElementById("warehouse_form_location").value = warehouse?.location ?? "";

    // New warehouses start unrestricted; editing shows what is already set.
    const typeEl = document.getElementById("warehouse_form_type");
    if (typeEl) typeEl.value = warehouse?.type ?? "both";

    const noteEl = document.getElementById("warehouse_form_note");
    if (noteEl) noteEl.value = warehouse?.note ?? "";

    document.getElementById("warehouseFormTitle").innerText = warehouse ? "Edit Warehouse" : "New Warehouse";
    document.getElementById("default-modal-warehouse-form").classList.remove("hidden");
}

function closeWarehouseFormModal() {
    document.getElementById("default-modal-warehouse-form").classList.add("hidden");
}

async function saveWarehouseForm() {
    const id = document.getElementById("warehouse_form_id").value;
    const name = document.getElementById("warehouse_form_name").value.trim();
    const location = document.getElementById("warehouse_form_location").value.trim();
    const typeEl = document.getElementById("warehouse_form_type");
    const noteEl = document.getElementById("warehouse_form_note");

    if (!name) {
        return showToast({ message: "Warehouse name is required", type: "error" });
    }

    const payload = { name, location };
    // Only sent when the field is present — the controller keeps the existing
    // value otherwise, so an older form cannot silently widen a restriction.
    if (typeEl) payload.type = typeEl.value;
    if (noteEl) payload.note = noteEl.value;

    const btn = document.getElementById("warehouseFormSaveBtn");
    btn.disabled = true;

    try {
        const url = id ? `/warehouses/update/${id}` : "/warehouses";
        const response = await fetch(url, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
        });

        const result = await response.json();

        if (result.success) {
            showToast({
                message: id ? "Warehouse updated successfully!" : "Warehouse created successfully!",
                type: "success",
            });
            closeWarehouseFormModal();
            loadWarehouseCrudList();
            loadWarehouses();
        } else {
            showToast({ message: result.message || "Failed to save warehouse", type: "error" });
        }
    } catch (err) {
        console.error(err);
        showToast({ message: "Error saving warehouse", type: "error" });
    } finally {
        btn.disabled = false;
    }
}

async function toggleWarehouseStatus(id) {
    try {
        const response = await fetch(`/warehouses/${id}/toggle-status`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                Accept: "application/json",
            },
        });
        const result = await response.json();

        if (result.success) {
            showToast({ message: result.message, type: "success" });
            loadWarehouseCrudList();
            loadWarehouses();
        } else {
            showToast({ message: result.message || "Failed to update status", type: "error" });
        }
    } catch (err) {
        console.error(err);
        showToast({ message: "Error updating warehouse status", type: "error" });
    }
}

async function deleteWarehouse(id) {
    if (!confirm("Delete this warehouse? This cannot be undone.")) return;

    try {
        const response = await fetch(`/warehouses/${id}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                Accept: "application/json",
            },
        });
        const result = await response.json();

        if (result.success) {
            showToast({ message: "Warehouse deleted successfully!", type: "success" });
            loadWarehouseCrudList();
            loadWarehouses();
        } else {
            showToast({ message: result.message || "Failed to delete warehouse", type: "error" });
        }
    } catch (err) {
        console.error(err);
        showToast({ message: "Error deleting warehouse", type: "error" });
    }
}

let currentSort = {
    by: "expire", // default sort column
    dir: "asc", // default direction
};

async function loadCategories_product() {
    try {
        const res = await fetch("/product/categories");
        const categories = await res.json();

        const select = document.getElementById("category-filter2");
        select.innerHTML = '<option value="">All Categories</option>'; // Clear existing options
        categories.forEach((category) => {
            const option = document.createElement("option");
            option.value = category.id;
            option.textContent = category.name;
            select.appendChild(option);
        });
    } catch (error) {
        console.error("Error loading categories:", error);
    }
}
// Element doesn't exist when the current user lacks warehouse.view — hide, don't crash.
if (document.getElementById("openWarehouseModel")) {
    document
        .getElementById("openWarehouseModel")
        .addEventListener("click", function () {
            // Load WARE ID In Select
            loadWarehouses();
            loadCategories_product();
            // Fetch and Render Stock
            loadWarehouseStock(0, 1); // or handle All case
        });
}
document
    .getElementById("warehouseTypeSelect")
    .addEventListener("change", function () {
        const warehouseId = this.value;

        if (warehouseId === "All") {
            loadWarehouseStock(0, 1); // or handle All case
        } else {
            loadWarehouseStock(warehouseId, 1);
        }
    });
// Listen to filter inputs (search, variant, status, stock)
document
    .querySelectorAll(
        "#limit-filter, #search-stock, #status-filter, #stock-filter, #category-filter2",
    )
    .forEach((el) => {
        el.addEventListener("input", () => {
            loadWarehouseStock(currentWarehouseId);
        });
    });
const modal = document.getElementById("warehouse-stock-modal");
// Stock rows are grouped per product per warehouse, so the same product repeats
// down the list. Tinting by warehouse makes those repeats scannable — which row
// belongs to which site is visible without reading the column.
//
// Keyed off warehouse_id, not row order, so a warehouse keeps its colour across
// pages and filters. Classes are written out in full because Tailwind scans for
// literal strings and would purge anything assembled at runtime.
const WAREHOUSE_TINTS = [
    { row: "bg-sky-50/70",     badge: "bg-sky-100 text-sky-800",         dot: "bg-sky-500" },
    { row: "bg-amber-50/70",   badge: "bg-amber-100 text-amber-800",     dot: "bg-amber-500" },
    { row: "bg-violet-50/70",  badge: "bg-violet-100 text-violet-800",   dot: "bg-violet-500" },
    { row: "bg-emerald-50/70", badge: "bg-emerald-100 text-emerald-800", dot: "bg-emerald-500" },
    { row: "bg-rose-50/70",    badge: "bg-rose-100 text-rose-800",       dot: "bg-rose-500" },
    { row: "bg-teal-50/70",    badge: "bg-teal-100 text-teal-800",       dot: "bg-teal-500" },
    { row: "bg-indigo-50/70",  badge: "bg-indigo-100 text-indigo-800",   dot: "bg-indigo-500" },
    { row: "bg-orange-50/70",  badge: "bg-orange-100 text-orange-800",   dot: "bg-orange-500" },
];

function warehouseTint(warehouseId) {
    const id = Number(warehouseId);
    if (!Number.isFinite(id)) {
        return { row: "", badge: "bg-gray-100 text-gray-700", dot: "bg-gray-400" };
    }
    return WAREHOUSE_TINTS[id % WAREHOUSE_TINTS.length];
}

const tbody_stock = document.getElementById("warehouse-stock-tbody");
const closeBtn = document.getElementById("close-modal");
const searchInput_stock = document.getElementById("search-stock");
const statusFilter = document.getElementById("status-filter");
const LimitFilter = document.getElementById("limit-filter");
const Category = document.getElementById("category-filter2");

async function loadWarehouseStock(warehouseId, page = 1) {
    try {
        currentWarehouseId = warehouseId;

        tbody_stock.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-4 text-center text-rose-500">
                        Loading...
                    </td>
                </tr>
            `;

        // Only include filters if not empty
        const params = new URLSearchParams();
        const limit = LimitFilter.value.trim();

        const search = searchInput_stock.value.trim();
        const category_id = Category.value.trim();
        const status = statusFilter.value;
        const stock = document.getElementById("stock-filter").value;

        if (search) params.append("search", search);
        if (limit) params.append("limit", limit);
        if (status !== "") params.append("status", status);
        if (stock) params.append("stock", stock);
        if (category_id) params.append("category_id", category_id);

        params.append("page", page);

        params.append("view", "summary");

        const res = await fetch(
            `/warehouses/${warehouseId}/stock?${params.toString()}`,
        );

        const result = await res.json();

        renderStockTable(result.data, result.current_page, result.per_page);
        renderPagination(result);
    } catch (err) {
        console.error(err);
        alert("Error fetching stock");
    }
}
function renderStockPagination(result) {
    const container = document.getElementById("paginationContainer_stock");
    const pageInfo = document.getElementById("pageInfo_stock");

    if (!container) return;

    container.innerHTML = "";

    if (result.per_page === "All") {
        if (pageInfo) {
            pageInfo.textContent = `Showing all ${result.total} items`;
        }
        return;
    }

    const currentPage = result.current_page;
    const lastPage = result.last_page;

    if (pageInfo) {
        pageInfo.textContent = `Page ${currentPage} of ${lastPage} | Total ${result.total}`;
    }

    // Prev
    const prevBtn = document.createElement("button");
    prevBtn.innerHTML = `<i class="fa-solid fa-chevron-left"></i>`;
    prevBtn.disabled = currentPage <= 1;
    prevBtn.className = stockPaginationBtnClass(prevBtn.disabled);
    prevBtn.onclick = () =>
        loadWarehouseStock(currentWarehouseId, currentPage - 1);
    container.appendChild(prevBtn);

    // Pages
    for (let i = 1; i <= lastPage; i++) {
        if (i === 1 || i === lastPage || Math.abs(i - currentPage) <= 2) {
            const btn = document.createElement("button");
            btn.textContent = i;
            btn.className = "page-btn" + (i === currentPage ? " page-btn-active" : "");

            btn.onclick = () => loadWarehouseStock(currentWarehouseId, i);
            container.appendChild(btn);
        }
    }

    // Next
    const nextBtn = document.createElement("button");
    nextBtn.innerHTML = `<i class="fa-solid fa-chevron-right"></i>`;
    nextBtn.disabled = currentPage >= lastPage;
    nextBtn.className = stockPaginationBtnClass(nextBtn.disabled);
    nextBtn.onclick = () =>
        loadWarehouseStock(currentWarehouseId, currentPage + 1);
    container.appendChild(nextBtn);
}

function stockPaginationBtnClass(disabled) {
    return "page-btn";
}
function renderStockTable(products, currentPage = 1, perPage = 10) {
    tbody_stock.innerHTML = "";

    if (products.length === 0) {
        tbody_stock.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4 text-rose-500">
                    No data found
                </td>
            </tr>
        `;
        return;
    }

    products.forEach((p, index) => {
        const rowNumber = (currentPage - 1) * perPage + index + 1;
        const wh = warehouseTint(p.warehouse_id);

        tbody_stock.insertAdjacentHTML(
            "beforeend",
            `
            <tr class="${wh.row} hover:brightness-95 transition-colors">
                <td class="px-3 text-left text-sm text-gray-600">${rowNumber}</td>
                <td class="px-3 py-1 text-left">
                    <img src="${
                        p.image
                            ? `/assets/startic_img/${encodeURIComponent(p.image)}`
                            : "/assets/defult/placeholder.png"
                    }"
                        alt="" loading="lazy"
                        onerror="this.src='/assets/defult/placeholder.png'"
                        class="w-16 h-16 rounded-lg object-cover border border-slate-200 bg-white">
                </td>
                <td class="px-3 text-left text-sm font-medium">${p.product_name}</td>
                <td class="px-3 text-left text-sm">${p.category_name ?? "NA"}</td>
                <td class="px-3 text-end text-sm font-bold">${parseFloat(p.total_quantity)}</td>
                <td class="px-3 text-left text-sm">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ${wh.badge}">
                        <span class="w-1.5 h-1.5 rounded-full ${wh.dot}"></span>${p.warehouse_name ?? ""}
                    </span>
                </td>
                <td class="px-3 text-center text-sm">${p.lot_count}</td>
                <td class="px-3 text-center text-sm ${p.status ? "text-green-600" : "text-red-500"}">
                    ${p.status ? "Active" : "Inactive"}
                </td>
                <td class="px-3 text-center text-sm">
                    <div class="inline-flex items-center gap-1.5">
                        <button onclick="openStockDetailModal(${p.product_id}, '${(p.product_name ?? "").replace(/'/g, "&#39;")}')"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-sky-500 text-white rounded-xl hover:bg-sky-600 transition text-xs font-semibold">
                            <i class="fa-solid fa-magnifying-glass"></i> View Detail
                        </button>
                        ${
                            parseFloat(p.total_quantity) > 0 && typeof canTransferStock !== "undefined" && canTransferStock
                                ? `<button onclick="openFefoTransferModal(${p.product_id}, '${(p.product_name ?? "").replace(/'/g, "&#39;")}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-green-500 text-white rounded-xl hover:bg-green-600 transition text-xs font-semibold"
                                    title="Transfer without picking a specific lot (FEFO)">
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer
                                </button>`
                                : ""
                        }
                    </div>
                </td>
            </tr>
            `,
        );
    });
}

// ---- Stock Detail modal (per-product lot/warehouse/bin breakdown) ----
let currentStockDetailProductId = null;
async function openStockDetailModal(productId, productName = "") {
    currentStockDetailProductId = productId;
    const modal = document.getElementById("stockDetailModal");
    const tbody = document.getElementById("stock-detail-tbody");
    document.getElementById("stockDetailProductName").textContent = productName;
    tbody.innerHTML = `
        <tr>
            <td colspan="10" class="px-4 py-4 text-center text-rose-500">Loading...</td>
        </tr>
    `;
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    try {
        const params = new URLSearchParams();
        params.append("product_id", productId);
        params.append("limit", "All");

        const stockFilter = document.getElementById("stock-filter")?.value;
        if (stockFilter) params.append("stock", stockFilter);

        // Respect whichever warehouse the list is currently filtered to —
        // "0" (all) only when the user actually has "All Warehouse" selected.
        const detailWarehouseId = currentWarehouseId || 0;
        const res = await fetch(`/warehouses/${detailWarehouseId}/stock?${params.toString()}`);
        const result = await res.json();
        renderStockDetailTable(result.data || []);
    } catch (err) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-4 py-4 text-center text-rose-500">Failed to load stock detail</td>
            </tr>
        `;
    }
}

function closeStockDetailModal() {
    const modal = document.getElementById("stockDetailModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    currentStockDetailProductId = null;
}

function renderStockDetailTable(rows) {
    const tbody = document.getElementById("stock-detail-tbody");
    tbody.innerHTML = "";

    if (rows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-4 text-rose-500">No data found</td>
            </tr>
        `;
        return;
    }

    const warehouseColors = [
        "#EF4444", "#F97316", "#F59E0B", "#10B981", "#06B6D4",
        "#3B82F6", "#6366F1", "#8B5CF6", "#EC4899", "#14B8A6",
    ];

    rows.forEach((p, index) => {
        let expireText = "N/A";
        if (p.expire) {
            const d = new Date(p.expire);
            const day = String(d.getDate()).padStart(2, "0");
            const month = String(d.getMonth() + 1).padStart(2, "0");
            const year = d.getFullYear();
            expireText = `${day}/${month}/${year}`;
        }
        const color = warehouseColors[p.warehouse_id % warehouseColors.length];

        tbody.insertAdjacentHTML(
            "beforeend",
            `
            <tr class="hover:bg-green-50 transition-colors">
                <td class="px-3 text-left text-sm text-gray-600">${index + 1}</td>
                <td class="px-3 text-left text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium"
                        style="background:${color}20; color:${color}; border:1px solid ${color}50;">
                        ${p.warehouse_name ?? "NA"}
                    </span>
                </td>
                <td class="px-3 text-left text-sm">${p.bin_name ?? "—"}</td>
                <td class="px-3 text-left text-sm">${p.lot ?? ""}</td>
                <td class="px-3 text-left text-sm">${expireText}</td>
                <td class="px-3 text-end text-sm font-bold">${parseFloat(p.quantity)}</td>
                <td class="px-3 text-end text-sm">${p.cost_price ?? 0}</td>
                <td class="px-3 text-end text-sm">${Number(p.sell_price_vat ?? 0).toFixed(2)}</td>
                <td class="px-3 text-center text-sm ${p.status ? "text-green-600" : "text-red-500"}">
                    ${p.status ? "Active" : "Inactive"}
                </td>
                <td class="px-3 text-center text-sm">
                    ${
                        p.quantity > 0 && typeof canTransferStock !== "undefined" && (canTransferStock || canMoveStock)
                            ? `<button onclick='openLotModal_transfer(${p.lot_id}, ${JSON.stringify({
                                  product_name: p.product_name,
                                  lot: p.lot ?? "",
                                  quantity: parseFloat(p.quantity) || 0,
                                  unit: p.unit ?? "",
                                  warehouse_id: p.warehouse_id ?? null,
                                  warehouse_name: p.warehouse_name ?? "",
                                  bin_id: p.bin_id ?? null,
                                  bin_name: p.bin_name ?? "",
                              }).replace(/'/g, "&#39;")})' class="px-2 py-1 bg-green-500 text-white rounded-xl hover:bg-green-600 transition"><i class="fa-classic fa-solid fa-arrow-right-arrow-left"></i></button>`
                            : ""
                    }
                </td>
            </tr>
            `,
        );
    });
}

function renderPagination(result) {
    const container = document.getElementById("paginationContainer_stock");
    container.innerHTML = "";

    if (!result.last_page || result.last_page <= 1) return;

    const currentPage = result.current_page;
    const lastPage = result.last_page;

    // Previous Button
    if (currentPage > 1) {
        container.insertAdjacentHTML(
            "beforeend",
            `
            <button class="page-btn"
                onclick="loadWarehouseStock(currentWarehouseId, ${currentPage - 1})">
                Prev
            </button>
        `,
        );
    }

    // Page Numbers
    for (let i = 1; i <= lastPage; i++) {
        container.insertAdjacentHTML(
            "beforeend",
            `
            <button
                class="page-btn ${i === currentPage ? "page-btn-active" : ""}"
                onclick="loadWarehouseStock(currentWarehouseId, ${i})">
                ${i}
            </button>
        `,
        );
    }

    // Next Button
    if (currentPage < lastPage) {
        container.insertAdjacentHTML(
            "beforeend",
            `
            <button class="page-btn"
                onclick="loadWarehouseStock(currentWarehouseId, ${currentPage + 1})">
                Next
            </button>
        `,
        );
    }
}

// Get Category on Click New
document
    .getElementById("btnAddProduct")
    .addEventListener("click", async function () {
        const select = document.getElementById("categorySelect");

        // Reset
        select.innerHTML = `<option value="">Loading categories...</option>`;

        try {
            const response = await fetch("/categories");
            const categories = await response.json();

            select.innerHTML = `<option value="">-- Select Category --</option>`;

            categories.forEach((cat) => {
                select.innerHTML += `
                <option value="${cat.id}">
                    ${cat.name}
                </option>
            `;
            });
        } catch (error) {
            console.error(error);
            select.innerHTML = `<option value="">Failed to load categories</option>`;
        }
    });
document
    .getElementById("productImage")
    .addEventListener("change", function (e) {
        const preview = document.getElementById("imagePreview");
        const file = e.target.files[0];

        if (!file) {
            preview.classList.add("hidden");
            preview.src = "";
            return;
        }

        if (!file.type.startsWith("image/")) {
            alert("Please select an image file");
            e.target.value = "";
            preview.classList.add("hidden");
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            preview.src = event.target.result;
            preview.classList.remove("hidden");
        };
        reader.readAsDataURL(file);
    });

// ADD Product
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("AddProductForm");
    if (!form) return;
    const submitBtn = form.querySelector('button[type="submit"]');

    // Live image preview
    const imageInput = document.getElementById("productImage");
    const imagePreviewContainer = document.createElement("div");
    imagePreviewContainer.id = "imagePreview";
    imageInput.parentNode.appendChild(imagePreviewContainer);

    imageInput.addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (!file) {
            imagePreviewContainer.innerHTML = "";
            return;
        }
        const reader = new FileReader();
        reader.onload = function (ev) {
            imagePreviewContainer.innerHTML = `<img src="${ev.target.result}" alt="Preview" class="mt-2 w-32 h-32 object-cover rounded" />`;
        };
        reader.readAsDataURL(file);
    });

    // Track Stock only makes sense for physical products — hide it for
    // Service / Expense types (and make sure it can't submit checked while
    // hidden, since expense/service items should never carry lot tracking)
    const typeSelect = document.getElementById("type");
    const trackStockField = document.getElementById("trackStockField");
    const trackStockCheckbox = document.getElementById("trackStockCheckbox");

    function syncTrackStockVisibility() {
        if (!typeSelect || !trackStockField || !trackStockCheckbox) return;
        const isPhysicalProduct = typeSelect.value === "product";
        trackStockField.classList.toggle("hidden", !isPhysicalProduct);
        if (!isPhysicalProduct) trackStockCheckbox.checked = false;
    }

    typeSelect?.addEventListener("change", syncTrackStockVisibility);
    syncTrackStockVisibility();

    // Async form submit
    form.addEventListener("submit", async function (e) {
        e.preventDefault();

        submitBtn.disabled = true;
        submitBtn.innerText = "Saving...";

        try {
            const response = await fetch("/products/store", {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'input[name="_token"]',
                    ).value,
                },
                body: new FormData(form),
            });

            let data;
            try {
                data = await response.json();
            } catch {
                data = {}; // in case response is not JSON
            }

            if (!response.ok) {
                // Show server message if exists, else fallback
                const message =
                    data.message ||
                    `Error ${response.status}: ${response.statusText}`;
                throw new Error(message);
            }

            // ✅ SUCCESS
            showToast({
                message: data.message || "Product added successfully",
                type: "success",
            });
            loadProducts(1);
            form.reset();
            imagePreviewContainer.innerHTML = "";

            document
                .querySelector('[data-modal-hide="default-modal-add-product"]')
                ?.click();
        } catch (err) {
            // Always show toast
            showToast({
                message: err.message || "Server error. Please try again.",
                type: "error",
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = "Save Product";
        }
    });
});

function openModal() {
    document.getElementById("tableModal").classList.remove("hidden");
    document.getElementById("tableModal").classList.add("flex");
}

function closeModal() {
    document.getElementById("tableModal").classList.add("hidden");
    document.getElementById("tableModal").classList.remove("flex");
}

async function saveTable() {
    const name = document.getElementById("table_name").value.trim();

    if (!name) {
        alert("Table name is required");
        return;
    }

    try {
        const response = await fetch("/restaurant-tables/store", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
                Accept: "application/json",
                "Content-Type": "application/json", // 🔥 must have
            },
            body: JSON.stringify({
                name: name,
            }),
        });

        const data = await response.json();

        if (data.success) {
            showToast({
                message: "Table Created Successfully!",
                type: "success",
            });
            closeModal();
            showTableModal(0, "ALL");
        } else {
            showToast({
                message: "Table Fail!",
                type: "error",
            });
        }
    } catch (error) {
        console.error(error);
        alert("Something went wrong.");
    }
}

/*
|--------------------------------------------------------------------------
| Date Filter Handler
|--------------------------------------------------------------------------
*/
function handleDateFilter() {
    const fromDate = document.getElementById("from_date").value;
    const toDate = document.getElementById("to_date").value;

    if (fromDate && toDate) {
        loadSales();
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Data
|--------------------------------------------------------------------------
*/
// Element doesn't exist when the current user lacks sales_report.view — hide, don't crash.
if (document.getElementById("sale_data")) {
    document.getElementById("sale_data").addEventListener("click", () => {
        fetchSalesData(1); // start from page 1
        loadCategories();
        loadPaymentMethods();
    });
}
// Example: filter inputs
const filters = [
    "from_date",
    "to_date",
    "invoice_paymentMethod",
    "customer_filter",
    "product_search",
    "category_filter",
    "sale_view_limit",
];
function clear_filter_invoice() {
    const filters = [
        "from_date",
        "to_date",
        "invoice_paymentMethod",
        "customer_filter",
        "ProductSearchInput_sale_invoice",
        "category_filter",
        "sale_view_limit",
        "document_search_invoice", // add this
        "product_search",
        "customer_search",
    ];

    filters.forEach((id) => {
        const el = document.getElementById(id);

        if (el) {
            if (el.tagName === "SELECT") {
                el.selectedIndex = 0;
            } else {
                el.value = "";
            }
        }
    });

    fetchSalesData();
}
document
    .querySelector("#document_search_invoice")
    .addEventListener("keyup", () => {
        fetchSalesData(1);
    });

filters.forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;

    el.addEventListener("change", () => {
        // Only fetch if both dates are filled (if date filters)
        if (id === "from_date" || id === "to_date") {
            const from = document.getElementById("from_date").value;
            const to = document.getElementById("to_date").value;
            if (!from || !to) return;
        }
        fetchSalesData(1);
    });
});

function fetchSalesData(page = 1) {
    const params = new URLSearchParams();

    const from_date = document.getElementById("from_date").value;
    const to_date = document.getElementById("to_date").value;
    const invoice_paymentMethod = document.getElementById(
        "invoice_paymentMethod",
    ).value;
    const customer_filter = document.getElementById("customer_filter").value;
    const ProductSearchInput = document.getElementById("product_search").value;
    const category_filter = document.getElementById("category_filter").value;
    const sale_view_limit = document.getElementById("sale_view_limit").value;

    if (from_date) params.append("from_date", from_date);
    if (to_date) params.append("to_date", to_date);
    if (invoice_paymentMethod)
        params.append("invoice_paymentMethod", invoice_paymentMethod);
    if (customer_filter) params.append("customer_filter", customer_filter);
    if (ProductSearchInput)
        params.append("ProductSearchInput", ProductSearchInput);
    if (category_filter) params.append("category_filter", category_filter);
    if (sale_view_limit) params.append("sale_view_limit", sale_view_limit);

    const document_no = document.querySelector(
        "#document_search_invoice",
    ).value;
    if (document_no) params.append("document_no", document_no);
    params.append("page", page);

    fetch(`/sales-report?${params.toString()}`)
        .then((res) => res.json())
        .then((data) => renderTable(data))
        .catch((err) => console.error(err));
}

/*
|--------------------------------------------------------------------------
| Debounce
|--------------------------------------------------------------------------
*/
function debounce(func, delay) {
    let timeout;
    return function () {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(), delay);
    };
}

let currentSortColumn = "h.invoice_date";
let currentSortDirection = "asc";

document.querySelectorAll("#Table-sale-list th[data-column]").forEach((th) => {
    th.addEventListener("click", function () {
        const column = this.getAttribute("data-column");

        // toggle direction
        if (currentSortColumn === column) {
            currentSortDirection =
                currentSortDirection === "asc" ? "desc" : "asc";
        } else {
            currentSortColumn = column;
            currentSortDirection = "asc";
        }

        updateSortIcons();

        loadSales(1);
    });
});
function updateSortIcons() {
    document.querySelectorAll(".sort-icon").forEach((icon) => {
        icon.innerText = "↕";
    });

    const activeTh = document.querySelector(
        `th[data-column="${currentSortColumn}"] .sort-icon`,
    );

    if (activeTh) {
        activeTh.innerText = currentSortDirection === "asc" ? "↑" : "↓";
    }
}
// Helper functions
function formatMoney(value) {
    return (Number(value) || 0).toFixed(2); // always 2 decimals for money
}
function formatPercent(value) {
    return Math.round(Number(value) || 0); // round to integer for percent
}
function renderTable(response) {
    const tbody = document.getElementById("salesTableBody");
    const paginationContainer = document.getElementById(
        "paginationContainer_sale_invoice",
    );
    const pageInfo = document.getElementById("pageInfo_sale_invoice");

    tbody.innerHTML = "";
    paginationContainer.innerHTML = "";
    pageInfo.innerHTML = "";

    if (!response.data || response.data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="24" class="text-center py-4 text-gray-500">
                    No data found
                </td>
            </tr>
        `;
        return;
    }

    let rowCount = 0;

    const rows = [];

  response.data.forEach((header) => {
        const lines = header.lines || [];

        lines.forEach((line) => {
            rowCount++;

            const quantity = Number(line.quantity) || 0;
            const discountPercent = Number(line.discount_percent) || 0;
            const vatPercent = Number(line.vat) || 0;

            // USD in English; Riel in Khmer, converted with the invoice's own
            // saved factor. listMoney() supplies the currency symbol itself, so
            // the cells below no longer append one.
            const usd = (v) => listMoney(v, header.factor);

            const unitPrice        = usd(line.unit_price);
            const sellPrice        = usd(line.sell_price);
            const lineAmount       = usd(line.line_amount);
            const discountAmount   = usd(line.discount_amount);
            const vatAmount        = usd(line.vat_amount);
            const netAmount        = usd(line.net_amount);
            const grandTotalAmount = usd(line.grand_total_amount);

            rows.push(`
    <tr class="text-nowrap" onclick="hightlightRow('Table-sale-list', this)">
        <td>${rowCount}</td>
        <td>${header.invoice_number ?? ""}</td>
        <td>${header.source_no ?? ""}</td>
        <td>${header.created_at ? new Date(header.created_at).toLocaleString("en-GB") : ""}</td>
        <td>${header.contact_name ?? ""}</td>
        <td>${header.phone ?? ""}</td>
        <td>${header.address ?? ""}</td>
        <td>${header.invoice_date ? new Date(header.invoice_date).toLocaleDateString("en-GB") : ""}</td>
        <td>${header.payment_method ?? ""}</td>
        <td>${header.customer_type ?? ""}</td>

        <td>${line.name ?? ""}</td>
        <td>${line.variant ?? ""}</td>
        <td>${line.description ?? ""}</td>
        <td class="text-right">${quantity}</td>
        <td>${line.unit ?? ""}</td>

        <td class="text-right">${unitPrice}</td>
        <td class="text-right">${sellPrice}</td>
        <td class="text-right">${lineAmount}</td>

        <td class="text-right">${formatPercent(discountPercent)} %</td>
        <td class="text-right">${discountAmount}</td>

        <td class="text-right">${formatPercent(vatPercent)} %</td>
        <td class="text-right">${vatAmount}</td>

        <td class="text-right">${netAmount}</td>
        <td class="text-right">${grandTotalAmount}</td>
    </tr>
    `);
        });
    });
    tbody.innerHTML = rows.join("");

    renderSaleInvoicePagination(response);
}
function renderSaleInvoicePagination(res) {
    const container = document.getElementById(
        "paginationContainer_sale_invoice",
    );
    const pageInfo = document.getElementById("pageInfo_sale_invoice");

    container.innerHTML = "";

    const currentPage = Number(res.current_page) || 1;
    const totalPages = Number(res.last_page) || 1;

    pageInfo.textContent = `Page ${currentPage} of ${totalPages} | Total ${res.total}`;

    // helper
    function createBtn(label, page, active = false, disabled = false) {
        const btn = document.createElement("button");

        btn.innerHTML = label;
        btn.disabled = disabled;

        btn.className = "page-btn" + (active ? " page-btn-active" : "");

        if (!disabled) {
            btn.onclick = () => fetchSalesData(page);
        }

        return btn;
    }

    // Prev
    container.appendChild(
        createBtn("Prev", currentPage - 1, false, currentPage === 1),
    );

    // start/end range
    let start = Math.max(1, currentPage - 4);
    let end = Math.min(totalPages, currentPage + 4);

    // left ...
    if (start > 1) {
        container.appendChild(createBtn(1, 1));

        if (start > 2) {
            const dot = document.createElement("span");
            dot.innerHTML = "...";
            dot.className = "px-2 text-gray-500";
            container.appendChild(dot);
        }
    }

    // page buttons
    for (let i = start; i <= end; i++) {
        container.appendChild(createBtn(i, i, i === currentPage));
    }

    // right ...
    if (end < totalPages) {
        if (end < totalPages - 1) {
            const dot = document.createElement("span");
            dot.innerHTML = "...";
            dot.className = "px-2 text-gray-500";
            container.appendChild(dot);
        }

        container.appendChild(createBtn(totalPages, totalPages));
    }

    // Next
    container.appendChild(
        createBtn("Next", currentPage + 1, false, currentPage === totalPages),
    );
}
async function loadCategories() {
    try {
        const res = await fetch("/sales/categories");
        const categories = await res.json();

        const select = document.getElementById("category_filter");
        select.innerHTML = '<option value="">All Categories</option>'; // Clear existing options
        categories.forEach((category) => {
            const option = document.createElement("option");
            option.value = category;
            option.textContent = category;
            select.appendChild(option);
        });
    } catch (error) {
        console.error("Error loading categories:", error);
    }
}

async function loadPaymentMethods() {
    try {
        const res = await fetch("/sales/payment-methods");
        const methods = await res.json();

        const select = document.getElementById("invoice_paymentMethod");
        select.innerHTML = '<option value="">All Payment</option>'; // Clear existing options
        methods.forEach((method) => {
            const option = document.createElement("option");
            option.value = method; // value for filtering
            option.textContent = method; // text shown to user
            select.appendChild(option);
        });
    } catch (error) {
        console.error("Error loading payment methods:", error);
    }
}
const customerInput = document.getElementById("customer_search");
const customerList = document.getElementById("customer_list");
const customerHidden = document.getElementById("customer_filter");

let debounceTimer;

customerInput.addEventListener("input", function () {
    clearTimeout(debounceTimer);

    debounceTimer = setTimeout(() => {
        fetchCustomers(this.value);
    }, 300); // debounce 300ms
});

async function fetchCustomers(search = "") {
    try {
        const res = await fetch(`/sales/customer-search?search=${search}`);
        const customers = await res.json();

        customerList.innerHTML = "";

        if (customers.length === 0) {
            customerList.classList.add("hidden");
            return;
        }

        customers.forEach((customer) => {
            const li = document.createElement("li");
            li.textContent = customer.name;
            li.className = "px-3 py-2 hover:bg-gray-100 cursor-pointer";

            li.addEventListener("click", () => {
                customerInput.value = customer.name;
                customerHidden.value = customer.id;
                customerList.classList.add("hidden");

                fetchSalesData(1); // auto filter
            });

            customerList.appendChild(li);
        });

        customerList.classList.remove("hidden");
    } catch (error) {
        console.error("Customer search error:", error);
    }
}

// Hide dropdown when clicking outside
document.addEventListener("click", function (e) {
    if (!customerInput.contains(e.target) && !customerList.contains(e.target)) {
        customerList.classList.add("hidden");
    }
});

const productInput = document.getElementById("product_search");
const productDatalist = document.getElementById("product_datalist");
const productHidden = document.getElementById(
    "ProductSearchInput_sale_invoice",
);

let productDebounce;

productInput.addEventListener("input", function () {
    clearTimeout(productDebounce);

    productDebounce = setTimeout(() => {
        fetchProducts(this.value);
    }, 300);
});

async function fetchProducts(search = "") {
    try {
        const res = await fetch(`/sales/product-search?search=${search}`);
        const products = await res.json();

        productDatalist.innerHTML = "";
        products.forEach((product) => {
            const option = document.createElement("option");
            option.value = product; // what user sees
            option.dataset.code = product;
            productDatalist.appendChild(option);
        });
    } catch (error) {
        console.error("Product search error:", error);
    }
}

function downloadSales() {
    const params = new URLSearchParams({
        document:    document.getElementById("document_search_invoice")?.value || "",
        from_date:   document.getElementById("from_date")?.value || "",
        to_date:     document.getElementById("to_date")?.value || "",
        payment:     document.getElementById("invoice_paymentMethod")?.value || "",
        customer_id: document.getElementById("customer_filter")?.value || "",
        product_id:  document.getElementById("ProductSearchInput_sale_invoice")?.value || "",
        category:    document.getElementById("category_filter")?.value || "",
    });
    window.open("/sale-report/export-excel?" + params.toString(), "_blank");
}

document
    .getElementById("btnPrintCustomer")
    .addEventListener("click", function () {
        let table = document.querySelector("#customer-list");

        if (!table) {
            alert("No customer data to print.");
            return;
        }

        let printWindow = window.open("", "", "width=1200,height=800");

        let now = new Date();
        let formattedDate = now.toLocaleString();

        printWindow.document.write(`
        <html>
        <head>
            <title>Customer List</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    padding: 20px;
                }

                h2 {
                    text-align: center;
                    margin-bottom: 5px;
                }

                .print-date {
                    text-align: right;
                    font-size: 12px;
                    margin-bottom: 15px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 12px;
                }

                th, td {
                    border: 1px solid #000;
                    padding: 6px;
                    text-align: left;
                }

                th {
                    background: #f2f2f2;
                }

                @media print {
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <h2>Customer List</h2>
            <div class="print-date">Printed: ${formattedDate}</div>
            ${table.outerHTML}
        </body>
        </html>
    `);

        printWindow.document.close();
        printWindow.focus();

        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    });


// document.getElementById("btnPrintProductMenu").addEventListener("click", () => {
//     if (!allProducts || !allProducts.length) {
//         alert("No products loaded to print!");
//         return;
//     }

//     // Group products by category
//     const categories = {};
//     allProducts.forEach((product) => {
//         const category = (product.category && product.category.name) || "Other";
//         if (!categories[category]) categories[category] = [];
//         categories[category].push(product);
//     });

//     // Build HTML: categories with underline
//     let html = "";

//     Object.keys(categories).forEach((cat) => {
//         html += `<div class="menu-category-block">
//                     <div class="menu-category-title" contenteditable="true">${cat}</div>`;

//         categories[cat].forEach((item) => {
//             const price = parseFloat(item.sell_price || item.price) || 0;
//             const discount = parseFloat(item.discount_percent || 0);
//             const imgSrc = item.image
//                 ? `/assets/startic_img/${encodeURIComponent(item.image)}`
//                 : "";

//             html += `
//                 <div class="menu-card">
//                     ${
//                         imgSrc
//                             ? `<img src="${imgSrc}" class="menu-img">`
//                             : `<div class="menu-img placeholder">No Image</div>`
//                     }
//                     <div class="menu-details">
//                         <div class="menu-name" contenteditable="true">${item.name}</div>
//                         ${
//                             discount > 0
//                                 ? `<div class="menu-price" contenteditable="true">
//                                     <del>$${price.toFixed(2)}</del> → $${(price * (1 - discount / 100)).toFixed(2)} (${discount}% Off)
//                                </div>`
//                                 : `<div class="menu-price" contenteditable="true">$${price.toFixed(2)}</div>`
//                         }
//                     </div>
//                 </div>
//             `;
//         });

//         html += `</div>`; // close category block
//     });

//     openEditableMenuPreview(html);
// });

function openEditableMenuPreview(content) {
    const win = window.open(
        "",
        "",
        "width=1200,height=800,scrollbars=yes,resizable=yes",
    );
    win.document.write(`
        <html>
        <head>
            <title>Editable Product Menu</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&family=Playfair+Display:wght@700&display=swap');
                body { font-family: 'Montserrat', sans-serif; padding:20px; background:#fff; color:#333; }

                /* Toolbar fixed at bottom */
                .toolbar {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    width: 100%;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    background: #f8f8f8;
                    border-top: 1px solid #ccc;
                    padding: 10px;
                    z-index: 999;
                }
                .toolbar label { font-size:12px; display:flex; flex-direction:column; }
                .toolbar input, .toolbar select, .toolbar button { padding:4px 6px; margin:2px 0; cursor:pointer; }

                /* Category */
                .menu-category-block { margin-bottom:30px; width:100%; }
                .menu-category-title {
                    font-size: 18px;
                    font-weight: 700;
                    margin-bottom: 10px;
                    text-align: center;
                    border-bottom: 2px solid #000;
                    padding-bottom: 3px;
                    width:100%;
                }

                /* Products grid */
                .menu-products {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr); /* default 4 columns */
                    gap: 15px;
                    width:100%;
                }

                /* Cards */
                .menu-card {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    padding: 5px;
                    background:#fff;
                    page-break-inside: avoid;
                    box-shadow:0 1px 3px rgba(0,0,0,0.1);
                    transition: transform 0.2s;
                }
                .menu-card:hover { transform: translateY(-2px); }

                .menu-img { width:100%; height:150px; object-fit:cover; border-radius:4px; margin-bottom:5px; }
                .menu-img.placeholder {
                    background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999; height:150px; width:100%; border-radius:4px; margin-bottom:5px;
                }

                .menu-details { text-align:center; width:100%; }
                .menu-name { font-weight:700; font-size:14px; text-transform:capitalize; }
                .menu-price { font-size:13px; color:#b33; margin-top:3px; font-weight:600; }
                .menu-price del { color:#888; font-weight:400; margin-right:4px; }

                [contenteditable="true"] { outline:none; padding:2px; }

                @media print { .toolbar { display:none; } body { -webkit-print-color-adjust: exact; } }
            </style>
        </head>
        <body>
            <div id="menuContainer">${content}</div>

            <div class="toolbar">
                <button id="printBtn">Print</button>
                <label>Columns
                    <input type="number" id="colInput" value="4" min="2" max="6" style="width:50px;">
                </label>
                <label>Card Height
                    <input type="number" id="cardHeightInput" value="150" min="50" max="400" style="width:50px;">px
                </label>
                <label><input type="checkbox" id="toggleImage" checked> Show Images</label>
                <label>Text Align
                    <select id="textAlignSelect">
                        <option value="left">Left</option>
                        <option value="center" selected>Center</option>
                        <option value="right">Right</option>
                    </select>
                </label>
                <label>Text Color
                    <input type="color" id="textColorPicker" value="#333">
                </label>
                <label>Font Size
                    <input type="number" id="fontSizeInput" value="14" min="8" max="30">
                </label>
            </div>

            <script>
                const menuContainer = document.getElementById('menuContainer');

                // Print
                document.getElementById('printBtn').onclick = () => { window.print(); };

                // Columns
                document.getElementById('colInput').oninput = (e) => {
                    const cols = parseInt(e.target.value) || 4;
                    menuContainer.querySelectorAll('.menu-products').forEach(grid => {
                        grid.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';
                    });
                };

                // Card Height
                document.getElementById('cardHeightInput').oninput = (e) => {
                    const h = parseInt(e.target.value) || 150;
                    menuContainer.querySelectorAll('.menu-img, .menu-img.placeholder').forEach(img => {
                        img.style.height = h + 'px';
                    });
                };

                // Show / hide images
                document.getElementById('toggleImage').onchange = (e) => {
                    const show = e.target.checked;
                    menuContainer.querySelectorAll('.menu-img, .menu-img.placeholder').forEach(img => {
                        img.style.display = show ? 'block' : 'none';
                    });
                };

                // Text Align
                document.getElementById('textAlignSelect').onchange = (e) => {
                    menuContainer.querySelectorAll('[contenteditable="true"]').forEach(el => {
                        el.style.textAlign = e.target.value;
                    });
                };

                // Text Color
                document.getElementById('textColorPicker').oninput = (e) => {
                    menuContainer.querySelectorAll('[contenteditable="true"]').forEach(el => {
                        el.style.color = e.target.value;
                    });
                };

                // Font Size
                document.getElementById('fontSizeInput').oninput = (e) => {
                    const size = parseInt(e.target.value) || 14;
                    menuContainer.querySelectorAll('[contenteditable="true"]').forEach(el => {
                        el.style.fontSize = size + 'px';
                    });
                };
            </script>
        </body>
        </html>
    `);
}

// Filters
// NOTE: this used to be gated behind `if (user_role === "admin" || user_role === "supervisor")`.
// That predates the permission system and conflicts with it: a user granted e.g. purchasing.* or
// expense.view through the new per-user permissions would see the button (blade now gates
// visibility via hasPermission()) but every function below was simply never defined for them,
// since their role is neither admin nor supervisor — "SaleReturn is not defined" etc. Real
// authorization is enforced server-side (route middleware / Livewire abort_unless), so these no
// longer need a client-side role gate at all.
{
    function SaleReturn() {
        document.getElementById("saleReturnModal").classList.remove("hidden");
        document.getElementById("saleReturnModal").classList.add("flex");

        // default today
        document.getElementById("return_date").value = new Date()
            .toISOString()
            .split("T")[0];
    }

    function closeSaleReturnModal() {
        document.getElementById("saleReturnModal").classList.add("hidden");
        document.getElementById("saleReturnModal").classList.remove("flex");
    }

    let isReturningSale = false;

    function confirmSaleReturn() {
        // stop double click
        if (isReturningSale) {
            return;
        }

        isReturningSale = true;

        const btn = document.getElementById("btnConfirmReturn");

        // disable button immediately
        if (btn) {
            btn.disabled = true;
            btn.classList.add("opacity-50", "pointer-events-none");
        }

        const returnDate = document.getElementById("return_date").value;

        const documentNo = document
            .getElementById("return_document_no")
            .value.trim();

        const remark = document.getElementById("return_remark").value.trim();

        if (!returnDate) {
            isReturningSale = false;

            if (btn) {
                btn.disabled = false;
                btn.classList.remove("opacity-50", "pointer-events-none");
            }

            showToast({
                message: "Please select return date",
                type: "warning",
            });

            return;
        }

        if (!documentNo) {
            isReturningSale = false;

            if (btn) {
                btn.disabled = false;
                btn.classList.remove("opacity-50", "pointer-events-none");
            }

            showToast({
                message: "Please input document no",
                type: "warning",
            });

            return;
        }

        const payload = {
            return_date: returnDate,
            document_no: documentNo,
            remark: remark,
        };

        console.log("sale return payload:", payload);

        Livewire.dispatch("sale-return", [payload]);

        closeSaleReturnModal();

        // optional timeout fallback
        setTimeout(() => {
            isReturningSale = false;

            if (btn) {
                btn.disabled = false;
                btn.classList.remove("opacity-50", "pointer-events-none");
            }
        }, 3000);
    }

    window.addEventListener("update-fail", (e) => {
        const message = e.detail.message;

        showToast({
            message: message,
            type: "error",
        });
    });
    window.addEventListener("return-success", (e) => {
        const message = e.detail.message;
        // const message = e.detail[0].message;

        showToast({
            message: message,
            type: "success",
        });
        reloadProducts();
        loadSaleOrders(1);
        closeSaleReturnModal();
    });

    let expenseSortColumn = "expense_date";
    let expenseSortDirection = "desc";

    document
        .querySelectorAll("#Table-expense-list th[data-column]")
        .forEach((th) => {
            th.addEventListener("click", () => {
                const col = th.dataset.column;

                if (expenseSortColumn === col) {
                    expenseSortDirection =
                        expenseSortDirection === "asc" ? "desc" : "asc";
                } else {
                    expenseSortColumn = col;
                    expenseSortDirection = "asc";
                }

                loadExpenses(1);

                document
                    .querySelectorAll("#Table-expense-list .sort-icon")
                    .forEach((s) => (s.textContent = "↕"));
            });
        });

    document
        .getElementById("expense_limit")
        ?.addEventListener("change", () => loadExpenses(1));
    document
        .getElementById("expense_from_date")
        ?.addEventListener("change", () => loadExpenses(1));
    document
        .getElementById("expense_to_date")
        ?.addEventListener("change", () => loadExpenses(1));

    document
        .getElementById("expense_search")
        ?.addEventListener("keyup", () => loadExpenses(1));

    function escapeHtml(s) {
        return String(s ?? "").replace(
            /[&<>"']/g,
            (c) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#39;",
                })[c],
        );
    }

    /**
     * Reverse an expense entered by mistake.
     *
     * Writes a negative mirror row sharing the original's expense_code — the
     * original is never edited or deleted, so the audit trail keeps both sides.
     * The server re-checks the permission and the outstanding amount, so a
     * double-click or a stale list cannot over-refund.
     */
    async function refundExpense(id, name) {
        const reason = prompt(
            `Refund "${name}"?\n\n` +
                `This adds a negative row that cancels it out. The original entry is kept.\n\n` +
                `Reason (optional):`,
        );
        if (reason === null) return; // user cancelled the prompt

        try {
            const res = await fetch("/expenses/refund", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector(
                        'input[name="_token"]',
                    ).value,
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    expense_id: id,
                    reason: reason.trim() || null,
                }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.status === false) {
                showToast({
                    message:
                        data.message ||
                        (res.status === 403
                            ? "You do not have permission to refund expenses."
                            : `Refund failed (${res.status})`),
                    type: "error",
                });
                return;
            }

            showToast({
                message: data.message || "Expense refunded.",
                type: "success",
            });
            loadExpenses(1);
        } catch (err) {
            showToast({
                message: "Refund failed — could not reach the server.",
                type: "error",
            });
        }
    }
    // Inline onclick resolves against global scope, but this file's contents
    // sit inside a block, so the handler has to be exported explicitly.
    window.refundExpense = refundExpense;

    function loadExpenses(page = 1) {
        const params = new URLSearchParams({
            page: page,

            from_date:
                document.getElementById("expense_from_date")?.value || "",
            to_date: document.getElementById("expense_to_date")?.value || "",
            search: document.getElementById("expense_search")?.value || "",
            limit: document.getElementById("expense_limit")?.value || 50,

            sort_column: expenseSortColumn,
            sort_direction: expenseSortDirection,
        });

        fetch(`/expenses/latest?${params.toString()}`)
            .then((res) => res.json())
            .then((res) => {
                const tbody = document.getElementById("expense_table_body");
                tbody.innerHTML = "";

                const expenses = res.data?.data ?? [];

                // 7th column only exists when the Action header was rendered.
                const colCount = canRefundExpense ? 7 : 6;

                if (!res.status || expenses.length === 0) {
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="${colCount}" class="text-center py-6">
                            No expense found
                        </td>
                    </tr>
                `;
                    return;
                }

                expenses.forEach((expense, index) => {
                    const rowNo = res.data.from
                        ? res.data.from + index
                        : index + 1;

                    // A refund is a negative mirror of the row it reverses, so
                    // it is shown in red and cannot itself be refunded.
                    const isRefund =
                        expense.refunded_from_id != null ||
                        Number(expense.amount) < 0;

                    let action = "";
                    if (canRefundExpense) {
                        // The server tells us what is still refundable, so a row
                        // that is already fully refunded shows no button at all
                        // rather than offering one that would come back 422.
                        let cell;
                        if (isRefund) {
                            cell = `<span class="text-xs text-slate-400">${
                                expense.refund_reason
                                    ? escapeHtml(expense.refund_reason)
                                    : "refund"
                            }</span>`;
                        } else if (!expense.is_refundable) {
                            cell = `<span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                        <i class="fa-solid fa-check"></i>Refunded
                                    </span>`;
                        } else {
                            cell = `<button type="button"
                                        onclick="event.stopPropagation(); refundExpense(${expense.id}, '${escapeHtml(
                                            expense.expense_name ?? "",
                                        ).replace(/'/g, "\\'")}')"
                                        class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600 border border-rose-200
                                               hover:bg-rose-100 text-xs font-semibold">
                                        <i class="fa-solid fa-rotate-left mr-1"></i>Refund
                                    </button>`;
                        }
                        action = `<td class="border px-3 py-1 text-center">${cell}</td>`;
                    }

                    tbody.innerHTML += `
                    <tr class="hover:bg-slate-50 ${isRefund ? "text-rose-600" : ""}" onclick="hightlightRow('Table-expense-list', this)">
                        <td class="border px-3 py-1">${rowNo}</td>
                        <td class="border px-3 py-1">${formatDate(expense.expense_date)}</td>
                        <td class="border px-3 py-1">${expense.expense_code ?? ""}</td>
                        <td class="border px-3 py-1">${expense.expense_name ?? ""}</td>
                        <td class="border px-3 py-1 text-right font-bold">${listMoney(expense.amount, expense.factor)}</td>
                        <td class="border px-3 py-1">${expense.note ?? ""}</td>
                        ${action}
                    </tr>
                `;
                });

                renderExpensePagination(res.data);
            });
    }
    function renderExpensePagination(data) {
        const container = document.getElementById(
            "paginationContainer_expense",
        );

        const info = document.getElementById("pageInfo_expense");

        if (!container || !info) return;

        container.innerHTML = "";

        if (!data || !data.last_page) return;

        info.textContent = `Page ${data.current_page} of ${data.last_page}`;

        // PREV
        const prevBtn = document.createElement("button");

        prevBtn.innerHTML = `
        <i class="fa-solid fa-chevron-left"></i>
    `;

        prevBtn.disabled = data.current_page === 1;

        prevBtn.className = "page-btn";

        prevBtn.onclick = () => loadExpenses(data.current_page - 1);

        container.appendChild(prevBtn);

        // PAGE NUMBERS
        let start = Math.max(1, data.current_page - 2);
        let end = Math.min(data.last_page, data.current_page + 2);

        // first page
        if (start > 1) {
            container.appendChild(createPageButton(1, data.current_page));

            if (start > 2) {
                const dots = document.createElement("span");
                dots.innerHTML = "...";
                dots.className = "px-2 text-gray-400";

                container.appendChild(dots);
            }
        }

        for (let i = start; i <= end; i++) {
            container.appendChild(createPageButton(i, data.current_page));
        }

        // last page
        if (end < data.last_page) {
            if (end < data.last_page - 1) {
                const dots = document.createElement("span");
                dots.innerHTML = "...";
                dots.className = "px-2 text-gray-400";

                container.appendChild(dots);
            }

            container.appendChild(
                createPageButton(data.last_page, data.current_page),
            );
        }

        // NEXT
        const nextBtn = document.createElement("button");

        nextBtn.innerHTML = `
        <i class="fa-solid fa-chevron-right"></i>
    `;

        nextBtn.disabled = data.current_page === data.last_page;

        nextBtn.className = "page-btn";

        nextBtn.onclick = () => loadExpenses(data.current_page + 1);

        container.appendChild(nextBtn);
    }

    function createPageButton(page, currentPage) {
        const btn = document.createElement("button");

        btn.innerHTML = page;

        btn.className = "page-btn" + (page === currentPage ? " page-btn-active" : "");

        btn.onclick = () => loadExpenses(page);

        return btn;
    }
    function updateSortIcons() {
        document.querySelectorAll(".sortable").forEach((th) => {
            const column = th.dataset.column;

            const label = th.textContent.replace(/[↑↓↕]/g, "").trim();

            if (column === expenseSortColumn) {
                th.innerHTML = `${label} ${expenseSortDirection === "asc" ? "↑" : "↓"}`;
            } else {
                th.innerHTML = `${label} ↕`;
            }
        });
    }
    document.querySelectorAll(".sortable").forEach((th) => {
        th.addEventListener("click", () => {
            const col = th.dataset.column;

            // toggle sort
            if (expenseSortColumn === col) {
                expenseSortDirection =
                    expenseSortDirection === "asc" ? "desc" : "asc";
            } else {
                expenseSortColumn = col;
                expenseSortDirection = "asc";
            }

            // update arrows
            updateSortIcons();

            // reload data
            loadExpenses(1);
        });
    });
    updateSortIcons();
    function formatDate(date) {
        if (!date) return "";

        return new Date(date).toLocaleDateString("en-GB");
    }

    function formatNumber(number) {
        return Number(number || 0).toLocaleString("en-US", {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        });
    }

    // Hook Edit button (doesn't exist when the current user lacks product.edit)
    if (document.getElementById("btnEditProduct")) {
        document.getElementById("btnEditProduct").addEventListener("click", () => {
            openUpdateProductModal();
        });
    }

    let selectedProductId = null;

    // Get ID Product
    function getSelectedProductId() {
        const selected = document.querySelector(
            'input[name="product_id"]:checked',
        );
        selectedProductId = selected ? selected.value : null; // store it
        return selectedProductId;
    }
    async function openUpdateProductModal() {
        const selected = document.querySelector(
            'input[name="product_id"]:checked',
        );

        if (!selected) {
            showToast({
                message: "Please select a product first",
                type: "warning",
            });
            return;
        }

        const productId = selected.value;
        const row = document.querySelector(`tr[data-id="${productId}"]`);
        if (!row) return;

        // Load categories
        let categories = [];
        try {
            const response = await fetch("/categories");
            categories = await response.json(); // e.g., [{id:1, name:'APPETIZER'}, ...]
        } catch (error) {
            console.error("Failed to load categories:", error);
        }

        // Take ID from Modal
        const categorySelect = document.getElementById("prod-category-id");

        categorySelect.innerHTML = ""; // clear previous options

        const currentCategoryId = row.getAttribute("data-category_id") || "";

        // Check if current category exists in the categories list
        const currentCategoryExists = categories.some(
            (cat) => String(cat.id) === currentCategoryId,
        );

        if (currentCategoryExists) {
            // Render all categories with the current one selected
            categories.forEach((cat) => {
                const option = document.createElement("option");
                option.value = cat.id;
                option.textContent = cat.name;
                if (String(cat.id) === currentCategoryId)
                    option.selected = true;
                categorySelect.appendChild(option);
            });
        } else {
            // Current category not found → add placeholder with previous category name
            const placeholder = document.createElement("option");
            placeholder.value = currentCategoryId;
            placeholder.textContent =
                row.getAttribute("data-category_name") || "";
            placeholder.selected = true;
            categorySelect.appendChild(placeholder);

            // Then add all categories normally
            categories.forEach((cat) => {
                const option = document.createElement("option");
                option.value = cat.id;
                option.textContent = cat.name;
                categorySelect.appendChild(option);
            });
        }

        document.getElementById("preview_img").src =
            `/assets/startic_img/${encodeURIComponent(row.dataset.image)}`;

        const sellPrice = parseFloat(row.dataset.sell_price) || 0; // Selling price
        const vat = parseFloat(row.dataset.vat) || 0; // VAT %
        const discount = parseFloat(row.dataset.discount_percent) || 0; // Discount %

        const priceAfterDiscount = sellPrice - (sellPrice * discount) / 100;
        const finalPrice =
            priceAfterDiscount - (priceAfterDiscount * vat) / 100;
        // ID
        document.getElementById("prod-id").value = productId;

        // BASIC
        document.getElementById("prod-code").value = row.dataset.code ?? "";
        document.getElementById("prod-barcode").value =
            row.dataset.bar_code ?? "";
        document.getElementById("prod-name").value = row.dataset.name ?? "";
        document.getElementById("prod-variant").value =
            row.dataset.variant ?? "";
        document.getElementById("prod-description").value =
            row.dataset.description ?? "";

        document.getElementById("sell_price-final").value =
            finalPrice.toFixed(3);

        document.getElementById("prod-unit").value = row.dataset.unit ?? "";

        // STOCK
        document.getElementById("prod-min-stock").value =
            row.dataset.min_stock ?? 0;
        document.getElementById("prod-max-stock").value =
            row.dataset.max_stock ?? 0;

        // PRICE
        document.getElementById("prod-cost").value = row.dataset.cost ?? 0;

        document.getElementById("prod-sell-price").value =
            row.dataset.sell_price ?? 0;
        document.getElementById("prod-vat").value = row.dataset.vat ?? 0;
        document.getElementById("prod-discount").value =
            row.dataset.discount_percent ?? 0;

        // CHECKBOXES / STATUS
        document.getElementById("prod-status").checked =
            row.dataset.status == "true";
        document.getElementById("prod-category-name").value =
            row.dataset.category_name ?? "";

        document.getElementById("prod-track-stock").checked =
            row.dataset.track_stock === "true";

        document.getElementById("prod-allow-discount").checked =
            row.dataset.allow_discount === "true";

        let discountInput = document.getElementById("prod-discount");
        if (row.dataset.allow_discount === "true") {
            discountInput.disabled = false; // enable
        } else {
            discountInput.disabled = true; // disable
        }

        document.getElementById("prod-allow-return").checked =
            row.dataset.allow_return === "true";

        // SHOW MODAL
        document
            .getElementById("confirm-update-product")
            .classList.remove("hidden");
    }

    function closeUpdateProductModal() {
        const modal = document.getElementById("confirm-update-product");
        if (modal) {
            modal.classList.add("hidden");
        }
    }

    function calculateFinalPrice() {
        const priceInput = document.getElementById("prod-sell-price");
        const vatInput = document.getElementById("prod-vat");
        const discountInput = document.getElementById("prod-discount");

        const price = parseFloat(priceInput.value) || 0;
        let vat = parseFloat(vatInput.value) || 0;
        let discount = parseFloat(discountInput.value) || 0;

        // limit VAT to 30%
        if (vat > 30) {
            vat = 30;
            vatInput.value = 30;
        }
        if (vat < 0) {
            vat = 0;
            vatInput.value = 0;
        }

        // limit Discount to 100%
        if (discount > 100) {
            discount = 100;
            discountInput.value = 100;
        }
        if (discount < 0) {
            discount = 0;
            discountInput.value = 0;
        }

        const vatAmount = price * (vat / 100);
        const discountAmount = price * (discount / 100);

        let finalPrice = price + vatAmount - discountAmount;

        // prevent negative sell price
        finalPrice = Math.max(finalPrice, 0);

        document.getElementById("sell_price-final").value =
            finalPrice.toFixed(2);
    }

    // auto recalc on typing
    ["prod-sell-price", "prod-vat", "prod-discount"].forEach((id) => {
        document
            .getElementById(id)
            .addEventListener("input", calculateFinalPrice);
    });

    // Initialize

    const fileInput = document.getElementById("update_image");
    const previewImg = document.getElementById("preview_img");

    fileInput.addEventListener("change", function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            previewImg.src = ""; // Reset if no file selected
        }
    });

    const puchasing_btn = document.getElementById("purchasing");
    // Button doesn't exist when the current user lacks purchasing.view — hide, don't crash.
    if (puchasing_btn) {
        puchasing_btn.addEventListener("click", () => {
            window.location.href = "/Purchasing";
        });
    }
}
async function confirmUpdateProduct() {
    const id = document.getElementById("prod-id").value;

    try {
        // 1️⃣ Create FormData
        const formData = new FormData();
        formData.append("_method", "PUT"); // Laravel fake PUT

        // 2️⃣ Define field mappings
        const fields = [
            "barcode",
            "code",
            "name",
            "variant",
            "description",
            "min_stock",
            "max_stock",
            "cost",
            "sell_price",
            "vat",
            "discount",
            "category_id",
            "category_name",
            "unit",
        ];

        // 3️⃣ Append text/number fields
        fields.forEach((field) => {
            const el = document.getElementById(
                `prod-${field.replace("_", "-")}`,
            );
            if (el) formData.append(field, el.value ?? "");
        });

        // 4️⃣ Append switch/checkbox fields
        const switches = [
            "track_stock",
            "allow_discount",
            "allow_return",
            "status",
        ];

        switches.forEach((field) => {
            const el = document.getElementById(
                `prod-${field.replace("_", "-")}`,
            );
            if (el) formData.append(field, el.checked ? 1 : 0);
        });

        // 5️⃣ Append image if selected
        const imageFile = document.getElementById("update_image")?.files[0];
        if (imageFile) formData.append("image", imageFile);

        // 6️⃣ Debug: log FormData
        for (const pair of formData.entries()) {
        }

        // 7️⃣ Send request
        const res = await fetch(`/product/${id}`, {
            method: "POST", // Must be POST for Laravel FormData + _method=PUT
            headers: {
                "X-CSRF-TOKEN":
                    document.querySelector("input[name=_token]").value,
            },
            body: formData,
        });

        const result = await res.json();

        // 9️⃣ Success handling
        if (result.success) {
            loadProducts(1);
            closeUpdateProductModal();
            showToast({
                message: "Product updated successfully",
                type: "success",
            });
        } else {
            showToast({
                message: result.message || "Update failed",
                type: "error",
            });
        }
    } catch (err) {
        console.error("Server error:", err);
        showToast({
            message: "Server error while updating product",
            type: "error",
        });
    }
}

const logoutBtn = document.getElementById("logout");
const modal_logout = document.getElementById("logoutModal");
const confirmBtn_logout = document.getElementById("confirmLogout");
const cancelBtn_logout = document.getElementById("cancelLogout");

// Show modal
logoutBtn.addEventListener("click", () => {
    modal_logout.classList.remove("hidden");
    document.body.style.overflow = "hidden"; // prevent scroll
});

// Confirm logout
confirmBtn_logout.addEventListener("click", () => {
    window.location.href = "/logout";
});

// Cancel logout
cancelBtn_logout.addEventListener("click", () => {
    modal_logout.classList.add("hidden");
    document.body.style.overflow = ""; // restore scroll
});

// Optional: click outside modal to close
modal_logout.addEventListener("click", (e) => {
    if (e.target === modal_logout) {
        modal_logout.classList.add("hidden");
        document.body.style.overflow = "";
    }
});
let currentCartIndex = null;
let currentProductId = null;
let currentTrackQty = 0;

function openLotModal(cart_index, product_id, product_name) {
    currentCartIndex = cart_index;
    currentProductId = product_id;
    const qtyInput = document.querySelector(`#qty_order_${cart_index}`);

    let maxQty = parseFloat(qtyInput.max);
    // Current value (number)
    currentTrackQty = parseFloat(qtyInput.value) || 0;
    if (currentTrackQty > maxQty) {
        showToast({
            message: `Quantity adjusted to max available (${maxQty})`,
            type: "error",
        });

        currentTrackQty = maxQty;
    }
    document.getElementById("item-id").textContent =
        `Product: ${product_name} (ID: ${product_id}) | Track Qty: ${currentTrackQty}`;

    // Grab original product image from main page
    let img = document.getElementById("product-image" + product_id);
    let display_img = document.getElementById("display_img");

    if (img) {
        display_img.src = img.src; // copy image URL
    } else {
        display_img.src = "assets/defult/placeholder.png"; // fallback
    }

    document.getElementById("lotModal").classList.remove("hidden");

    // Default to auto-pick every time the modal opens — manual per-bin/lot
    // selection is an opt-in extra step, not required.
    const autoPick = document.getElementById("lot-auto-pick");
    autoPick.checked = true;
    const autoQty = document.getElementById("lot-auto-qty");
    autoQty.value = currentTrackQty;
    autoQty.max = currentTrackQty;
    toggleLotAutoPick();

    loadLotData(product_id);
}

function toggleLotAutoPick() {
    const auto = document.getElementById("lot-auto-pick").checked;
    document.getElementById("lot-auto-fill-row").classList.toggle("hidden", !auto);
    document.getElementById("lotModalBody").classList.toggle("hidden", auto);
    updateLotWarning();
}

async function loadLotData(product_id) {
    // Declared outside the try so the catch block below can still reach it
    // to show an error message instead of throwing its own ReferenceError.
    const modalBody = document.getElementById("lotModalBody");
    try {
        modalBody.innerHTML = "<p class='text-gray-500'>Loading lots...</p>";

        const res = await fetch(`/get-lot-data/${product_id}`);
        if (!res.ok) {
            const message = res.status === 403
                ? "You do not have permission to view stock lots."
                : "Failed to load lots.";
            modalBody.innerHTML = `<p class='text-red-500'>${message}</p>`;
            return;
        }
        const data = await res.json();

        if (!data.length) {
            modalBody.innerHTML =
                "<p class='text-red-500'>No stock available</p>";
            return;
        }

        modalBody.innerHTML = ""; // clear previous

        // Table header
        const header = document.createElement("div");
        header.className =
            "grid grid-cols-6 gap-2 font-semibold border-b pb-1 mb-2 text-gray-700";
        header.innerHTML = `
            <div>Lot No</div>
            <div>Qty to pick</div>
            <div>Stock</div>
            <div>Expire</div>
            <div>Warehouse</div>
            <div>Bin</div>
        `;
        modalBody.appendChild(header);

        // Table rows
        data.forEach((lot) => {
            const row = document.createElement("div");
            row.className =
                "grid grid-cols-6 gap-2 items-center p-1 bg-white rounded shadow-sm";

            // Expired check
            let expireClass = "";
            if (lot.expire && new Date(lot.expire) < new Date()) {
                expireClass = "text-red-500 font-bold";
            }

            // Low stock color
            let stockClass = "";
            if (lot.qty <= 5) stockClass = "text-yellow-600 font-semibold";
            let formattedExpire = "-";
            if (lot.expire) {
                const d = new Date(lot.expire);
                const months = [
                    "Jan",
                    "Feb",
                    "Mar",
                    "Apr",
                    "May",
                    "Jun",
                    "Jul",
                    "Aug",
                    "Sep",
                    "Oct",
                    "Nov",
                    "Dec",
                ];
                const day = String(d.getDate()).padStart(2, "0");
                const month = months[d.getMonth()];
                const year = d.getFullYear();
                formattedExpire = `${day}-${month}-${year}`;
            }
            row.innerHTML = `
        <!-- Hidden Warehouse Product ID -->
        <input type="hidden" class="lot-id" value="${lot.id}">

        <input type="text" value="${lot.lot ?? "NO LOT"}" readonly
            class="border px-2 py-1 rounded bg-gray-100 w-full">

        <input type="number"
       min="0"
       max="${lot.quantity}"
       step="0.01"
       value="0"
       class="border px-2 py-1 rounded lot-qty w-full"
       oninput="updateLotWarning()"
       onblur="clampLotQty(this)">

        <span class="${stockClass} text-center">${parseFloat(lot.quantity)}</span>

        <span class="${expireClass} text-center">${formattedExpire}</span>
             <span class="text-center text-nowrap">${lot.warehouse_name}</span>
             <span class="text-center text-nowrap">${lot.bin_name ?? "-"}</span>
    `;

            modalBody.appendChild(row);
        });
    } catch (err) {
        console.error("Error loading lot data:", err);
        modalBody.innerHTML =
            "<p class='text-red-500'>Failed to load lots.</p>";
    }
}
const LOT_QTY_MIN = 0.01;

function clampLotQty(input) {
    let qty = parseFloat(input.value);
    if (Number.isNaN(qty) || qty <= 0) {
        input.value = "0";
        updateLotWarning();
        return;
    }

    const max = parseFloat(input.max) || Infinity;
    qty = Math.round(qty * 100) / 100; // 2 decimals

    if (qty < LOT_QTY_MIN) qty = LOT_QTY_MIN;
    if (qty > max) qty = max;

    input.value = qty;
    updateLotWarning();
}

function saveLots() {
    const autoPick = document.getElementById("lot-auto-pick").checked;

    let lots = [];
    let total = 0;

    if (autoPick) {
        // Empty lots array = let checkout auto-pick bins/lots FEFO-first,
        // same fallback it already uses when no explicit lots are set.
        total = Number((parseFloat(document.getElementById("lot-auto-qty").value || 0)).toFixed(6));
    } else {
        const rows = document.querySelectorAll("#lotModalBody .lot-id");

        rows.forEach((lotInput) => {
            const row = lotInput.closest("div");
            const lotQtyInput = row.querySelector(".lot-qty");

            // ✅ FIX: use parseFloat
            let qty = parseFloat(lotQtyInput.value || 0);

            // normalize
            qty = Number(qty.toFixed(6));

            if (qty > 0 && qty < LOT_QTY_MIN) {
                qty = LOT_QTY_MIN;
                lotQtyInput.value = qty;
            }

            const lotId = lotInput.value;

            if (qty > 0) {
                lots.push({ id: lotId, qty });
                total += qty;
            }
        });
    }

    // ✅ fix floating precision
    const totalRounded = Number(total.toFixed(6));
    const targetRounded = Number(parseFloat(currentTrackQty).toFixed(6));

    const warning = document.getElementById("lot-warning");
    const saveBtn = document.getElementById("save-lot-btn");

    // ✅ tolerance
    const tolerance = 0.000001;

    if (Math.abs(totalRounded - targetRounded) > tolerance) {
        warning.textContent = `Total lot qty (${totalRounded}) must equal item qty (${targetRounded})`;
        warning.classList.remove("hidden");

        saveBtn.disabled = true;
        saveBtn.classList.remove("bg-emerald-500", "hover:bg-emerald-600");
        saveBtn.classList.add("bg-gray-400", "cursor-not-allowed");
        return;
    }

    Livewire.dispatch("set-item-lots", {
        index: currentCartIndex,
        lots: lots,
    });

    warning.classList.add("hidden");
    closeLotModal();

    showToast({ message: "Lots saved successfully!", type: "success" });
}
function updateLotWarning() {
    let total = 0;

    if (document.getElementById("lot-auto-pick")?.checked) {
        total = parseFloat(document.getElementById("lot-auto-qty")?.value || 0);
    } else {
        const inputs = document.querySelectorAll("#lotModalBody .lot-qty");

        inputs.forEach((input) => {
            let val = parseFloat(input.value || 0);

            const maxLot = parseFloat(input.max || 0);

            if (val > maxLot) {
                val = maxLot;
                input.value = val;
            }

            if (val < 0) {
                val = 0;
                input.value = val;
            }

            total += val;
        });
    }

    const totalRounded = Number(total.toFixed(6));
    const targetRounded = Number(parseFloat(currentTrackQty).toFixed(6));

    const warning = document.getElementById("lot-warning");
    const saveBtn = document.getElementById("save-lot-btn");

    const tolerance = 0.000001;

    if (Math.abs(totalRounded - targetRounded) > tolerance) {
        warning.textContent = `Total lot qty (${totalRounded}) must equal item qty (${targetRounded})!`;
        warning.classList.remove("hidden");

        saveBtn.disabled = true;
        saveBtn.classList.remove("bg-emerald-500", "hover:bg-emerald-600");
        saveBtn.classList.add("bg-gray-400", "cursor-not-allowed");
    } else {
        warning.classList.add("hidden");

        saveBtn.disabled = false;
        saveBtn.classList.remove("bg-gray-400", "cursor-not-allowed");
        saveBtn.classList.add("bg-emerald-500", "hover:bg-emerald-600");
    }
}
function closeLotModal() {
    document.getElementById("lotModal").classList.add("hidden");
}
const TRANSFER_MIN_QTY = 0.01;
let transferSource = { warehouseId: null, binId: null };

function openLotModal_transfer(lot_id, data = {}) {
    const modal = document.getElementById("transfer_modal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    // global or scoped variable
    wh_product_id = lot_id;

    const currentWh = (data.warehouse_name ?? "").toString().trim();
    const currentBin = (data.bin_name ?? "").toString().trim();

    transferSource = {
        warehouseId: data.warehouse_id ?? null,
        binId: data.bin_id ?? null,
    };

    document.getElementById("location-display").textContent =
        currentWh + (currentBin ? ` · ${currentBin}` : "");

    // =========================
    // FROM LOCATION TABLE
    // =========================
    document.getElementById("from_location_body").innerHTML = `
        <tr>
            <td class="px-4 py-2.5 font-medium">${data.product_name ?? ""}</td>
            <td class="px-4 py-2.5">${data.lot ?? "—"}</td>
            <td class="px-4 py-2.5 text-right font-bold text-emerald-700">${parseFloat(data.quantity) || 0}</td>
            <td class="px-4 py-2.5">${data.unit ?? ""}</td>
        </tr>
    `;

    // =========================
    // DESTINATION SELECT (any warehouse, incl. the current one — bin-to-bin
    // transfers within the same warehouse are allowed; only same warehouse
    // + same bin is rejected)
    // =========================
    const select = document.getElementById("to_location_select");
    select.innerHTML = `<option value="">Select warehouse</option>`;

    (wh || []).forEach((item) => {
        const option = document.createElement("option");
        option.value = item.id;
        option.textContent =
            String(item.id) === String(transferSource.warehouseId)
                ? `${item.name} (current)`
                : item.name;
        select.appendChild(option);
    });

    document.getElementById("to_location_bin").innerHTML = `<option value="">No Bin</option>`;

    // Only one *other* warehouse to transfer to? Pick it automatically
    // instead of making the user select the sole option.
    const otherWarehouses = (wh || []).filter(
        (item) => String(item.id) !== String(transferSource.warehouseId),
    );
    if (otherWarehouses.length === 1) {
        select.value = String(otherWarehouses[0].id);
        onTransferDestinationWarehouseChange(select.value);
    }

    // =========================
    // QUANTITY INPUT
    // =========================
    const qtyInput = document.getElementById("transfer_qty");

    const availableQty = parseFloat(data.quantity) || 0;

    qtyInput.min = TRANSFER_MIN_QTY;
    qtyInput.max = availableQty;
    qtyInput.step = "0.01";

    qtyInput.value = "";

    // =========================
    // VALIDATE FORM
    // =========================
    validateTransferForm();
}

async function onTransferDestinationWarehouseChange(warehouseId) {
    const binSelect = document.getElementById("to_location_bin");
    const excludeBinId =
        String(warehouseId) === String(transferSource.warehouseId)
            ? transferSource.binId
            : null;

    await populateBinSelect(binSelect, warehouseId, { excludeBinId });
    validateTransferForm();
}

function closeLotModal_transfer() {
    const modal = document.getElementById("transfer_modal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function validateTransferForm() {
    const MIN_QTY = TRANSFER_MIN_QTY;

    const btn = document.getElementById("confirmTransferBtn");
    const qtyInput = document.getElementById("transfer_qty");
    const warehouseSelect = document.getElementById("to_location_select");
    const binSelect = document.getElementById("to_location_bin");
    const qtyError = document.getElementById("transfer_qty_error");

    if (!btn || !qtyInput || !warehouseSelect) return;

    const min = MIN_QTY;
    const max = qtyInput.max ? Number(qtyInput.max) : Infinity;
    const qtyValue = qtyInput.value.trim();

    let valid = false;

    if (qtyValue === "") {
        if (qtyError) {
            qtyError.textContent = "Please enter quantity";
            qtyError.classList.remove("hidden");
        }
    } else {
        const qty = Number(qtyValue);

        if (Number.isNaN(qty) || qty < min || qty > max) {
            if (qtyError) {
                qtyError.textContent =
                    max === Infinity
                        ? `Qty must be at least ${formatQty(min)}`
                        : `Qty must be between ${formatQty(min)} and ${formatQty(max)}`;

                qtyError.classList.remove("hidden");
            }
        } else if (
            warehouseSelect.value !== "" &&
            String(warehouseSelect.value) === String(transferSource.warehouseId) &&
            String(binSelect?.value || "") === String(transferSource.binId || "")
        ) {
            if (qtyError) {
                qtyError.textContent = "Destination must be a different warehouse or bin than the source";
                qtyError.classList.remove("hidden");
            }
        } else {
            if (qtyError) {
                qtyError.textContent = "";
                qtyError.classList.add("hidden");
            }

            if (warehouseSelect.value !== "") {
                valid = true;
            }
        }
    }

    if (valid) {
        btn.disabled = false;
        btn.classList.remove("bg-gray-400", "cursor-not-allowed");
        btn.classList.add(
            "bg-green-500",
            "hover:bg-green-600",
            "cursor-pointer",
        );
    } else {
        btn.disabled = true;
        btn.classList.remove(
            "bg-green-500",
            "hover:bg-green-600",
            "cursor-pointer",
        );
        btn.classList.add("bg-gray-400", "cursor-not-allowed");
    }
}
function clampTransferQty() {
    const qtyInput = document.getElementById("transfer_qty");
    if (!qtyInput || qtyInput.value.trim() === "") return;

    let qty = Number(qtyInput.value);
    if (Number.isNaN(qty)) { qtyInput.value = ""; validateTransferForm(); return; }

    qty = Math.round(qty * 100) / 100;           // force 2 decimals (0.01 step)

    const MIN_QTY = TRANSFER_MIN_QTY;
    const max = qtyInput.max ? Number(qtyInput.max) : Infinity;

    if (qty < MIN_QTY) qty = MIN_QTY;            // floor
    if (qty > max) qty = max;                // respect stock cap

    qtyInput.value = qty;
    validateTransferForm();
}
function formatQty(value) {
    return Number(value ?? 0)
        .toFixed(6)
        .replace(/\.?0+$/, "");
}
let wh_product_id = 0;

async function submitTransfer() {
    const warehouse_id = document.getElementById("to_location_select").value;
    const bin_id = document.getElementById("to_location_bin")?.value || null;
    const qty = document.getElementById("transfer_qty").value;

    const res = await fetch("/transfer-lot", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                .value,
            Accept: "application/json",
            "Content-Type": "application/json", // 🔥 must have
        },
        body: JSON.stringify({
            wh_product_id: wh_product_id,
            warehouse_id: warehouse_id,
            bin_id: bin_id,
            quantity: qty,
        }),
    });

    const data = await res.json();

    if (res.ok) {
        // SUCCESS
        showToast({
            message: data.message || "Transfer completed",
            type: "success",
        });

        let select_wh = document.getElementById("warehouseTypeSelect");

        const warehouseId = select_wh.value;
        if (warehouseId === "All") {
            loadWarehouseStock(0, 1); // or handle All case
        } else {
            loadWarehouseStock(warehouseId, 1);
        }

        const detailModal = document.getElementById("stockDetailModal");
        if (detailModal && !detailModal.classList.contains("hidden") && currentStockDetailProductId) {
            openStockDetailModal(currentStockDetailProductId, document.getElementById("stockDetailProductName")?.textContent || "");
        }

        document.getElementById("transfer_modal").classList.add("hidden");
    } else {
        // ERROR
        showToast({
            message: data.message || "Transfer failed",
            type: "error",
        });
    }
}

// ------------------------
// Quick Transfer (FEFO) — transfer a product without opening its lot
// detail first; the server pulls from the oldest-expiring lots in the
// chosen source warehouse until the requested qty is satisfied.
// ------------------------
let fefoWarehouseTotals = {}; // { [warehouse_id]: { name, total } }

async function openFefoTransferModal(productId, productName) {
    document.getElementById("fefo_transfer_product_id").value = productId;
    document.getElementById("fefo_transfer_product_name").textContent = productName || "";

    const fromSelect = document.getElementById("fefo_from_warehouse");
    fromSelect.innerHTML = `<option value="">Loading...</option>`;

    const modal = document.getElementById("fefoTransferModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    try {
        const res = await fetch(`/get-lot-data/${productId}`);
        const lots = await res.json();

        fefoWarehouseTotals = {};
        (lots || []).forEach((lot) => {
            const whId = lot.warehouse_id;
            if (whId == null) return;
            if (!fefoWarehouseTotals[whId]) {
                fefoWarehouseTotals[whId] = { name: lot.warehouse_name || "", total: 0 };
            }
            fefoWarehouseTotals[whId].total += parseFloat(lot.quantity) || 0;
        });

        const warehouseIds = Object.keys(fefoWarehouseTotals).filter(
            (id) => fefoWarehouseTotals[id].total > 0,
        );

        if (!warehouseIds.length) {
            fromSelect.innerHTML = `<option value="">No stock available</option>`;
            return;
        }

        fromSelect.innerHTML = warehouseIds
            .map((id) => `<option value="${id}">${fefoWarehouseTotals[id].name}</option>`)
            .join("");

        fromSelect.value = warehouseIds[0]; // only-one-option case auto-selects itself
        onFefoSourceWarehouseChange();
    } catch (err) {
        console.error(err);
        fromSelect.innerHTML = `<option value="">Failed to load stock</option>`;
    }
}

function onFefoSourceWarehouseChange() {
    const fromId = document.getElementById("fefo_from_warehouse").value;
    const available = fefoWarehouseTotals[fromId]?.total || 0;

    document.getElementById("fefo_from_available").textContent = formatQty(available);

    const qtyInput = document.getElementById("fefo_transfer_qty");
    qtyInput.max = available;

    const toSelect = document.getElementById("fefo_to_warehouse");
    toSelect.innerHTML = `<option value="">Select warehouse</option>`;

    const otherWarehouses = (wh || []).filter(
        (item) => String(item.id) !== String(fromId),
    );

    otherWarehouses.forEach((item) => {
        const option = document.createElement("option");
        option.value = item.id;
        option.textContent = item.name;
        toSelect.appendChild(option);
    });

    // Only one other warehouse to transfer to? Pick it automatically.
    if (otherWarehouses.length === 1) {
        toSelect.value = String(otherWarehouses[0].id);
    }

    onFefoDestinationWarehouseChange();
}

async function onFefoDestinationWarehouseChange() {
    const toId = document.getElementById("fefo_to_warehouse").value;
    const binSelect = document.getElementById("fefo_to_bin");

    await populateBinSelect(binSelect, toId);
    validateFefoTransferForm();
}

function validateFefoTransferForm() {
    const btn = document.getElementById("confirmFefoTransferBtn");
    const qtyInput = document.getElementById("fefo_transfer_qty");
    const fromSelect = document.getElementById("fefo_from_warehouse");
    const toSelect = document.getElementById("fefo_to_warehouse");
    const qtyError = document.getElementById("fefo_transfer_qty_error");

    if (!btn || !qtyInput || !fromSelect || !toSelect) return;

    const min = TRANSFER_MIN_QTY;
    const max = qtyInput.max ? Number(qtyInput.max) : Infinity;
    const qtyValue = qtyInput.value.trim();

    let valid = false;

    if (qtyValue === "") {
        if (qtyError) {
            qtyError.textContent = "Please enter quantity";
            qtyError.classList.remove("hidden");
        }
    } else {
        const qty = Number(qtyValue);

        if (Number.isNaN(qty) || qty < min || qty > max) {
            if (qtyError) {
                qtyError.textContent = `Qty must be between ${formatQty(min)} and ${formatQty(max)}`;
                qtyError.classList.remove("hidden");
            }
        } else if (!toSelect.value) {
            if (qtyError) {
                qtyError.textContent = "Please select a destination warehouse";
                qtyError.classList.remove("hidden");
            }
        } else if (String(fromSelect.value) === String(toSelect.value)) {
            if (qtyError) {
                qtyError.textContent = "Destination must be a different warehouse than the source";
                qtyError.classList.remove("hidden");
            }
        } else {
            if (qtyError) {
                qtyError.textContent = "";
                qtyError.classList.add("hidden");
            }
            valid = true;
        }
    }

    if (valid) {
        btn.disabled = false;
        btn.classList.remove("bg-gray-400", "cursor-not-allowed");
        btn.classList.add("bg-green-500", "hover:bg-green-600", "cursor-pointer");
    } else {
        btn.disabled = true;
        btn.classList.remove("bg-green-500", "hover:bg-green-600", "cursor-pointer");
        btn.classList.add("bg-gray-400", "cursor-not-allowed");
    }
}

function closeFefoTransferModal() {
    const modal = document.getElementById("fefoTransferModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function submitFefoTransfer() {
    const product_id = document.getElementById("fefo_transfer_product_id").value;
    const from_warehouse_id = document.getElementById("fefo_from_warehouse").value;
    const warehouse_id = document.getElementById("fefo_to_warehouse").value;
    const bin_id = document.getElementById("fefo_to_bin")?.value || null;
    const quantity = document.getElementById("fefo_transfer_qty").value;

    try {
        const response = await fetch("/transfer-fefo", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                product_id,
                from_warehouse_id,
                warehouse_id,
                bin_id,
                quantity,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            showToast({ message: data.message || "Transfer completed", type: "success" });
            closeFefoTransferModal();

            const select_wh = document.getElementById("warehouseTypeSelect");
            const warehouseId = select_wh?.value;
            if (warehouseId === "All" || !warehouseId) {
                loadWarehouseStock(0, 1);
            } else {
                loadWarehouseStock(warehouseId, 1);
            }

            const detailModal = document.getElementById("stockDetailModal");
            if (detailModal && !detailModal.classList.contains("hidden") && currentStockDetailProductId) {
                openStockDetailModal(currentStockDetailProductId, document.getElementById("stockDetailProductName")?.textContent || "");
            }
        } else {
            showToast({ message: data.message || "Transfer failed", type: "error" });
        }
    } catch (err) {
        console.error(err);
        showToast({ message: "Error submitting transfer", type: "error" });
    }
}

function formatDate(dateStr) {
    if (!dateStr) return "";
    return new Date(dateStr).toLocaleDateString("en-GB");
}

function formatDateTime(dateStr) {
    if (!dateStr) return "";
    return new Date(dateStr).toLocaleString("en-GB");
}

[
    "filter_global",
    "filter_lot",
    "filter_warehouse",
    "filter_doc_type",
    "filter_date_from",
    "filter_date_to",
].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;

    el.addEventListener("input", debounce(loadItemLedgerEntries, 400));
    el.addEventListener("change", loadItemLedgerEntries);
});

let itemLedgerCurrentPage = 1;
let itemLedgerPerPage = 50;

async function loadItemLedgerEntries(page = 1) {
    const tbody = document.getElementById("item_ledger_entry_table_body");
    itemLedgerCurrentPage = page;

    tbody.innerHTML = `
        <tr>
            <td colspan="38" class="px-3 py-4 text-center text-gray-500">Loading...</td>
        </tr>
    `;

    try {
        const params = new URLSearchParams({
            page: itemLedgerCurrentPage,
            per_page: itemLedgerPerPage,
            search: document.getElementById("filter_global")?.value || "",
            lot: document.getElementById("filter_lot")?.value || "",
            warehouse: document.getElementById("filter_warehouse")?.value || "",
            type: document.getElementById("filter_doc_type")?.value || "",
            from: document.getElementById("filter_date_from")?.value || "",
            to: document.getElementById("filter_date_to")?.value || "",
        });

        const response = await fetch(`/item-ledger-entry?${params.toString()}`);

        if (!response.ok) throw new Error("Failed to load item ledger entry");

        const result = await response.json();
        renderItemLedgerEntries(result.data || []);
        renderItemLedgerPagination(result);
    } catch (error) {
        console.error(error);

        tbody.innerHTML = `
            <tr>
                <td colspan="38" class="px-3 py-4 text-center text-red-500">
                    Failed to load item ledger entry
                </td>
            </tr>
        `;
    }
}
function renderItemLedgerEntries(rows) {
    const tbody = document.getElementById("item_ledger_entry_table_body");

    if (!rows.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="38" class="px-3 py-4 text-center text-gray-500">
                    No item ledger entry found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = rows
        .map(
            (row) => `
        <tr class="hover:bg-emerald-50/60 text-nowrap transition" onclick="hightlightRow('Table-item-ledger-entry', this)">
            <td class="px-2 py-1">${row.entry_no ?? ""}</td>
            <td class="px-2 py-1 text-center">${formatDate(row.posting_date)}</td>
            <td class="px-2 py-1">${row.document_type ?? ""}</td>
            <td class="px-2 py-1">${row.document_no ?? ""}</td>
            <td class="px-2 py-1">${row.source_no ?? ""}</td>
            <td class="px-2 py-1">${row.barcode ?? ""}</td>
            <td class="px-2 py-1">${row.item_code ?? ""}</td>
            <td class="px-2 py-1">${row.name ?? ""}</td>
            <td class="px-2 py-1">${row.variant ?? ""}</td>
            <td class="px-2 py-1">${row.description ?? ""}</td>
            <td class="px-2 py-1">${row.unit ?? ""}</td>
            <td class="px-2 py-1">${row.category_name ?? ""}</td>
            <td class="px-2 py-1">${row.warehouse_name ?? ""}</td>
            <td class="px-2 py-1">${row.lot ?? ""}</td>
            <td class="px-2 py-1 text-center">${formatDate(row.expire_date)}</td>

            <td class="px-2 py-1 text-right">${formatNumber(row.quantity)}</td>
            <td class="px-2 py-1 text-right">${formatNumber(row.remaining_quantity)}</td>
            <td class="px-2 py-1">${row.entry_type ?? ""}</td>

            <td class="px-2 py-1 text-right">${formatNumber(row.unit_cost)} $</td>
            <td class="px-2 py-1 text-right font-semibold">${formatNumber(row.cost_amount)} $</td>
            <td class="px-2 py-1 text-right">${formatNumber(row.unit_price)} $</td>
            <td class="px-2 py-1 text-right">${formatNumber(row.sell_price)} $</td>

            <td class="px-2 py-1 text-right">${formatNumber(row.discount_percent)} %</td>
            <td class="px-2 py-1 text-right">${formatNumber(row.discount_amount)} $</td>

            <td class="px-2 py-1 text-right">${formatNumber(row.vat)} %</td>
            <td class="px-2 py-1 text-right">${formatNumber(row.vat_amount)} $</td>

            <td class="px-2 py-1 text-right">${formatNumber(row.line_amount)} $</td>
            <td class="px-2 py-1 text-right">${formatNumber(row.net_amount)} $</td>
            <td class="px-2 py-1 text-right">${formatNumber(row.grand_total_amount)} $</td>

            <td class="px-2 py-1">${row.customer_id ?? ""}</td>
            <td class="px-2 py-1">${row.customer_name ?? ""}</td>
            <td class="px-2 py-1">${row.customer_phone ?? ""}</td>
            <td class="px-2 py-1">${row.customer_address ?? ""}</td>

            <td class="px-2 py-1">${row.vendor_id ?? ""}</td>
            <td class="px-2 py-1">${row.vendor_name ?? ""}</td>
            <td class="px-2 py-1">${row.payment_method ?? ""}</td>

            <td class="px-2 py-1">${row.created_by ?? ""}</td>
            <td class="px-2 py-1 text-center">${formatDateTime(row.created_at)}</td>
        </tr>
    `,
        )
        .join("");
}

function renderItemLedgerPagination(result) {
    const container = document.getElementById(
        "paginationContainer_item_ledger_entry",
    );
    const pageInfo = document.getElementById("pageInfo_item_ledger_entry");

    if (!container || !pageInfo) return;

    const currentPage = result.current_page || 1;
    const lastPage = result.last_page || 1;
    const total = result.total || 0;
    const from = result.from || 0;
    const to = result.to || 0;

    pageInfo.textContent = `Showing ${from} to ${to} of ${total} entries`;

    if (lastPage <= 1) {
        container.innerHTML = "";
        return;
    }

    let html = "";

    html += `
        <button type="button"
            onclick="loadItemLedgerEntries(${currentPage - 1})"
            ${currentPage <= 1 ? "disabled" : ""}
            class="page-btn">
            Prev
        </button>
    `;

    for (let i = 1; i <= lastPage; i++) {
        if (i === 1 || i === lastPage || Math.abs(i - currentPage) <= 2) {
            html += `
                <button type="button"
                    onclick="loadItemLedgerEntries(${i})"
                    class="page-btn ${i === currentPage ? "page-btn-active" : ""}">
                    ${i}
                </button>
            `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<span class="px-2 text-gray-400">...</span>`;
        }
    }

    html += `
        <button type="button"
            onclick="loadItemLedgerEntries(${currentPage + 1})"
            ${currentPage >= lastPage ? "disabled" : ""}
            class="page-btn">
            Next
        </button>
    `;

    container.innerHTML = html;
}
function debounce(fn, delay = 400) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}

function initItemLedgerFilters() {
    const ids = [
        "filter_global",
        "filter_lot",
        "filter_warehouse",
        "filter_doc_type",
        "filter_date_from",
        "filter_date_to",
    ];

    ids.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;

        el.addEventListener(
            "input",
            debounce(() => loadItemLedgerEntries(1), 400),
        );
        el.addEventListener("change", () => loadItemLedgerEntries(1));
    });
}

function clearItemLedgerFilters() {
    [
        "filter_global",
        "filter_lot",
        "filter_warehouse",
        "filter_doc_type",
        "filter_date_from",
        "filter_date_to",
    ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });

    loadItemLedgerEntries(1);
}

function formatNumber(value) {
    const num = Number(value ?? 0);

    // round to 2 decimals for display
    let display = num.toFixed(2);

    // remove useless zeros
    display = display.replace(/\.?0+$/, "");

    return display;
}

window.addEventListener("payment-success", (e) => {
    const message = e.detail[0].message;


    showToast({
        message: message,
        type: "success",
    });

    Livewire.dispatch("clearAll_after_payment");

    reloadProducts();
});
window.addEventListener("deposit-success", (e) => {
    const message = e.detail[0].message;

    // If the completed sale came from a loaded quotation, clear the
    // quotation modal's stale edit-mode id so the next "Quote" click
    // starts a fresh quotation instead of overwriting the completed one.
    const qId = document.getElementById("quotation_id");
    if (qId) qId.value = "";

    showToast({
        message: message,
        type: "success",
    });
    Livewire.dispatch("clearAll_after_payment");

    reloadProducts();
    refreshSaleOrderListIfOpen();
});
window.addEventListener("expense-success", (e) => {
    const message = e.detail[0].message;

    showToast({
        message: message,
        type: "success",
    });
});
let sale_order_header_for_print = null;
window.addEventListener("pass_sale_header", async (event) => {
    const header = event.detail.header ?? event.detail[0]?.header;
    const posInfo = event.detail.posInfo ?? event.detail[0]?.posInfo;


    sale_order_header_for_print = header;

    const options = await askPrintOptions();

    if (options?.receipt.checked) {
        for (let i = 0; i < options.receipt.qty; i++) {
            await print_document_v2("Invoice", sale_order_header_for_print, posInfo);
        }
    }

    // Invoice / Delivery Note / Picking List all share the same A4 printer —
    // every checked copy is combined into ONE multi-page document and sent
    // to the browser print dialog a single time, instead of opening a
    // separate print window per copy (which browsers silently blocked past
    // the first one, since only one popup per user gesture is allowed).
    const a4Pages = [];
    let a4DocNo = header.document_no;

    if (options?.invoice.checked || options?.delivery.checked || options?.pickingList.checked) {
        try {
            const data = await fetchSaleOrderForPrint(header.id);

            if (options.invoice.checked) {
                for (let i = 0; i < options.invoice.qty; i++) {
                    a4Pages.push(buildInvoiceHtml(data.header, data.lines, posInfo));
                }
            }

            if (options.delivery.checked) {
                for (let i = 0; i < options.delivery.qty; i++) {
                    a4Pages.push(buildDeliveryNoteHtml(data.header, data.lines, posInfo));
                }
            }

            if (options.pickingList.checked) {
                const res = await fetch(`/picking-list-data/${header.id}`);
                if (!res.ok) throw new Error(res.status);
                const pickingData = await res.json();
                a4Pages.push(buildPickingListHtml(pickingData.header, pickingData.rows, posInfo));
            }
        } catch (err) {
            console.error(err);
            showToast({ message: "Failed to print invoice/delivery note/picking list", type: "error" });
        }
    }

    if (a4Pages.length > 0) {
        // The delivery note and picking list are styled by the .a4-* rules in
        // style_a4_document, but the Khmer invoice uses its own markup
        // (.header-center / .line / .info-section / .total_print) which only
        // style_invoice_khmer defines. Sending style_a4_document alone printed
        // the invoice with no styling at all — no rules, no borders, no
        // two-column header. A combined document needs both sheets; the Khmer
        // one is only added when an invoice is actually in the batch so its
        // bare table/th/td rules don't reach a delivery-note-only print.
        const a4Styles = options?.invoice.checked
            ? style_a4_document + style_invoice_khmer
            : style_a4_document;

        await printA4(a4Styles + a4Pages.join(""), a4DocNo);
    }

    reloadProducts();
});
window.addEventListener("ordered", (e) => {
    // Livewire may pass [{...}] or {...} — handle both
    const detail = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    const message = detail?.message ?? "Success";

    showToast({
        message: message,
        type: "success",
    });

    // 🎉 tell customer display to show Thank You
    localStorage.setItem("pos_display_event", JSON.stringify({
        type: "thank_you",
        doc_no: detail?.doc_no ?? "",
        at: Date.now()          // makes value unique so storage event always fires
    }));

    Livewire.dispatch("clearCart_no_message");
    document.querySelector("#customerValue").value = "";
    document.querySelector("#customerSearch").value = "";

    const deliverySection = document.getElementById("delivery_section");
    deliverySection.classList.add("hidden");

    document.getElementById("so_customer_type").value = "Take-Away";
    document.getElementById("so_delivery_dateInput").value = "";
    document.getElementById("so_delivery_status").value = "N/A";
    document.getElementById("so_delivery_info_status").value = "";
    document.getElementById("so_driver_name").value = "";
    document.getElementById("so_driver_phone").value = "";
    document.getElementById("so_customer_id_info").value = "";
    document.getElementById("so_customer_name_info").value = "";
    document.getElementById("so_customer_phone_info").value = "";
    document.getElementById("so_customer_address_info").value = "";
    document.getElementById("so_remark_invoice").value = "";

    refreshSaleOrderListIfOpen();
});

// Reload the Sale Order List table in place, but only if it's actually
// open — never force it open just because a payment/order happened
// elsewhere (e.g. a normal checkout from the cart).
function refreshSaleOrderListIfOpen() {
    const modal = document.getElementById("default-modal-sales-order-list");
    if (modal && !modal.classList.contains("hidden")) {
        loadSaleOrders(1);
    }
}

window.addEventListener("view-cart-lots", (event) => {
    const { lots, product_name, product_id } = event.detail[0]; // <-- remove [0]

    // Grab original product image from main page
    const img = document.getElementById("product-image" + product_id); // optional, only if you have image IDs
    const display_img = document.getElementById("display_img2");

    if (img) {
        display_img.src = img.src; // copy image URL
    } else {
        display_img.src = "https://via.placeholder.com/150"; // fallback
    }

    const modalBody = document.getElementById("viewLotModalBody");
    const modalTitle = document.getElementById("view-lot-title");
    modalBody.innerHTML = "";

    modalTitle.textContent = `Tracked Lots for: ${product_name}`;

    if (!lots.length) {
        modalBody.innerHTML =
            "<p class='text-gray-500'>No lots tracked yet.</p>";
    } else {
        // Header
        const header = document.createElement("div");
        header.className =
            "grid grid-cols-6 gap-2 font-semibold border-b pb-1 text-gray-700";
        header.innerHTML = `

         <div>Warehouse</div>
            <div>Bin</div>
            <div>Lot No</div>
            <div>Qty</div>
            <div>Stock</div>
            <div>Expire</div>
        `;
        modalBody.appendChild(header);

        // Rows
        lots.forEach((lot) => {
            const row = document.createElement("div");
            row.className =
                "grid grid-cols-6 gap-2 items-center p-1 bg-gray-50 rounded";

            let expireClass = "";
            if (lot.expire && new Date(lot.expire) < new Date()) {
                expireClass = "text-red-500 font-bold";
            }

            row.innerHTML = `
                <span class="text-left px-2">${lot.warehouse}</span>
                <span class="text-left px-2">${lot.bin ?? "-"}</span>
                <span class="text-left px-2">${lot.lot}</span>
                <span class="text-center px-2">${lot.qty}</span>
                <span class="text-center px-2">${lot.stock}</span>
                <span class="text-center px-2 text-nowrap ${expireClass}">${lot.expire}</span>
            `;
            modalBody.appendChild(row);
        });
    }

    // Show modal
    document.getElementById("viewLotModal").classList.remove("hidden");
});

window.addEventListener("trigger-print", (e) => {
    // print_document("Receipt");
    Livewire.dispatch("clearCart_no_message");
});

window.addEventListener("clear-customer", (e) => {
    document.querySelector("#customerValue").value = "";
    document.querySelector("#customerSearch").value = "";
});

window.addEventListener("update-customer-input", (e) => {
    document.querySelector("#customerValue").value = e.detail[0].code;
    document.querySelector("#customerSearch").value = e.detail[0].display;
});

window.addEventListener("cart-cleared", (e) => {
    current_discount = 0;
    messsage = e.detail[0].message;
    showToast({
        message: messsage,
        type: "success",
    });

    document.querySelector("#customerValue").value = "";
    document.querySelector("#customerSearch").value = "";

    const deliverySection = document.getElementById("delivery_section");

    deliverySection.classList.add("hidden");

    document.getElementById("so_customer_type").value = "Take-Away";
    document.getElementById("so_delivery_dateInput").value = "";
    document.getElementById("so_delivery_status").value = "N/A";
    document.getElementById("so_delivery_info_status").value = "";
    document.getElementById("so_driver_name").value = "";
    document.getElementById("so_driver_phone").value = "";
    document.getElementById("so_customer_id_info").value = "";
    document.getElementById("so_customer_name_info").value = "";
    document.getElementById("so_customer_phone_info").value = "";
    document.getElementById("so_customer_address_info").value = "";
    document.getElementById("so_remark_invoice").value = "";
});

window.addEventListener("payment-error", (e) => {
    const message = e.detail[0].message;

    showToast({
        message: message,
        type: "error",
    });

    // Ask before printing
});

window.addEventListener("discount-not-allowed", (e) => {
    showToast({
        message: e.detail[0].message,
        type: "error",
    });
});

window.addEventListener("app-error", (e) => {
    const message = e.detail[0].message;

    showToast({
        message: message,
        type: "error",
    });
});

window.addEventListener("no-stock", (e) => {
    const message = e.detail[0].message;

    showToast({
        message: message,
        type: "error",
    });
});

document.getElementById("sale_order_status").addEventListener("change", () => {
    loadSaleOrders(1);
});
function openSaleOrderModal() {
    const modal = document.getElementById("default-modal-sales-order-list");
    if (!modal) return;

    modal.classList.remove("hidden");

    loadSaleOrders(1);
}

function closeSaleOrderModal() {
    const modal = document.getElementById("default-modal-sales-order-list");
    if (!modal) return;

    modal.classList.add("hidden");
}

[
    "sale_order_status",
    "sale_order_payment_status",
    "sale_order_delivery_status",
    "so_from_posting_dateInput",
    "so_to_posting_dateInput",
].forEach((id) => {
    document.getElementById(id)?.addEventListener("change", () => {
        loadSaleOrders(1);
    });
});

["sale_document_search", "sale_order_search"].forEach((id) => {
    document.getElementById(id)?.addEventListener("input", () => {
        loadSaleOrders(1);
    });
});
// Collects the sale-order filter bar into the same params loadSaleOrders() sends,
// so "Mark All Delivered" acts on exactly the rows currently listed.
function currentSaleOrderFilterParams() {
    const params = new URLSearchParams({
        status: document.getElementById("sale_order_status")?.value || "",
        payment_status:
            document.getElementById("sale_order_payment_status")?.value || "",
        delivery_status:
            document.getElementById("sale_order_delivery_status")?.value || "",
        from_posting_date:
            document.getElementById("so_from_posting_dateInput")?.value || "",
        to_posting_date:
            document.getElementById("so_to_posting_dateInput")?.value || "",
        search: document.getElementById("sale_order_search")?.value || "",
        search_document:
            document.getElementById("sale_document_search")?.value || "",
        // the Seller filter isn't rendered for cashiers
        user_id:
            user_role != "cashier"
                ? document.getElementById("sale_order_user_id")?.value || ""
                : "",
    });
    return params;
}

async function markAllDelivered() {
    const btn = document.getElementById("markAllDeliveredBtn");
    const params = currentSaleOrderFilterParams();

    const anyFilter = [...params.values()].some((v) => v !== "");
    if (
        !confirm(
            anyFilter
                ? "Mark every order in the current filtered list as Delivered?"
                : "No filters are set — this will mark EVERY order you can see as Delivered. Continue?",
        )
    ) {
        return;
    }

    if (btn) btn.disabled = true;

    try {
        const res = await fetch(`/sale-order/mark-all-delivered?${params.toString()}`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN":
                    document.querySelector('input[name="_token"]')?.value ||
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content"),
                Accept: "application/json",
            },
        });

        if (res.status === 403) {
            showToast({ message: "You don't have permission to do that", type: "error" });
            return;
        }

        const data = await res.json();
        showToast({
            message: data.message || "Done",
            type: data.affected > 0 ? "success" : "info",
        });

        if (data.affected > 0) loadSaleOrders(1);
    } catch (e) {
        console.error(e);
        showToast({ message: "Failed to update deliveries", type: "error" });
    } finally {
        if (btn) btn.disabled = false;
    }
}

/**
 * Settle every unpaid order in the current filtered view.
 *
 * Mirrors markAllDelivered: the server re-applies the same visibility scope and
 * the same filters, so this can only ever touch rows the user is already
 * looking at. Payment fields only — nothing ships.
 */
async function markAllPaid() {
    const btn = document.getElementById("markAllPaidBtn");
    const params = currentSaleOrderFilterParams();

    const anyFilter = [...params.values()].some((v) => v !== "");
    if (
        !confirm(
            anyFilter
                ? "Mark every unpaid order in the current filtered list as Paid?\n\nThis records them as fully paid. It does not ship anything."
                : "No filters are set — this will mark EVERY unpaid order you can see as Paid.\n\nThis records them as fully paid and cannot be undone in bulk. Continue?",
        )
    ) {
        return;
    }

    if (btn) btn.disabled = true;

    try {
        const res = await fetch(`/sale-order/mark-all-paid?${params.toString()}`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN":
                    document.querySelector('input[name="_token"]')?.value ||
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content"),
                Accept: "application/json",
            },
        });

        if (res.status === 403) {
            showToast({ message: "You don't have permission to do that", type: "error" });
            return;
        }

        const data = await res.json();
        showToast({
            message: data.message || "Done",
            type: data.affected > 0 ? "success" : "info",
        });

        if (data.affected > 0) loadSaleOrders(1);
    } catch (e) {
        console.error(e);
        showToast({ message: "Failed to mark orders paid", type: "error" });
    } finally {
        if (btn) btn.disabled = false;
    }
}

function loadSaleOrders(page = 1) {
    console.log("sale loaded");
    const status = document.getElementById("sale_order_status")?.value || "";
    const paymentStatus =
        document.getElementById("sale_order_payment_status")?.value || "";
    const deliveryStatus =
        document.getElementById("sale_order_delivery_status")?.value || "";
    const fromPostingDate =
        document.getElementById("so_from_posting_dateInput")?.value || "";
    const toPostingDate =
        document.getElementById("so_to_posting_dateInput")?.value || "";
    const search = document.getElementById("sale_order_search")?.value || "";
    const search_document =
        document.getElementById("sale_document_search")?.value || "";
    const modal = document.getElementById("default-modal-sales-order-list");
    let user_created_by_id = "";
    if (user_role != "cashier") {
        user_created_by_id =
            document.getElementById("sale_order_user_id")?.value || "";
    }

    if (!modal) {
        console.error("Modal not found: default-modal-sales-order-list");
        return;
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
    const params = new URLSearchParams({
        page: page,
        status: status,
        payment_status: paymentStatus,
        delivery_status: deliveryStatus,
        from_posting_date: fromPostingDate,
        to_posting_date: toPostingDate,
        search: search,
        search_document: search_document,
        user_id: user_created_by_id,
    });

    fetch(`/get-sale-orders?${params.toString()}`)
        .then((res) => res.json())
        .then((res) => {
            let tbody = document.getElementById("Table-sale-order-list");
            tbody.innerHTML = "";

            if (!res.data || res.data.length === 0) {
                tbody.innerHTML = `
        <tr>
            <td colspan="21" class="text-center py-6 text-gray-500">
                No Sale Orders Found 😢
            </td>
        </tr>
    `;

                document.getElementById(
                    "paginationContainer_sale_order",
                ).innerHTML = "";
                document.getElementById("pageInfo_sale_order").textContent = "";

                return;
            }

            saleOrderRowsById = {};

            res.data.forEach((row, index) => {
                // Amounts are stored in base USD. In English the list shows USD;
                // in Khmer it shows Riel converted with THIS row's own saved
                // factor, so each order keeps the rate it was sold at.
                const money = (value) => listMoney(value, row.factor);

                saleOrderRowsById[row.id] = row;

                tbody.innerHTML += `
                <tr onclick="selectSaleOrderRow(this, ${row.id})"
                        ondblclick="viewSaleOrderLine(${row.id})"
                        oncontextmenu="showSaleOrderRowMenu(event, this, ${row.id})"
                        class="cursor-pointer hover:bg-gray-50 text-nowrap">

                        <td class="px-4 py-2">
                            <input type="checkbox" class="sale-order-checkbox pointer-events-none">
                        </td>

                        <td class="px-4 py-3">${index + 1}</td>
                        <td class="px-4 py-3">${row.document_no ?? ""}</td>
                        <td class="px-4 py-3 text-center">${row.posting_date ?? ""}</td>
                        <td class="px-4 py-3 text-center">${row.order_date ?? ""}</td>





                   <td class="px-4 py-3 text-right">${money(row.total_amount)}</td>
                    <td class="px-4 py-3 text-right">${money(row.vat_amount)}</td>
                    <td class="px-4 py-3 text-right">${money(row.discount_amount)}</td>
                    <td class="px-4 py-3 text-right">${money(row.grand_total)}</td>
                    <td class="px-4 py-3 text-right">${money(row.paid_amount)}</td>

                    <td class="px-4 py-3 text-right ${Number(row.balance_amount ?? 0) > 0
                        ? "text-red-500"
                        : "text-green-500"
                    }">
                        ${money(row.balance_amount)}
                    </td>




                        <td class="px-4 py-3 text-right text-nowrap">${getStatusBadge(row.status)}</td>
                        <td class="px-4 py-3 text-right text-nowrap hidden">${getPaymentBadge(row.payment_status)}</td>
                            <td class="px-4 py-3 text-center text-nowrap">
                    ${getDeliverySelect(row.delivery_status, row.id)}
                    </td>
                                     <td class="px-4 py-3">${row.contact_name ?? ""}</td>
                        <td class="px-4 py-3">${row.phone ?? ""}</td>
                        <td class="px-4 py-3">${row.address ?? ""}</td>
                             <td class="px-4 py-3">${row.remarks ?? ""}</td>
                                  <td class="px-4 py-3">${row.return_remarks ?? ""}</td>
                         <td class="px-4 py-3">${row.created_by ?? ""}</td>
                    </tr>`;
            });
            renderSaleOrderPagination(res);
        })
        .catch((err) => {
            let tbody = document.getElementById("Table-sale-order-list");
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-6 text-red-500">
                        Error loading data ⚠️
                    </td>
                </tr>
            `;
            console.error(err);
        });
}
const statusMap = {
    Pending: { color: "bg-yellow-100 text-yellow-700", emoji: "⏳" },
    Processing: { color: "bg-blue-100 text-blue-700", emoji: "⚙️" },
    Shipped: { color: "bg-purple-100 text-purple-700", emoji: "🚚" },
    Delivered: { color: "bg-green-100 text-green-700", emoji: "✅" },
    Cancelled: { color: "bg-red-100 text-red-700", emoji: "❌" },
    Returned: { color: "bg-orange-100 text-orange-700", emoji: "↩️" },
    "N/A": { color: "bg-gray-100 text-gray-600", emoji: "➖" },
};

function clearSaleOrderFilters() {
    document.getElementById("sale_order_status").value = "";
    document.getElementById("sale_order_payment_status").value = "";
    document.getElementById("sale_order_delivery_status").value = "";
    document.getElementById("so_from_posting_dateInput").value = "";
    document.getElementById("so_to_posting_dateInput").value = "";
    document.getElementById("sale_order_search").value = "";
    document.getElementById("sale_document_search").value = "";
    if (user_role != "cashier") {
        document.getElementById("sale_order_user_id").value = "";
    }

    loadSaleOrders(1);
}
function getDeliverySelect(status, rowId) {
    const options = Object.keys(statusMap);

    return `
        <select
            data-id="${rowId}"
            class="delivery-select px-4 py-1.5 rounded-xl border border-gray-200
                   bg-white text-gray-700 text-sm font-medium
                   shadow-sm hover:shadow-md
                   focus:outline-none focus:ring-2 focus:ring-gray-300
                   transition duration-150 ease-in-out cursor-pointer"
            onchange="updateDeliveryStatus(${rowId}, this.value, this)"
        >
            ${options
            .map(
                (opt) => `
                <option value="${opt}" ${opt === status ? "selected" : ""}>
                    ${statusMap[opt].emoji} ${opt}
                </option>
            `,
            )
            .join("")}
        </select>
    `;
}
function updateDeliveryStatus(rowId, newStatus) {
    // ✅ update all delivery selects with same row id instantly
    document
        .querySelectorAll(`.delivery-select[data-id="${rowId}"]`)
        .forEach((select) => {
            select.value = newStatus;
        });

    fetch("/sale-order/update-delivery-status", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                .value,
            Accept: "application/json",
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            id: rowId,
            delivery_status: newStatus,
        }),
    })
        .then((res) => res.json())
        .then((data) => {
            if (!data.success) throw new Error(data.message || "Update failed");

            showToast({
                message: "Delivery status updated 🚚",
                type: "success",
            });
        })
        .catch((err) => {
            showToast({
                message: err.message || "Failed to update status",
                type: "error",
            });
        });
}
function renderSaleOrderPagination(res) {
    const container = document.getElementById("paginationContainer_sale_order");
    const pageInfo = document.getElementById("pageInfo_sale_order");

    container.innerHTML = "";

    if (!res.links) return;

    pageInfo.textContent = `Page ${res.current_page} of ${res.last_page} | Total ${res.total}`;

    res.links.forEach((link) => {
        let label = link.label
            .replace("&laquo; Previous", "Prev")
            .replace("Next &raquo;", "Next");

        const btn = document.createElement("button");
        btn.innerHTML = label;
        btn.disabled = !link.url;

        btn.className = "page-btn" + (link.active ? " page-btn-active" : "");

        if (link.url) {
            const url = new URL(link.url);
            const page = url.searchParams.get("page");

            btn.onclick = () => loadSaleOrders(page);
        }

        container.appendChild(btn);
    });
}
function getStatusBadge(status) {
    const s = (status || "").toLowerCase();

    const map = {
        quotation: { text: "text-teal-600", label: "📝 Open" },
        ordered: { text: "text-blue-600", label: "📦 Ordered" },
        deposit: { text: "text-amber-600", label: "💰 Pending" }, // 👈 changed
        completed: { text: "text-green-600", label: "✅ Completed" },
        cancelled: { text: "text-red-600", label: "❌ Cancelled" },
        returned: { text: "text-purple-600", label: "↩️ Returned" },
    };

    const style = map[s] || {
        text: "text-gray-600",
        label: status, // fallback
    };

    return `
        <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full
                     bg-gray-100 border border-gray-200 shadow-sm ${style.text}">
            ${style.label}
        </span>
    `;
}
function getPaymentBadge(status) {
    switch ((status || "").toLowerCase()) {
        case "unpaid":
            return `<span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full bg-rose-50 text-rose-700 border border-rose-200 shadow-sm">
                        💸 Unpaid
                    </span>`;

        case "partial":
            return `<span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full bg-amber-50 text-amber-700 border border-amber-200 shadow-sm">
                        🌓 Partial
                    </span>`;

        case "paid":
            return `<span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                        💵 Paid
                    </span>`;

        case "refunded":
            return `<span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full bg-blue-50 text-blue-700 border border-blue-200 shadow-sm">
                        ↩️ Refunded
                    </span>`;

        default:
            return `<span class="px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700">${status}</span>`;
    }
}
function Save_Sale_Order() {
    const countCart = parseFloat(
        document.getElementById("count_cart_input")?.value || 0,
    );

    const newOrder = document.querySelector("#new_order");
    if (newOrder) newOrder.style.display = "block";

    const updateOrder = document.querySelector("#update_order");
    if (updateOrder) updateOrder.style.display = "none";

    if (!countCart) {
        showToast({ message: "Cart is Empty.", type: "error" });
        return;
    }
    const payUSDInput = document.getElementById("so_pay_usd");
    const payOtherInput = document.getElementById("so_pay_other");
    if (payUSDInput) payUSDInput.value = 0;
    if (payOtherInput) payOtherInput.value = 0;

    // reset bill discount each time the modal opens
    const billDiscInput = document.getElementById("so_invoice_discount");
    if (billDiscInput) billDiscInput.value = "0";

    // Canonical amounts due (values are clean — no thousands separators)
    //   #total_amount           → BASE USD       (exact)
    //   #converted_total_amount → DISPLAY ccy     (stepped / WYSIWYG)
    const totalUSD = parseFloat(
        document.querySelector("#total_amount")?.value || 0,
    );

    const factor_riel = rielFactor();
    const symbol = "៛";

    // Snap to the 100៛ grid, same as REMAINING/RETURN below. Raw USD × rate is
    // a binary float: 82.7093… × 4123 came out as 341000.96099999995, and that
    // whole string was being written into the field.
    const totalConverted = roundDisplay(totalUSD * factor_riel, symbol);


    // keep the "Pay as X" label + placeholder honest so nobody types riel into a $ field
    const otherLabelEl = document.getElementById("so_currency_display_name");
    if (otherLabelEl) otherLabelEl.textContent = symbol;
    const payOtherLabelInput = document.getElementById("so_pay_other");
    if (payOtherLabelInput) payOtherLabelInput.placeholder = "0 " + symbol;


    // Display the due amounts (USD as money 2-dp; converted shown as-is)
    const dueUSDEl = document.querySelector("#so_display_pay_amount");
    const dueCvtEl = document.querySelector("#so_display_pay_amount_converted");

    if (dueUSDEl) {
        dueUSDEl.value = totalUSD.toFixed(2) + " $";
        dueUSDEl.dataset.amount = totalUSD;
    }
    if (dueCvtEl) {
        dueCvtEl.value = totalConverted.toLocaleString("en-US") + " " + symbol;
        dueCvtEl.dataset.amount = totalConverted;
    }

    // today (en-CA → YYYY-MM-DD)
    const today = todayLocal();
    [
        "so_document_dateInput",
        "so_order_dateInput",
        "so_delivery_dateInput",
    ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = today;
    });

    validateSaleOrderPayment();
    document
        .getElementById("default-modal-sales-order-save")
        ?.classList.remove("hidden");
}
function Confirm_Save_Sale_Order(document_status, btn = null) {
    const customerName =
        document.getElementById("so_customer_name_info")?.value?.trim() || "";

    // ask confirm if customer empty
    if (customerName === "") {
        pendingSaleOrderStatus = document_status;
        pendingSaleOrderButton = btn;

        document
            .getElementById("confirm_customer_empty_modal")
            .classList.remove("hidden");

        return;
    }

    // normal submit
    submitSaleOrder(document_status, btn);
}

// Local calendar date YYYY-MM-DD — reads the PC clock's day directly.
// getFullYear/getMonth/getDate are LOCAL, so no midnight roll-back.
function todayLocal() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
}
function moneyNumber(value) {
    return (
        parseFloat(
            String(value || "0")
                .replace(/,/g, "")
                .replace(/[^\d.-]/g, ""),
        ) || 0
    );
}

// The POS screen carries its riel rate in #riel_factor (cart.blade.php).
// #currency_display_factor belongs to the PURCHASING screen and does not exist
// here — script.js is not even loaded there — so querying it always returned
// null and fell back to a factor of 1. That made "payOther / factor" a no-op,
// so riel tendered was banked as if it were dollars: paying 20,000៛ on a
// 24,600៛ order stored 20,001.12 USD and reported 82,004,600៛ paid.
function rielFactor() {
    return Number(document.querySelector("#riel_factor")?.value) || 4000;
}
function validateSaleOrderPayment(e = null) {
    const payUSDInput = document.getElementById("so_pay_usd");
    const payOtherInput = document.getElementById("so_pay_other");
    const billDiscInput = document.getElementById("so_invoice_discount");
    if (!payUSDInput || !payOtherInput) return true;

    cleanMoneyInput(payUSDInput);
    cleanMoneyInput(payOtherInput);
    if (billDiscInput) cleanMoneyInput(billDiscInput);   // keep discount numeric

    updateSaleOrderRemaining();
    return true;
}
function cleanMoneyInput(input) {
    if (!input) return;

    let value = input.value;

    value = value.replace(/[^\d.]/g, "");

    // keep only first dot
    const parts = value.split(".");
    if (parts.length > 2) {
        value = parts[0] + "." + parts.slice(1).join("");
    }

    if (value === "" || value === "0.") {
        input.value = value;
        return;
    }

    if (value.includes(".")) {
        let [intPart, decPart] = value.split(".");
        intPart = intPart.replace(/^0+/, "") || "0";
        input.value = intPart + "." + decPart;
    } else {
        input.value = value.replace(/^0+/, "") || "0";
    }
}


// USD is the BASE currency (factor 1) and has NO row in the currency table,
// so its symbol can't be read from one. The only table currency is riel,
// whose factor the admin changes. → symbol is a pure function of the factor.
// We never trust #currency_display_symbol any more.

// display rules per exchange factor — mirror of the Blade $step/$decimal
function currencyRules(factor) {
    factor = Number(factor) || 1;
    if (factor === 1) return { decimal: 2, step: 0 }; // USD
    if (factor >= 4000) return { decimal: 0, step: 100 }; // KHR → nearest 100
    if (factor >= 100) return { decimal: 3, step: 0 }; // mid-rate
    return { decimal: 2, step: 0 };
}

// round a display-currency amount per its currency rules
function roundDisplay(amount, symbol) {
    if (symbol === "៛") {
        return Math.round(amount / 100) * 100; // nearest 100 riel
    }

    return Math.round(amount * 100) / 100;
}

function updateSaleOrderRemaining() {
    // Base USD total
    const totalUSD = moneyNumber(
        document.querySelector("#total_amount")?.value ??
        document.querySelector("#so_display_pay_amount")?.value ??
        0
    );



    // Bill discount (USD)
    const billDiscUSD = moneyNumber(
        document.getElementById("so_invoice_discount")?.value ?? 0
    );

    const netOwedUSD = Math.max(0, totalUSD - billDiscUSD);

    // Payments
    // dataset.amount holds the unrounded figure; .value is the 2-dp version
    // shown on screen. Reading .value here would settle against a rounded
    // payment and leave a few riel outstanding on every reloaded order.
    const paidEl = document.querySelector("#paid_amount");
    const oldPaidUSD = paidEl?.dataset?.amount !== undefined
        ? Number(paidEl.dataset.amount) || 0
        : moneyNumber(paidEl?.value ?? 0);

    const payUSD = moneyNumber(
        document.getElementById("so_pay_usd")?.value ?? 0
    );

    const payOther = moneyNumber(
        document.getElementById("so_pay_other")?.value ?? 0
    );

    // Currency
    const factorRiel = Number(
        document.querySelector("#riel_factor")?.value ?? 4000
    );

    const symbol = "៛";
    const totalOther = totalUSD  * factorRiel;
    // Use display rate if available, otherwise fall back to currency factor
    const rate = totalUSD > 0
        ? totalOther / totalUSD
        : factorRiel;

           // Display total (Riel)


    // Convert payment to USD
    const paidUSD = oldPaidUSD + payUSD + (rate > 0 ? payOther / rate : 0);

    const balanceUSD = netOwedUSD - paidUSD;

    const remainUSD = Math.max(balanceUSD, 0);
    const returnUSD = Math.max(-balanceUSD, 0);

    const remainOther = roundDisplay(remainUSD * rate, symbol);
    const returnOther = roundDisplay(returnUSD * rate, symbol);

    const set = (id, value) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    };

    set("so_need_more_usd", `${remainUSD.toFixed(2)} $`);
    set("so_need_more_other", `${remainOther.toLocaleString()} ${symbol}`);

    set("so_return_usd", `${returnUSD.toFixed(2)} $`);
    set("so_return_other", `${returnOther.toLocaleString()} ${symbol}`);
}

let selectedSaleOrderId = null;
let saleOrderRowsById = {}; // id -> raw row data from /get-sale-orders, refreshed each loadSaleOrders() call

// Right-click a Sale Order row: select it (same as a normal click), then
// show a floating menu with View Line / print options / Pay — Pay only
// appears when the order still has money owed (balance_amount > 0).
let saleOrderRowMenuEl = null;

function closeSaleOrderRowMenu() {
    saleOrderRowMenuEl?.remove();
    saleOrderRowMenuEl = null;
    document.removeEventListener("click", closeSaleOrderRowMenu);
    document.removeEventListener("keydown", handleSaleOrderRowMenuEscape);
}

function handleSaleOrderRowMenuEscape(e) {
    if (e.key === "Escape") closeSaleOrderRowMenu();
}

function showSaleOrderRowMenu(event, rowElement, id) {
    event.preventDefault();
    closeSaleOrderRowMenu();

    selectSaleOrderRow(rowElement, id);

    const row = saleOrderRowsById[id] || {};
    const shipped = row.status === "Deposit" || row.status === "Completed";

    // kind: "form" = opens something (a modal, print preview, or the cart)
    // for you to review/fill in before anything actually happens; "action" =
    // fires immediately (after a confirm for anything destructive) — labeled
    // in the menu so it's obvious at a glance which is which.
    const items = [
        { label: "View Line", icon: "fa-eye", kind: "form", action: () => viewSaleOrderLine(id) },
        { label: "Print Invoice", icon: "fa-file-invoice", kind: "form", action: () => printSelectedSaleOrderInvoice() },
        { label: "Print Delivery Note", icon: "fa-truck-fast", kind: "form", action: () => printSelectedSaleOrderDeliveryNote() },
        { label: "Print Receipt", icon: "fa-receipt", kind: "form", action: () => printSelectedSaleOrderReceipt() },
        { label: "Picking List", icon: "fa-boxes-packing", kind: "form", action: () => printSelectedSaleOrderPickingList() },
    ];

    // Only offered once the order has actually shipped — before that there's
    // no invoice/stock movement yet for there to be anything to return.
    if (typeof canSellPos !== "undefined" && canSellPos && shipped) {
        items.push({ label: "Sale Return", icon: "fa-rotate-left", kind: "form", action: () => SaleReturn() });
    }

    // Loads the order into the cart (same as before) — the correct
    // Ship/Pay/Ship & Pay button then auto-shows in the cart footer based
    // on this exact order's state, so the menu just needs to load it and
    // get out of the way; the label here just tells the cashier what to
    // expect next.
    if (row.status === "Ordered") {
        if (row.payment_status === "Paid") {
            items.push({ label: "Ship (Deduct Stock)", icon: "fa-truck-fast", kind: "form", action: () => paySelectedSaleOrder(id), highlight: true });
        } else {
            items.push({ label: "Complete (Ship & Pay)", icon: "fa-money-bill-wave", kind: "form", action: () => paySelectedSaleOrder(id), highlight: true });
        }
    } else if (row.status === "Deposit" && Number(row.balance_amount ?? 0) > 0) {
        items.push({ label: "Pay", icon: "fa-money-bill-wave", kind: "form", action: () => paySelectedSaleOrder(id), highlight: true });
    }

    // Only for orders that never shipped — stock was never taken, so
    // cancelling here is a safe no-op on inventory. An already-shipped
    // order needs Sale Return instead (properly reverses stock/ledger).
    if (typeof canSellPos !== "undefined" && canSellPos && (row.status === "Ordered" || row.status === "Quotation")) {
        items.push({ label: "Cancel Order", icon: "fa-ban", kind: "action", action: () => cancelSelectedSaleOrder(id) });
    }

    const kindTag = (kind) => kind === "action"
        ? `<span style="margin-left:auto; font-size:10px; font-weight:700; letter-spacing:.02em; color:#b45309; background:#fef3c7; padding:2px 6px; border-radius:999px;">ACTION</span>`
        : `<span style="margin-left:auto; font-size:10px; font-weight:700; letter-spacing:.02em; color:#6b7280; background:#f3f4f6; padding:2px 6px; border-radius:999px;">FORM</span>`;

    const menu = document.createElement("div");
    menu.id = "saleOrderRowMenu";
    menu.style.cssText = `
        position:fixed; z-index:99999; min-width:230px;
        background:#fff; border:1px solid #e5e7eb; border-radius:10px;
        box-shadow:0 10px 30px rgba(0,0,0,.2); padding:6px; font-family:inherit;
    `;
    menu.innerHTML = items.map((it, i) => `
        <button type="button" data-idx="${i}" style="display:flex; align-items:center; gap:10px;
            width:100%; text-align:left; padding:9px 12px; border:none; background:${it.highlight ? "#ecfdf5" : "transparent"};
            color:${it.highlight ? "#047857" : "#374151"}; font-weight:${it.highlight ? "700" : "500"};
            font-size:13.5px; border-radius:6px; cursor:pointer;">
            <i class="fa-solid ${it.icon}" style="width:16px;"></i> ${it.label} ${kindTag(it.kind)}
        </button>
    `).join("");

    document.body.appendChild(menu);
    saleOrderRowMenuEl = menu;

    // position, then clamp so it never renders off-screen
    const maxX = window.innerWidth - menu.offsetWidth - 8;
    const maxY = window.innerHeight - menu.offsetHeight - 8;
    menu.style.left = Math.min(event.clientX, Math.max(8, maxX)) + "px";
    menu.style.top = Math.min(event.clientY, Math.max(8, maxY)) + "px";

    menu.querySelectorAll("button[data-idx]").forEach((btn) => {
        btn.addEventListener("mouseenter", () => { btn.style.background = items[btn.dataset.idx].highlight ? "#d1fae5" : "#f3f4f6"; });
        btn.addEventListener("mouseleave", () => { btn.style.background = items[btn.dataset.idx].highlight ? "#ecfdf5" : "transparent"; });
        btn.addEventListener("click", () => {
            items[btn.dataset.idx].action();
            closeSaleOrderRowMenu();
        });
    });

    // let this click finish before arming the outside-click closer
    setTimeout(() => {
        document.addEventListener("click", closeSaleOrderRowMenu);
        document.addEventListener("keydown", handleSaleOrderRowMenuEscape);
    }, 0);
}

// Loads a pending/partially-paid order into the cart so the cashier can
// collect the remaining payment — same underlying flow as Load_order()
// (used by the View Line modal), just triggered from the row menu instead.
function paySelectedSaleOrder(id) {
    const input_count_cart = document.getElementById("count_cart_input");
    if (Number(input_count_cart?.value ?? 0) > 0) {
        showToast({ message: "Cart is not empty — clear it before loading this order for payment.", type: "error" });
        return;
    }

    Livewire.dispatch("load-sale-order-to-cart", { saleOrderId: id });
    closeSaleOrderModal();
}

// Only offered for orders that never shipped (Ordered/Quotation) — cancelling
// this way just relabels the order, it doesn't reverse stock/ledger, so it's
// only safe here. An already-shipped order needs Sale Return instead, which
// properly reverses the stock it took.
async function cancelSelectedSaleOrder(id) {
    const confirmed = await askConfirm("This order will be marked Cancelled. This cannot be undone.", {
        title: "Cancel this order?",
        confirmLabel: "Yes, Cancel Order",
    });
    if (!confirmed) return;

    try {
        const res = await fetch("/update-sale-order-status", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? document.querySelector('input[name="_token"]')?.value,
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ sale_order_id: id, status: "Cancelled" }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || res.status);

        showToast({ message: "Order cancelled", type: "success" });
        loadSaleOrders(1);
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to cancel order", type: "error" });
    }
}

function selectSaleOrderRow(rowElement, id) {
    selectedSaleOrderId = id;

    document.getElementById("saleOrderRowActions")?.classList.remove("hidden");

    document.querySelectorAll("#Table-sale-order tbody tr").forEach((tr) => {
        tr.classList.remove("bg-blue-100", "shadow-lg", "scale-[1.01]");

        tr.querySelectorAll("td").forEach((td) => {
            td.classList.remove(
                "bg-blue-100",
                "border-t",
                "border-b",
                "border-blue-400",
            );
        });
    });

    // highlight row
    rowElement.classList.add(
        "shadow-lg",
        "scale-[1.01]",
        "transition",
        "duration-150",
    );

    rowElement.querySelectorAll("td").forEach((td) => {
        td.classList.add(
            "bg-blue-100",
            "border-t",
            "border-b",
            "border-blue-400",
        );
    });
    // get all td
    const tds = rowElement.querySelectorAll("td");

    document.querySelector("#return_document_no").value =
        tds[2].innerText.trim();

    // uncheck all
    document.querySelectorAll(".sale-order-checkbox").forEach((cb) => {
        cb.checked = false;
    });

    // check current
    const checkbox = rowElement.querySelector(".sale-order-checkbox");

    if (checkbox) {
        checkbox.checked = true;
    }
}
function closeViewLotModal() {
    const modal = document.getElementById('viewLotModal');

    if (!modal) {
        console.warn('viewLotModal not found');
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function hightlightRow(table_id, rowElement) {
    document.querySelectorAll(`#${table_id} tbody tr`).forEach((tr) => {
        tr.classList.remove(
            "shadow-lg",
            "scale-[1.01]",
            "transition",
            "duration-150",
        );

        tr.querySelectorAll("td").forEach((td) => {
            td.classList.remove(
                "bg-blue-100",
                "border-t",
                "border-b",
                "border-blue-400",
                "text-blue-900",
                "font-semibold",
            );
        });
    });

    // highlight row
    rowElement.classList.add(
        "shadow-lg",
        "scale-[1.01]",
        "transition",
        "duration-150",
    );

    rowElement.querySelectorAll("td").forEach((td) => {
        td.classList.add(
            "bg-blue-100",
            "border-t",
            "border-b",
            "border-blue-400",
            "text-blue-900",
            "font-semibold",
        );
    });
}
function viewSelectedSaleOrderLine() {
    if (!selectedSaleOrderId) {
        showToast({
            message: "Please select a sale order first",
            type: "error",
        });

        return;
    }

    // Load data first
    viewSaleOrderLine(selectedSaleOrderId);
}

async function fetchSaleOrderForPrint(id) {
    const res = await fetch(`/sale-order-lines/${id}`);
    if (!res.ok) throw new Error("Failed to load sale order");
    return res.json();
}

// Letterhead for a document that already exists: the server resolves it from
// the user who ISSUED the invoice, so a reprint keeps the original profile.
// pos_profile_for_print is the SIGNED-IN user's profile — only correct for a
// document being created right now, and the wrong answer for every reprint.
function docProfile(data) {
    return data?.posInfo ?? pos_profile_for_print;
}

async function printSelectedSaleOrderInvoice() {
    if (!selectedSaleOrderId) {
        showToast({ message: "Please select a sale order first", type: "error" });
        return;
    }
    if (!(await askPrintConfirm("Print Invoice for this sale order?"))) return;

    try {
        const data = await fetchSaleOrderForPrint(selectedSaleOrderId);
        await printInvoiceA4(data.header, data.lines, docProfile(data));
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to print invoice", type: "error" });
    }
}

async function printSelectedSaleOrderDeliveryNote() {
    if (!selectedSaleOrderId) {
        showToast({ message: "Please select a sale order first", type: "error" });
        return;
    }
    if (!(await askPrintConfirm("Print Delivery Note for this sale order?"))) return;

    try {
        const data = await fetchSaleOrderForPrint(selectedSaleOrderId);
        await printDeliveryNoteA4(data.header, data.lines, docProfile(data));
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to print delivery note", type: "error" });
    }
}

async function printSelectedSaleOrderPickingList() {
    if (!selectedSaleOrderId) {
        showToast({ message: "Please select a sale order first", type: "error" });
        return;
    }
    try {
        const res = await fetch(`/picking-list-data/${selectedSaleOrderId}`);
        if (!res.ok) throw new Error(res.status);
        const data = await res.json();
        await printPickingListA4(data.header, data.rows, docProfile(data));
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to load picking list", type: "error" });
    }
}

async function printSelectedSaleOrderReceipt() {
    if (!selectedSaleOrderId) {
        showToast({ message: "Please select a sale order first", type: "error" });
        return;
    }
    if (!(await askPrintConfirm("Print receipt for this sale order?"))) return;

    try {
        const data = await fetchSaleOrderForPrint(selectedSaleOrderId);
        await print_document_v2("Invoice", data.header, docProfile(data), data.lines);
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to print receipt", type: "error" });
    }
}

// Close modal
function closeSaleOrderLineModal() {
    const modal = document.getElementById("saleOrderLineModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function Load_order(e) {
    if (!currentSaleOrderId) {
        alert("No sale order selected");
        return;
    }
    // check cart Logic
    let input_count_cart = document.getElementById("count_cart_input");
    let count_cart = input_count_cart.value;
    if (count_cart > 0) {
        showToast({
            message: "Cart is not Empty.",
            type: "error",
        });
        return;
    }

    Livewire.dispatch("load-sale-order-to-cart", {
        saleOrderId: currentSaleOrderId,
    });

    closeSaleOrderLineModal();
}

document
    .getElementById("so_customer_type")
    .addEventListener("change", function () {
        const deliverySection = document.getElementById("delivery_section");

        if (this.value === "At-Delivery") {
            deliverySection.classList.remove("hidden");
        } else {
            deliverySection.classList.add("hidden");

            document.getElementById("so_delivery_dateInput").value = "";
            document.getElementById("so_delivery_status").value = "N/A";
            document.getElementById("so_delivery_info_status").value = "";
            document.getElementById("so_driver_name").value = "";
            document.getElementById("so_driver_phone").value = "";
        }
    });

function changeSaleOrderStatus(status) {
    const select = document.getElementById("sale-order-status");

    let className = "";

    switch (status) {
        case "Quotation":
            className = "bg-gray-100 text-gray-700";
            break;
        case "Ordered":
            className = "bg-yellow-100 text-yellow-700";
            break;
        case "Deposit":
            className = "bg-blue-100 text-blue-700";
            break;
        case "completed":
            className = "bg-green-100 text-green-700";
            break;
        case "cancelled":
            className = "bg-red-100 text-red-700";
            break;
    }

    select.className = `px-3 py-1 rounded-full text-sm font-semibold border-none outline-none ${className}`;

    // fetch('/update-sale-order-status', ...)
}

let currentSaleOrderId = null;
let document_type_for_print = null;
window.addEventListener("view-line-sale-order", (e) => {
    let id = e.detail[0].id;
    viewSaleOrderLine(id);
});

// Hook for Print
window.addEventListener("print-sale-order", (e) => {
    let header = e.detail[0].header;
    let posInfo = e.detail[0].posInfo;
    print_document_v2(document_type_for_print, header, posInfo);
    Livewire.dispatch("clearCart_no_message");
});
function print_preview() {
    const name = document.getElementById("so_customer_name_info")?.value;
    const phone = document.getElementById("so_customer_phone_info")?.value;
    const address = document.getElementById("so_customer_address_info")?.value;
    const taxi = document.getElementById("so_remark_invoice")?.value;

    let header = {
        contact_name: name,
        phone: phone,
        address: address,
        remarks: taxi,
    };

    print_document_v2("Invoice", header, pos_profile_for_print || null);
}

// ---- Sale Order "Print" dropdown (Invoice / Delivery / Receipt / Entire Table) ----
// position:fixed + coordinates from the button's own rect, so the menu is
// never clipped by an ancestor modal's `overflow-hidden` (position:absolute
// nested inside that modal was being clipped/hidden instead of floating
// above it, regardless of z-index).
function togglePrintMenu(menuId, buttonEl) {
    const menu = document.getElementById(menuId);
    if (!menu) return;
    const isOpen = !menu.classList.contains("hidden");
    closePrintMenus();
    if (isOpen) return;

    if (buttonEl) {
        const rect = buttonEl.getBoundingClientRect();
        menu.style.left = `${rect.left}px`;
        menu.style.bottom = `${window.innerHeight - rect.top + 8}px`;
        menu.style.top = "auto";
    }
    menu.classList.remove("hidden");
}

function closePrintMenus() {
    document.querySelectorAll("[id$='-print-menu']").forEach((m) => m.classList.add("hidden"));
}

document.addEventListener("click", (e) => {
    if (e.target.closest("[id$='-print-menu']") || e.target.closest("#btn-print-invoice")) return;
    closePrintMenus();
});

// currentSaleOrderData is already loaded (header + lines) whenever this
// modal is open — printing straight from it avoids the old Livewire
// round-trip, which scraped the *live cart's* DOM table (wrong data for a
// historical order) and cleared the current cart as a side effect.
async function printSaleOrderDataAs(kind) {
    if (!currentSaleOrderData) {
        showToast({ message: "No sale order loaded", type: "error" });
        return;
    }
    const { header, lines } = currentSaleOrderData;
    const posInfo = docProfile(currentSaleOrderData);

    try {
        if (kind === "invoice") {
            await printInvoiceA4(header, lines, posInfo);
        } else if (kind === "delivery") {
            await printDeliveryNoteA4(header, lines, posInfo);
        } else if (kind === "receipt") {
            if (!(await askPrintConfirm("Print receipt now?"))) return;
            await print_document_v2("Invoice", header, posInfo, lines);
        } else if (kind === "table") {
            await printSaleOrderFullTableA4(header, lines, posInfo);
        } else if (kind === "picking") {
            // The picking list is lot-level, so it comes from its own endpoint
            // rather than the header/lines already loaded into this modal.
            const id = header?.id ?? currentSaleOrderId;
            const res = await fetch(`/picking-list-data/${id}`, {
                headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            });
            if (!res.ok) throw new Error(`picking list request failed (${res.status})`);
            const data = await res.json();
            await printPickingListA4(data.header, data.rows, docProfile(data));
        }
    } catch (err) {
        console.error(err);
        showToast({
            message: `Failed to print — ${err?.name ?? "Error"}: ${err?.message ?? err}`,
            type: "error",
        });
    }
}

let currentSaleOrderData = null;

function smartNumber(value, decimals = 6) {
    value = Number(value || 0);
    return value.toFixed(decimals).replace(/\.?0+$/, "");
}

function formatQty(value) {
    return smartNumber(value, 6);
}

function formatCurrency(value, factor) {
    factor = parseFloat(factor) || 1;
    const converted = Number(value || 0) * factor;

    if (factor > 1) {
        // Snap to the same 100៛ grid the cart totals use, so the detail modal
        // can never quote a figure that was never charged.
        return (Math.round(converted / 100) * 100).toLocaleString("en-US");
    }

    // These are amounts of money, so 2 decimals — not the raw stored precision.
    // smartNumber(…, 6) printed "22.682925 $" because a riel total divides back
    // into a long USD fraction (93,000៛ / 4100).
    return converted.toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * Money formatter for every LIST view.
 *
 * Amounts are stored in base USD. When the UI is in Khmer they are shown in
 * Riel, converted with the rate that was saved ON THE DOCUMENT (`header.factor`)
 * — not today's rate — so a historical order keeps showing the value it was
 * actually sold at. Rows whose own factor is missing or 1 (document saved in
 * USD) fall back to the shop's current default rate, and if that is unavailable
 * too the amount stays in USD rather than silently showing an unconverted
 * number labelled as Riel.
 *
 * Riel is rounded to the nearest 100 and shown without decimals, matching how
 * cash is actually handled.
 *
 * @param {number|string} value   amount in base USD
 * @param {number|string} [rowFactor] the document header's own factor
 */
function listMoney(value, rowFactor) {
    const amount = Number(value ?? 0);

    if (typeof app_locale === "undefined" || app_locale !== "km") {
        return `$${amount.toFixed(2)}`;
    }

    let rate = Number(rowFactor);
    if (!Number.isFinite(rate) || rate <= 1) {
        // `factor` is the shop default (exposed by the POS page)
        rate = typeof factor !== "undefined" ? Number(factor) : NaN;
    }
    if (!Number.isFinite(rate) || rate <= 1) {
        return `$${amount.toFixed(2)}`;
    }

    const riel = Math.round((amount * rate) / 100) * 100;
    return `${riel.toLocaleString("en-US")}៛`;
}
// Sale Order Details modal — currency view toggle (USD <-> the order's own
// currency, usually Riel). Deliberately a distinct name from the other
// getSelectedCurrency() (reads a different, unrelated #currency_select
// element for the preview modal) — the previous shared name meant this
// modal was silently reading the wrong element via JS's last-declaration-
// wins hoisting, so its currency toggle never actually worked.
let saleOrderViewCurrency = { factor: 1, currency: "$" };

/**
 * Which currency a document detail should open in.
 *
 * A Khmer-speaking cashier works in riel, so opening every document in USD made
 * them toggle on each one. The document's own factor is used — not today's rate
 * — so a historical order still reads at the rate it was sold at. Falls back to
 * USD when the document has no factor (it was priced in dollars) or the UI is
 * in English.
 */
function defaultDetailCurrency(header) {
    const factor = Number(header?.factor || 1);
    const isKhmer = typeof app_locale !== "undefined" && app_locale === "km";

    if (isKhmer && factor > 1) {
        return { factor, currency: header?.currency_name || "៛" };
    }
    return { factor: 1, currency: "$" };
}

function toggleSaleOrderCurrency() {
    const header = currentSaleOrderData?.header ?? {};
    const headerFactor = Number(header.factor || 1);
    const headerCurrency = header.currency_name || "៛";

    if (saleOrderViewCurrency.factor === 1 && headerFactor > 1) {
        saleOrderViewCurrency = { factor: headerFactor, currency: headerCurrency };
    } else {
        saleOrderViewCurrency = { factor: 1, currency: "$" };
    }

    const label = document.getElementById("sale-currency-toggle-label");
    if (label) {
        label.textContent =
            saleOrderViewCurrency.factor === 1 ? `View in ${headerCurrency}` : "View in $";
    }

    if (currentSaleOrderData) renderSaleOrderLine(currentSaleOrderData);
}

// Same state-based labeling as the Sale Order list's right-click menu — the
// button always just loads the order into the cart (Load_order() ->
// loadSaleOrderToCart), the label just tells the cashier what will actually
// happen once it's there (the matching Ship/Pay/Ship & Pay button auto-shows
// via the change-document-type listener).
function updateOrderDetailActionLabel(header) {
    const labelEl = document.getElementById("btn-sale-order-detail-update-label");
    const btnEl = document.getElementById("btn-sale-order-detail-update");
    if (!labelEl || !btnEl) return;

    if (header.status === "Ordered") {
        btnEl.classList.remove("hidden");
        labelEl.textContent = header.payment_status === "Paid" ? "Ship (Deduct Stock)" : "Complete (Ship & Pay)";
    } else if (header.status === "Deposit" && Number(header.balance_amount ?? 0) > 0) {
        btnEl.classList.remove("hidden");
        labelEl.textContent = "Pay";
    } else {
        btnEl.classList.add("hidden");
    }
}

async function viewSaleOrderLine(id) {
    try {
        currentSaleOrderId = id;
        // The print buttons in this modal read selectedSaleOrderId. Opening the
        // modal from a row sets it, but the Livewire "view-line-sale-order"
        // event calls straight in here without selecting a row — leaving those
        // buttons pointing at a stale order, or refusing with "Please select a
        // sale order first". The order being viewed IS the selected one.
        selectedSaleOrderId = id;

        const modal = document.getElementById("saleOrderLineModal");
        modal.classList.remove("hidden");
        modal.classList.add("flex");

        const res = await fetch(`/sale-order-lines/${id}`);
        if (!res.ok) throw new Error("Failed to load sale order");

        const data = await res.json();
        currentSaleOrderData = data;

        // Open in the currency the user actually works in — riel for a Khmer UI,
        // USD otherwise. The toggle still switches either way.
        saleOrderViewCurrency = defaultDetailCurrency(data.header);
        const label = document.getElementById("sale-currency-toggle-label");
        if (label) {
            label.textContent =
                saleOrderViewCurrency.factor === 1
                    ? `View in ${data.header?.currency_name || "៛"}`
                    : "View in $";
        }

        renderSaleOrderLine(data);
    } catch (e) {
        console.error(e);
        alert("Error loading sale order");
    }
}

function renderSaleOrderLine(data) {
    const header = data.header ?? {};
    const { factor, currency } = saleOrderViewCurrency;
    const setText = (id, value = "-") => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || "-";
    };

    document.querySelector("#sale-order-status").innerHTML = getStatusBadge(
        header.status,
    );

    document.querySelector("#sale-payment-status").innerHTML = getPaymentBadge(
        header.payment_status,
    );

    updateOrderDetailActionLabel(header);

    document.getElementById("sale-delivery-status").innerHTML =
        getDeliverySelect(header.delivery_status, header.id);

    document.querySelector("#sale_order_id").value =
        header.id ?? currentSaleOrderId;

    setText("sale-order-no", header.document_no);
    setText("sale-order-created-by", header.created_by);
    setText("sale-order-posting-date", header.posting_date);

    setText("sale-order-customer", header.contact_name);
    setText("sale-order-phone", header.phone);
    setText("sale-order-address", header.address);
    // "Taxi Phone" is the remarks column — see the order form's #so_remark_invoice.
    setText("sale-order-taxi-phone", header.remarks);

    setText("sale-order-payment-method", header.payment_method);
    setText("sale-order-delivery-date", header.delivery_date);
    // document.getElementById("sale-delivery-status").innerHTML =
    //     getDeliverySelect(header.delivery_status || "N/A", header.id);

    setText("sale-order-delivery-info", header.delivery_info);
    setText("sale-order-driver-name", header.driver_name);
    setText("sale-order-driver-phone", header.driver_phone);

    setText(
        "sale-order-total",
        `${formatCurrency(header.total_amount, factor, currency)} ${currency}`,
    );
    setText(
        "sale-order-discount",
        `${formatCurrency(header.discount_amount, factor, currency)} ${currency}`,
    );
    setText(
        "sale-order-vat",
        `${formatCurrency(header.vat_amount, factor, currency)} ${currency}`,
    );
    setText(
        "sale-order-grand-total",
        `${formatCurrency(header.grand_total, factor, currency)} ${currency}`,
    );

    if (factor !== 1) {
        setText("currency-rate-info", `1$ = ${parseFloat(factor)} ${currency}`);
    } else {
        setText("currency-rate-info", "");
    }

    let html = "";

    (data.lines ?? []).forEach((line, index) => {
        // A service (delivery fee and the like) has no quantity to count and
        // nothing to ship, so those two cells stay empty rather than claiming a
        // meaningless "0" — and the shipped cell no longer flags it red for
        // being under-delivered.
        const isService = (line.type ?? "product") === "service";
        const shippedShort =
            parseFloat(line.quantity_shiped ?? 0) < parseFloat(line.quantity ?? 0);

        html += `
            <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2">${index + 1}</td>
                <td class="px-4 py-2">${line.item_code ?? ""}</td>
                <td class="px-4 py-2">${line.name ?? ""}</td>

                <td class="px-4 py-2 text-right">${isService ? "" : formatQty(line.quantity)}</td>

                <td class="px-4 py-2 text-right font-bold ${isService ? "" : shippedShort ? "text-red-500" : "text-green-500"}">
                    ${isService ? "" : formatQty(line.quantity_shiped)}
                </td>

                <td class="px-4 py-2 text-right">${formatCurrency(line.sell_price, factor, currency)} ${currency}</td>
                <td class="px-4 py-2 text-right">${formatCurrency(line.sub_total, factor, currency)} ${currency}</td>
                <td class="px-4 py-2 text-right">${formatCurrency(line.discount_amount, factor, currency)} ${currency}</td>
                <td class="px-4 py-2 text-right">${formatCurrency(line.vat_amount, factor, currency)} ${currency}</td>
                <td class="px-4 py-2 text-right">${formatCurrency(line.grand_total_amount, factor, currency)} ${currency}</td>
            </tr>
        `;
    });

    document.getElementById("sale-line-data").innerHTML = html;
}

function openSaleLine() {
    const modal = document.getElementById("saleOrderLineModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}
/* ================= Quotation customer: search / select / create ================= */

function applyQuotationCustomer(customer) {
    document.getElementById("quotation-customer-id").value = customer.id ?? "";
    document.getElementById("quotation-customer-name").value =
        customer.name ?? "";
    document.getElementById("quotation-customer-phone").value =
        customer.phone ?? "";
    document.getElementById("quotation-customer-address").value =
        customer.address1 ?? customer.address ?? "";

    const search = document.getElementById("quotation-customer-search");
    if (search) search.value = customer.name ?? "";
    document.getElementById("quotation-customer-list")?.classList.add("hidden");
}

function openCustomerCreateFor(context) {
    window.customerCreateContext = context;
    const modal = document.getElementById("default-modal-customer");
    if (modal) {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    }
}

(function () {
    const search = document.getElementById("quotation-customer-search");
    const list = document.getElementById("quotation-customer-list");
    if (!search || !list) return;

    search.addEventListener("input", async () => {
        const value = search.value.trim();

        if (value.length === 0) {
            list.classList.add("hidden");
            return;
        }

        try {
            const res = await fetch(
                `/customers/search?q=${encodeURIComponent(value)}`,
            );
            const data = await res.json();

            list.innerHTML = "";

            if (data.length === 0) {
                list.innerHTML =
                    '<li class="px-3 py-2 text-sm text-gray-500">No results found</li>';
            } else {
                data.forEach((customer) => {
                    const li = document.createElement("li");
                    li.textContent = `${customer.customer_code} - ${customer.name}`;
                    li.className =
                        "px-3 py-2 cursor-pointer hover:bg-gray-100 text-sm";
                    li.addEventListener("click", () => {
                        applyQuotationCustomer(customer);
                    });
                    list.appendChild(li);
                });
            }

            list.classList.remove("hidden");
        } catch (err) {
            console.error(err);
        }
    });

    document.addEventListener("click", (e) => {
        if (!search.contains(e.target) && !list.contains(e.target)) {
            list.classList.add("hidden");
        }
    });
})();

// The Preview button opens this same modal with the quotation half switched
// off. Remembering the title is what lets an edit ("Edit Quotation Q-12")
// survive a preview and come back intact when the modal is reopened via Quote.
let quotationModalTitleBeforePreview = null;

// Which document the modal's Preview button prints. Opened via Quote it is a
// quotation; opened via Preview it is the invoice the customer is about to be
// given, which is what a cashier actually shows them before taking payment.
let quotationModalIsPreviewOnly = false;

function setQuotationModalMode(previewOnly) {
    quotationModalIsPreviewOnly = previewOnly;

    const title = document.getElementById("quotation-modal-title");
    const subtitle = document.getElementById("quotation-modal-subtitle");
    const saveBtn = document.getElementById("btn-save-quotation");

    if (previewOnly) {
        if (quotationModalTitleBeforePreview === null) {
            quotationModalTitleBeforePreview = title.textContent;
        }
        title.textContent = "Preview";
        if (subtitle) subtitle.textContent = "Preview this cart — nothing is saved";
        saveBtn?.classList.add("hidden");
        return;
    }

    if (quotationModalTitleBeforePreview !== null) {
        title.textContent = quotationModalTitleBeforePreview;
        quotationModalTitleBeforePreview = null;
    }
    if (subtitle) subtitle.textContent = "Create or edit a quotation for a customer";
    saveBtn?.classList.remove("hidden");
}

window.addEventListener("open-quotation-preview", (event) => {
    const previewOnly = event.detail.previewOnly === true;
    setQuotationModalMode(previewOnly);

    // Preview leaves the fields exactly as the user left them — whatever is
    // typed is what the printed preview says — so only the quotation path
    // resets them.
    //
    // Only reset to "new quotation" mode if no quotation is currently loaded
    // for editing (loadQuotationToCartUI already filled these fields).
    if (!previewOnly && !document.getElementById("quotation_id").value) {
        document.getElementById("quotation-customer-id").value = "";
        document.getElementById("quotation-customer-search").value = "";
        document.getElementById("quotation-customer-name").value = "";
        document.getElementById("quotation-customer-phone").value = "";
        document.getElementById("quotation-customer-address").value = "";
        document.getElementById("quotation-remark").value = "";
        document.getElementById("quotation-modal-title").textContent =
            "Save Quotation";
        document.getElementById("quotation-save-label").textContent =
            "Save Quotation";
    }

    openPreviewLine(
        event.detail.cart ?? [],
        event.detail.totals ?? {},
        event.detail.factor ?? 1,
        event.detail.currency ?? "USD",
    );
});

window.addEventListener("load-quotation", (e) => {
    const detail = e.detail[0];
    fillQuotationModal(detail.header);
    openPreviewLine(
        detail.cart ?? [],
        detail.totals ?? {},
        detail.factor ?? 1,
        detail.currency ?? "USD",
    );
    showToast({
        message: detail.message,
        type: "success",
    });
});

window.addEventListener("quotation-saved", async (e) => {
    const message = e.detail[0].message;
    const quotationId = e.detail[0].id;
    document.getElementById("quotation_id").value = "";
    document.getElementById("quotation-customer-id").value = "";
    document.getElementById("quotation-customer-search").value = "";
    document.getElementById("quotation-customer-name").value = "";
    document.getElementById("quotation-customer-phone").value = "";
    document.getElementById("quotation-customer-address").value = "";
    document.getElementById("quotation-remark").value = "";
    closeQuotationModal();
    showToast({
        message: message,
        type: "success",
    });

    if (quotationId && (await askPrintConfirm("Print this quotation now?"))) {
        try {
            const res = await fetch(`/quotations/${quotationId}`);
            const data = await res.json();
            await printQuotationA4(data.header, data.lines, pos_profile_for_print);
        } catch (err) {
            console.error(err);
            showToast({ message: "Failed to print quotation", type: "error" });
        }
    }
});

function fillQuotationModal(header) {
    document.getElementById("quotation_id").value = header.id ?? "";
    document.getElementById("quotation-customer-id").value =
        header.customer_id ?? "";
    document.getElementById("quotation-customer-search").value =
        header.customer_name ?? "";
    document.getElementById("quotation-customer-name").value =
        header.customer_name ?? "";
    document.getElementById("quotation-customer-phone").value =
        header.phone ?? "";
    document.getElementById("quotation-customer-address").value =
        header.address ?? "";
    document.getElementById("quotation-remark").value = header.remarks ?? "";
    document.getElementById("quotation-modal-title").textContent =
        "Edit Quotation " + (header.quotation_no ?? "");
    document.getElementById("quotation-save-label").textContent =
        "Update Quotation";
}

function submitQuotation() {
    const quotationId = document.getElementById("quotation_id")?.value || "";
    const payload = {
        customer_id:
            document.getElementById("quotation-customer-id")?.value || "",
        customer_name:
            document.getElementById("quotation-customer-name")?.value ||
            "Walk-in Customer",
        customer_phone:
            document.getElementById("quotation-customer-phone")?.value || "",
        customer_address:
            document.getElementById("quotation-customer-address")?.value || "",
        remark: document.getElementById("quotation-remark")?.value || "",
    };

    if (quotationId) {
        Livewire.dispatch("updateQuotation", { payload });
    } else {
        Livewire.dispatch("saveQuotation", { payload });
    }
}

// Same customer fields as submitQuotation, but routed to a preview so nothing is
// written — the server builds the document and hands it straight back for
// printing. Which document depends on how the modal was opened: Quote previews
// the quotation being written, Preview previews the INVOICE the customer is
// about to be handed.
function previewQuotation() {
    const payload = {
        customer_name:
            document.getElementById("quotation-customer-name")?.value ||
            "Walk-in Customer",
        customer_phone:
            document.getElementById("quotation-customer-phone")?.value || "",
        customer_address:
            document.getElementById("quotation-customer-address")?.value || "",
        remark: document.getElementById("quotation-remark")?.value || "",
    };

    Livewire.dispatch(
        quotationModalIsPreviewOnly ? "previewInvoice" : "previewQuotation",
        { payload },
    );
}

// The profile to print a preview under is the signed-in user's: they are the one
// issuing it, and no document exists yet to resolve an issuer from.
function previewProfile() {
    return typeof pos_profile_for_print !== "undefined"
        ? pos_profile_for_print
        : (window.pos_profile_for_print ?? null);
}

window.addEventListener("quotation-preview", async (e) => {
    const detail = e.detail?.[0] ?? e.detail ?? {};
    try {
        await printQuotationA4(detail.header ?? {}, detail.lines ?? [], previewProfile());
    } catch (err) {
        console.error("Quotation preview failed:", err);
        showToast({
            message: `Failed to preview quotation — ${err?.name ?? "Error"}: ${err?.message ?? err}`,
            type: "error",
        });
    }
});

window.addEventListener("invoice-preview", async (e) => {
    const detail = e.detail?.[0] ?? e.detail ?? {};
    try {
        await printInvoiceA4(detail.header ?? {}, detail.lines ?? [], previewProfile());
    } catch (err) {
        console.error("Invoice preview failed:", err);
        showToast({
            message: `Failed to preview invoice — ${err?.name ?? "Error"}: ${err?.message ?? err}`,
            type: "error",
        });
    }
});

function closeQuotationModal() {
    const modal = document.getElementById("quotationModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

/* ===================== Quotations List ===================== */

function openQuotationListModal() {
    const modal = document.getElementById("quotationListModal");
    if (!modal) return;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    loadQuotations(1);
}

function closeQuotationListModal() {
    const modal = document.getElementById("quotationListModal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function clearQuotationFilters() {
    ["quotation_document_search", "quotation_search"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
    ["quotation_status", "quotation_from_date", "quotation_to_date"].forEach(
        (id) => {
            const el = document.getElementById(id);
            if (el) el.value = "";
        },
    );
    loadQuotations(1);
}

[
    "quotation_document_search",
    "quotation_search",
    "quotation_status",
    "quotation_from_date",
    "quotation_to_date",
].forEach((id) => {
    document.getElementById(id)?.addEventListener("input", () => {
        loadQuotations(1);
    });
    document.getElementById(id)?.addEventListener("change", () => {
        loadQuotations(1);
    });
});

let selectedQuotationId = null;

function loadQuotations(page = 1) {
    const search = document.getElementById("quotation_search")?.value || "";
    const search_document =
        document.getElementById("quotation_document_search")?.value || "";
    const status = document.getElementById("quotation_status")?.value || "";
    const from_date =
        document.getElementById("quotation_from_date")?.value || "";
    const to_date = document.getElementById("quotation_to_date")?.value || "";

    const params = new URLSearchParams({
        page: page,
        search: search,
        search_document: search_document,
        status: status,
        from_date: from_date,
        to_date: to_date,
    });

    fetch(`/quotations?${params.toString()}`)
        .then((res) => res.json())
        .then((res) => {
            const tbody = document.getElementById("Table-quotation-list");
            tbody.innerHTML = "";

            if (!res.data || res.data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-14 text-slate-400">
                            <i class="fa-solid fa-file-circle-question text-3xl mb-2 block"></i>
                            No quotations found
                        </td>
                    </tr>`;
                document.getElementById("quotation-pagination").innerHTML = "";
                return;
            }

            res.data.forEach((row, index) => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-slate-50 cursor-pointer transition";
                tr.dataset.id = row.id;
                tr.onclick = () => {
                    selectedQuotationId = row.id;
                    document
                        .querySelectorAll("#Table-quotation-list tr")
                        .forEach((r) => r.classList.remove("bg-teal-50"));
                    tr.classList.add("bg-teal-50");
                };
                tr.innerHTML = `
                    <td class="px-4 py-3 text-slate-500">${index + 1 + (res.current_page - 1) * res.per_page}</td>
                    <td class="px-4 py-3 font-semibold text-slate-800">${row.quotation_no ?? ""}</td>
                    <td class="px-4 py-3 text-center text-slate-600">${row.quotation_date ?? ""}</td>
                    <td class="px-4 py-3 text-slate-700">${row.customer_name ?? ""}</td>
                    <td class="px-4 py-3 text-slate-600">${row.phone ?? ""}</td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800">${listMoney(row.grand_total, row.factor)}</td>
                    <td class="px-4 py-3 text-center">${getStatusBadge(row.status)}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <button onclick="event.stopPropagation(); printQuotationById(${row.id})"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-900 text-white text-xs font-semibold mr-1 transition">
                            <i class="fa-solid fa-print"></i> Print
                        </button>
                        ${
                            row.status === "Quotation"
                                ? `<button onclick="event.stopPropagation(); loadQuotationToCartUI(${row.id})"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-sky-500 hover:bg-sky-600 text-white text-xs font-semibold mr-1 transition">
                                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Load
                                </button>
                                <button onclick="event.stopPropagation(); cancelQuotation(${row.id})"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-500 hover:bg-red-600 text-white text-xs font-semibold transition">
                                    <i class="fa-solid fa-ban"></i> Cancel
                                </button>`
                                : ""
                        }
                    </td>
                `;
                tbody.appendChild(tr);
            });

            const pag = document.getElementById("quotation-pagination");
            pag.textContent = `Page ${res.current_page} of ${res.last_page} | Total ${res.total}`;
        })
        .catch((err) => {
            console.error(err);
            showToast({ message: "Failed to load quotations", type: "error" });
        });
}

async function printQuotationById(id) {
    try {
        const res = await fetch(`/quotations/${id}`);
        if (!res.ok) throw new Error("Failed to load quotation");
        const data = await res.json();
        await printQuotationA4(data.header, data.lines, pos_profile_for_print);
    } catch (err) {
        console.error(err);
        showToast({ message: "Failed to print quotation", type: "error" });
    }
}

function loadQuotationToCartUI(id) {
    const input_count_cart = document.getElementById("count_cart_input");
    const count_cart = input_count_cart ? input_count_cart.value : 0;
    if (count_cart > 0) {
        showToast({
            message: "Cart is not empty. Clear or finish the current cart first.",
            type: "error",
        });
        return;
    }

    Livewire.dispatch("load-quotation-to-cart", { quotationId: id });
    closeQuotationListModal();
}

function cancelQuotation(id) {
    if (!confirm("Cancel this quotation?")) return;

    fetch("/quotations/update-status", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                .value,
            Accept: "application/json",
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            quotation_id: id,
            status: "Cancelled",
        }),
    })
        .then((res) => res.json())
        .then((data) => {
            if (!data.success) throw new Error(data.message || "Update failed");
            showToast({ message: "Quotation cancelled", type: "success" });
            loadQuotations(1);
        })
        .catch((err) => {
            showToast({
                message: err.message || "Failed to cancel quotation",
                type: "error",
            });
        });
}

function openPreviewLine(cart = [], totals = {}, factor = 1, currency = "USD") {
    const modal = document.getElementById("quotationModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    let html = "";

    cart.forEach((item, index) => {
        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">${index + 1}</td>
                <td class="px-4 py-3">${item.code ?? item.item_code ?? ""}</td>
                <td class="px-4 py-3 font-medium text-gray-800">${item.name ?? ""}</td>
                <td class="px-4 py-3 text-right">${formatQty(item.qty ?? item.quantity ?? 0)}</td>
                <td class="px-4 py-3 text-right">${formatCurrency(item.price ?? item.sell_price ?? 0, factor, currency)} ${currency}</td>
                <td class="px-4 py-3 text-right">${formatCurrency(item.discount_amount ?? 0, factor, currency)} ${currency}</td>
                <td class="px-4 py-3 text-right">${formatCurrency(item.vat_amount ?? 0, factor, currency)} ${currency}</td>
                <td class="px-4 py-3 text-right font-bold text-blue-600">
                    ${formatCurrency(item.grand_total_amount ?? item.net_amount_line ?? 0, factor, currency)} ${currency}
                </td>
            </tr>
        `;
    });

    document.getElementById("preview-line-data").innerHTML = html;

    document.getElementById("preview-total").textContent =
        `${formatCurrency(totals.total_amount ?? 0, factor, currency)} ${currency}`;

    document.getElementById("preview-discount").textContent =
        `${formatCurrency(totals.discount_amount ?? 0, factor, currency)} ${currency}`;

    document.getElementById("preview-vat").textContent =
        `${formatCurrency(totals.vat_amount ?? 0, factor, currency)} ${currency}`;

    document.getElementById("preview-grand").textContent =
        `${formatCurrency(totals.grand_total ?? 0, factor, currency)} ${currency}`;
}

function getSelectedCurrency() {
    const select = document.getElementById("currency_select");

    if (select && select.selectedOptions.length > 0) {
        const option = select.selectedOptions[0];

        const factor = Number(option.dataset.factor || 1);
        const currency = option.value || "USD";

        return { factor, currency };
    }

    if (window.previewCurrency) {
        return window.previewCurrency;
    }

    return { factor: 1, currency: "USD" };
}
function setStatusColor(id, status) {
    const el = document.getElementById(id);
    if (!el) return;

    let className = "bg-gray-100 text-gray-700";

    switch (status) {
        case "unpaid":
            className = "bg-red-100 text-red-700";
            break;
        case "partial":
            className = "bg-yellow-100 text-yellow-700";
            break;
        case "paid":
            className = "bg-green-100 text-green-700";
            break;
        case "N/A":
            className = "bg-gray-100 text-gray-700";
            break;
    }

    el.className = `px-3 py-1 rounded-full text-sm font-medium ${className}`;
}


/**
 * Main button click
 */

/**
 * Cancel modal
 */
function cancelEmptyCustomerConfirm() {
    pendingSaleOrderStatus = null;
    pendingSaleOrderButton = null;

    document
        .getElementById("confirm_customer_empty_modal")
        .classList.add("hidden");
}

/**
 * Continue from modal
 */
function continueEmptyCustomerConfirm() {
    document
        .getElementById("confirm_customer_empty_modal")
        .classList.add("hidden");

    submitSaleOrder(pendingSaleOrderStatus, pendingSaleOrderButton);

    pendingSaleOrderStatus = null;
    pendingSaleOrderButton = null;
}

/**
 * REAL submit logic
 */
function submitSaleOrder(document_status, btn = null) {
    // prevent spam click
    if (isSavingSaleOrder) {
        return;
    }

    isSavingSaleOrder = true;

    // disable clicked button
    if (btn) {
        btn.disabled = true;

        btn.classList.add("opacity-50", "pointer-events-none");
    }

    try {
        const totalAmount = parseFloat(
            document.querySelector("#total_amount")?.value || 0,
        );

        const payUSD = parseFloat(
            document.getElementById("so_pay_usd")?.value || 0,
        );

        const payOther = parseFloat(
            document.getElementById("so_pay_other")?.value || 0,
        );

        const factor = rielFactor();

        const paidAmount = payUSD + payOther / factor;

        let paymentData = {
            // Dates
            document_date:
                document.getElementById("so_document_dateInput")?.value ?? null,

            order_date:
                document.getElementById("so_order_dateInput")?.value ?? null,

            delivery_date:
                document.getElementById("so_delivery_dateInput")?.value ?? null,

            // Payment
            paymentMethod:
                document.getElementById("so_payment_method")?.value ?? null,

            paid_amount: paidAmount,
            deposit_amount: paidAmount,
            total_amount: totalAmount,
            // Bill discount — previously only used for the on-screen
            // "remaining" preview and never actually sent to the server.
            bill_discount: parseFloat(
                document.getElementById("so_invoice_discount")?.value || 0,
            ),

            // Customer
            customer_type:
                document.getElementById("so_customer_type")?.value ?? null,

            customer_id:
                document.getElementById("so_customer_id_info")?.value ?? null,

            customer_name:
                document.getElementById("so_customer_name_info")?.value ?? null,

            customer_phone:
                document.getElementById("so_customer_phone_info")?.value ??
                null,

            customer_address:
                document.getElementById("so_customer_address_info")?.value ??
                null,

            // Delivery
            delivery_status:
                document.getElementById("so_delivery_status")?.value ?? null,

            delivery_info:
                document.getElementById("so_delivery_info_status")?.value ??
                null,

            driver_name:
                document.getElementById("so_driver_name")?.value ?? null,

            driver_phone:
                document.getElementById("so_driver_phone")?.value ?? null,

            status: document_status,

            // Optional
            remark: document.getElementById("so_remark_invoice")?.value ?? null,
        };

        console.log("Sale Order Payload:", paymentData);

        // Dispatch
        if (document_status == "Deposit") {
            Livewire.dispatch("confirmDepositSaleOrder", [paymentData]);
        } else if (document_status == "Update-Deposit") {
            Livewire.dispatch("updateDepositSaleOrder", [paymentData]);
        } else if (document_status == "Quotation") {
            Livewire.dispatch("confirmSaleOrder", [paymentData]);
        } else if (document_status == "Ordered") {
            Livewire.dispatch("confirmSaleOrder", [paymentData]);
        } else if (document_status == "Completed") {
            Livewire.dispatch("confirmSaleOrderPaid", [paymentData]);
        }

        // reset form
        document.getElementById("so_customer_type").value = "Take-Away";

        document.getElementById("so_delivery_dateInput").value = "";

        document.getElementById("so_delivery_status").value = "N/A";

        document.getElementById("so_delivery_info_status").value = "";

        document.getElementById("so_driver_name").value = "";

        document.getElementById("so_driver_phone").value = "";

        // hide delivery
        document.getElementById("delivery_section").classList.add("hidden");

        // close modal
        document
            .getElementById("default-modal-sales-order-save")
            .classList.add("hidden");
    } catch (e) {
        console.error(e);

        showToast({
            message: "Failed to submit sale order",
            type: "error",
        });
    } finally {
        // unlock button
        setTimeout(() => {
            isSavingSaleOrder = false;

            if (btn) {
                btn.disabled = false;

                btn.classList.remove("opacity-50", "pointer-events-none");
            }
        }, 1500);
    }
}
window.addEventListener("load-sale-order", (e) => {
    const message = e.detail[0].message;
    fillSaleOrderModal(e.detail[0].header);
    showToast({
        message: message,
        type: "success",
    });
});
window.addEventListener("expense_item_prevented", (e) => {
    const message = e.detail[0].message;

    showToast({
        message: message,
        type: "error",
    });
});
window.addEventListener("product_item_prevented", (e) => {
    const message = e.detail[0].message;

    showToast({
        message: message,
        type: "error",
    });
});
window.addEventListener("update-fail", (e) => {
    const message = e.detail[0].message;

    showToast({
        message: message,
        type: "error",
    });
});

window.addEventListener("change-document-type", (e) => {
    const document_type = e.detail[0].document;
    const payment_status = e.detail[0].payment_status;

    // Tailwind's .hidden class only, never inline style — mixing the two
    // was leaving buttons in an inconsistent box-model state (no gap
    // between them) whenever a style.display value outlived a class change.
    const show = (id, visible) => {
        const el = document.querySelector(id);
        if (el) el.classList.toggle("hidden", !visible);
    };

    show("#buttone_update_deposit", false);
    show("#save_as_order", false);
    show("#btn_ship_only", false);

    if (document_type == "Deposit") {
        // Stock already shipped — this is topping up more payment.
        show("#buttone_update_deposit", true);
    } else if (document_type == "Ordered") {
        // Nothing shipped yet — Ship is always relevant regardless of
        // payment_status (goods can go out before, with, or after payment).
        show("#btn_ship_only", true);
        // Ship & Pay only makes sense while there's still money owed —
        // once fully Paid, only shipping is left to do. Only two paths:
        // Ship now and Pay later, or Ship & Pay together.
        if (payment_status !== "Paid") {
            show("#save_as_order", true);
        }
    }
    // Quotation/Cancelled/Completed/Returned — already closed or not
    // payable here, leave everything hidden (the show(..., false) calls above).
});

function shipOnlySelectedOrder() {
    Livewire.dispatch("ship-sale-order");
}

function fillSaleOrderModal(data = {}) {
    document.getElementById("so_sale_order_id").value = data.id ?? "";

    document.getElementById("so_document_dateInput").value =
        data.posting_date ?? data.document_date ?? "";

    document.getElementById("so_order_dateInput").value = data.order_date ?? "";
    document.getElementById("so_delivery_dateInput").value =
        data.delivery_date ?? "";

    const factor = rielFactor();
    const currency =
        document.querySelector("#currency_display_symbol")?.value || "$";

    const grandTotal =
        parseFloat(data.grand_total ?? data.total_amount ?? 0) || 0;

    // Snap to the 100៛ grid and group the thousands, the same as the new-order
    // path — this used to print the raw product, e.g. "623478 ៛".
    const convertedTotal = roundDisplay(grandTotal * factor, currency);

    document.getElementById("so_payment_method").value =
        data.payment_method ?? "ABA";

    const paidAmount = parseFloat(data.paid_amount ?? 0) || 0;
    const paidEl = document.getElementById("paid_amount");
    // The exact figure drives the arithmetic, the rounded one is what's read on
    // screen — keeping both stops "150.000000" showing while still balancing to
    // the stored precision.
    paidEl.dataset.amount = paidAmount;
    paidEl.value = paidAmount.toFixed(2);

    document.getElementById("so_pay_usd").value = 0;
    document.getElementById("so_pay_other").value = 0;

    // updateSaleOrderRemaining falls back to #so_display_pay_amount when
    // #total_amount is empty, and loading a saved order never filled it — so
    // the formatted 2-dp value became the amount owed and lost the fractional
    // riel. Publish the exact total here and format only what's displayed.
    const totalEl = document.querySelector("#total_amount");
    if (totalEl) totalEl.value = grandTotal;

    const dueEl = document.getElementById("so_display_pay_amount");
    dueEl.dataset.amount = grandTotal;
    dueEl.value = grandTotal.toFixed(2) + " $";

    const dueCvtEl = document.getElementById("so_display_pay_amount_converted");
    dueCvtEl.dataset.amount = convertedTotal;
    dueCvtEl.value = convertedTotal.toLocaleString("en-US") + " " + currency;

    document.getElementById("so_customer_type").value =
        data.customer_type ?? "Take-Away";
    document.getElementById("so_customer_id_info").value =
        data.customer_id ?? "";
    document.getElementById("so_customer_name_info").value =
        data.contact_name ?? data.customer_name ?? "";
    document.getElementById("so_customer_phone_info").value =
        data.phone ?? data.customer_phone ?? "";
    document.getElementById("so_customer_address_info").value =
        data.address ?? data.customer_address ?? "";

    if (document.querySelector("#customerSearch")) {
        document.querySelector("#customerSearch").value =
            data.contact_name ?? data.customer_name ?? "";
    }
    document.getElementById("so_delivery_status").value = data.delivery_status;
    document.getElementById("so_delivery_info_status").value =
        data.delivery_info ?? "";
    document.getElementById("so_driver_name").value = data.driver_name ?? "";
    document.getElementById("so_driver_phone").value = data.driver_phone ?? "";

    document.getElementById("so_remark_invoice").value =
        data.remarks ?? data.remark ?? "";

    if (data.customer_type === "At-Delivery") {
        document.getElementById("delivery_section").classList.remove("hidden");
    } else {
        document.getElementById("delivery_section").classList.add("hidden");
    }

    if (typeof validateSaleOrderPayment === "function") {
        validateSaleOrderPayment();
    }
    document
        .getElementById("default-modal-sales-order-save")
        .classList.remove("hidden");

    document.querySelector("#new_order").style.display = "none";
    document.querySelector("#update_order").style.display = "block";
}

function update_sale_order() {
    validateSaleOrderPayment();
    document
        .getElementById("default-modal-sales-order-save")
        .classList.remove("hidden");
}

function Confirm_update_Sale_Order() {
    let hidden_input_document_type = document.querySelector("#document_type");

    if (hidden_input_document_type.value === "Quotation") {
        showToast({
            message: `${hidden_input_document_type.value} មិនអាចបង់ប្រាក់បានទេ។`,
            type: "warning",
        });
        return;
    }

    const factor = rielFactor();

    const payUSD = parseFloat(
        document.getElementById("so_pay_usd")?.value || 0,
    );
    const payOther = parseFloat(
        document.getElementById("so_pay_other")?.value || 0,
    );

    const newPaidAmount = payUSD + payOther / factor;
    const billDiscount = parseFloat(document.getElementById("so_invoice_discount")?.value || 0);

    // A discount alone (writing off the balance with no cash) is a valid
    // way to close this out — only block when there's neither payment nor
    // a discount entered at all.
    if (newPaidAmount <= 0 && billDiscount <= 0) {
        showToast({
            message: "សូមបង់ប្រាក់ជាមុនសិន.",
            type: "error",
        });
        return;
    }

    let paymentData = {
        sale_order_id:
            document.getElementById("so_sale_order_id")?.value ?? null,

        // Dates
        document_date:
            document.getElementById("so_document_dateInput")?.value ?? null,
        order_date:
            document.getElementById("so_order_dateInput")?.value ?? null,
        delivery_date:
            document.getElementById("so_delivery_dateInput")?.value ?? null,

        // Payment
        paymentMethod:
            document.getElementById("so_payment_method")?.value ?? null,
        // must match what Cart::updateDepositSaleOrder() reads
        deposit_amount: newPaidAmount,
        bill_discount: parseFloat(
            document.getElementById("so_invoice_discount")?.value || 0,
        ),

        // Customer
        customer_type:
            document.getElementById("so_customer_type")?.value ?? null,
        customer_id:
            document.getElementById("so_customer_id_info")?.value ?? null,
        customer_name:
            document.getElementById("so_customer_name_info")?.value ?? null,
        customer_phone:
            document.getElementById("so_customer_phone_info")?.value ?? null,
        customer_address:
            document.getElementById("so_customer_address_info")?.value ?? null,

        // Delivery
        delivery_status:
            document.getElementById("so_delivery_status")?.value ?? null,
        delivery_info:
            document.getElementById("so_delivery_info_status")?.value ?? null,
        driver_name: document.getElementById("so_driver_name")?.value ?? null,
        driver_phone: document.getElementById("so_driver_phone")?.value ?? null,

        // Remark
        remark: document.getElementById("so_remark_invoice")?.value ?? null,
    };

    document
        .getElementById("default-modal-sales-order-save")
        .classList.add("hidden");

    // must match Cart::updateDepositSaleOrder()'s #[On('updateDepositSaleOrder')]
    Livewire.dispatch("updateDepositSaleOrder", [paymentData]);
}
function openExpenseModal() {
    document.getElementById("expenseModal").classList.remove("hidden");

    const today = todayLocal();

    document.getElementById("expenseDate").value = today;
}

function closeExpenseModal() {
    document.getElementById("expenseModal").classList.add("hidden");
}

function confirmExpensePayment() {
    const selectedDate = document.getElementById("expenseDate").value;

    if (!selectedDate) {
        alert("Please select expense date");
        return;
    }

    pay_expense({ document_date: selectedDate });

    closeExpenseModal();
}

function pay_expense(payload) {
    Livewire.dispatch("payment-expenses", {
        payload: payload,
    });
}

// display rules per exchange factor — mirror of the Blade $step/$decimal
function currencyRules(factor) {
    factor = Number(factor) || 1;
    if (factor === 1) return { decimal: 2, step: 0, thousands: false }; // USD
    if (factor >= 4000) return { decimal: 0, step: 100, thousands: true }; // KHR → 4,050,450
    if (factor >= 100) return { decimal: 3, step: 0, thousands: false }; // mid-rate
    return { decimal: 2, step: 0, thousands: false };
}

// round a BASE (USD) per-unit value into stepped display currency
function unitDisp(baseUnit, factor, rules) {
    const v = (Number(baseUnit) || 0) * factor;
    return rules.step > 0
        ? Math.round(v / rules.step) * rules.step
        : Number(v.toFixed(rules.decimal));
}

// format a FINAL display-currency amount (no step rounding), grouped for KHR
function money(value, rules) {
    return (Number(value) || 0).toLocaleString("en-US", {
        minimumFractionDigits: 0,
        maximumFractionDigits: rules.decimal,
        useGrouping: rules.thousands,
    });
}

// WYSIWYG line: stepped unit × qty
function lineMoney(baseUnit, qty, factor, rules) {
    return money(unitDisp(baseUnit, factor, rules) * (Number(qty) || 0), rules);
}



function exportProducts() {
    const params = new URLSearchParams({
        search: document.getElementById('ProductSearchInput')?.value || '',
        status: document.getElementById('productSearchCheckbox')?.value || '',
        type:   document.getElementById('productTypeSelect')?.value || '',
        images: document.getElementById('exportWithImages')?.checked ? '1' : '0',
    });
    window.location.href = '/products/export-excel?' + params.toString();
}
