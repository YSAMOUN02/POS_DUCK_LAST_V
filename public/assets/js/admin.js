if(user_role === "admin" ){

    
    let userBtn = document.getElementById("user_data");
    userBtn.addEventListener("click", fetchUsers);
    // document.getElementById("user_data").addEventListener("click", fetchUsers);
    document
        .getElementById("userSearchInput")
        .addEventListener("input", fetchUsers);
    document
        .getElementById("role_filter")
        .addEventListener("change", fetchUsers);
    document.getElementById("active").addEventListener("change", fetchUsers);
    // Trigger fetch when modal opens
    document
        .getElementById("default-modal-user-list")
        .addEventListener("transitionend", function () {
            if (!this.classList.contains("hidden")) {
                fetchUsers();
            }
        });

    document
        .getElementById("btnUser")
        .addEventListener("click", loadWarehouses_user);
    document
        .getElementById("btnUser")
        .addEventListener("click", () => loadPermissions("permissionList", []));

    async function fetchUsers() {
        const search = document.getElementById("userSearchInput").value.trim();
        const role = document.getElementById("role_filter").value;
        const active = document.getElementById("active").value;

        const params = new URLSearchParams({ search, role, active });

        try {
            const res = await fetch(`/users-list-data?${params.toString()}`);
            const users = await res.json();
            renderUsers(users);
        } catch (err) {
            console.error(err);
        }
    }
    function renderUsers(users) {
        const tbody = document.getElementById("user-table-body");
        tbody.innerHTML = "";

        if (!users.length) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-gray-500">No users found</td></tr>`;
            return;
        }

        users.forEach((user) => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
            <td class="px-4 py-2 text-center">
                <input type="radio" name="selectUser" value="${user.id}" onclick="selectUserRow(${user.id})">
            </td>
            <td class="px-4 py-2">${user.id}</td>
            <td class="px-4 py-2">${user.name}</td>
            <td class="px-4 py-2">${user.email}</td>
            <td class="px-4 py-2">${user.phone || "-"}</td>
            <td class="px-4 py-2">${user.role}</td>

             <td class="px-3      text-sm ${user.status ? "text-green-600" : "text-red-500"}">
                    ${user.status ? "Active" : "Inactive"}
                </td>
        `;

            tbody.appendChild(tr);
        });
    }

    const displayName = document.getElementById("display_name");
    const username = document.getElementById("username");
    const role = document.getElementById("role");
    const email = document.getElementById("email");
    const submitBtn = document.getElementById("submitBtn");

    const password = document.getElementById("password");
    const formError = document.getElementById("formError");

    // warehouse checkbox change (IMPORTANT)
    displayName.addEventListener("input", validateForm);
    username.addEventListener("input", validateForm);
    role.addEventListener("change", validateForm);
    email.addEventListener("input", validateForm);

    function validateForm() {
        let errors = [];

        const nameValid = displayName.value.trim() !== "";
        const userValid = username.value.trim() !== "";
        const roleValid = role.value !== "";

        const emailValue = email.value.trim();
        const isEmailFormatValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            emailValue,
        );

        const passwordValid = password.value.trim() !== "";

        const warehouseChecked =
            document.querySelectorAll('input[name="warehouses[]"]:checked')
                .length > 0;

        // 🔴 Collect errors
        if (!nameValid) errors.push("Display name is required");
        if (!userValid) errors.push("Username is required");
        if (!roleValid) errors.push("Role is required");
        if (!passwordValid) errors.push("Password is required");
        if (!warehouseChecked) errors.push("Select at least 1 warehouse");
        if (emailValue !== "" && !isEmailFormatValid) {
            errors.push("Email format is invalid");
        }

        // 🔥 Show ALL messages in ONE place
        if (errors.length > 0) {
            formError.classList.remove("hidden");
            formError.innerHTML = errors.join("<br>");
        } else {
            formError.classList.add("hidden");
            formError.innerHTML = "";
        }

        // ✅ Enable / disable button
        if (errors.length === 0) {
            submitBtn.disabled = false;
            submitBtn.classList.remove("bg-gray-400", "cursor-not-allowed");
            submitBtn.classList.add("bg-green-500", "text-white");
            submitBtn.textContent = "Create User";
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add("bg-gray-400", "cursor-not-allowed");
            submitBtn.textContent = "Required More Info";
        }
    }

    document.addEventListener("change", function (e) {
        if (e.target.name === "warehouses[]") {
            validateForm();
        }
    });

    async function loadWarehouses_user() {
        try {
            const res = await fetch("/warehouse-list-data");
            const data = await res.json();

            const container = document.getElementById("warehouseList");
            container.innerHTML = "";

            if (!data.length) {
                container.innerHTML =
                    "<p class='text-gray-500'>No warehouse found</p>";
                return;
            }

            data.forEach((w) => {
                const div = document.createElement("label");
                div.className = `
        flex items-center gap-3 p-3 rounded-lg border border-gray-300
        cursor-pointer transition-all duration-150
        hover:border-amber-500 hover:bg-amber-50
    `;

                div.innerHTML = `
        <input type="checkbox" name="warehouses[]" value="${w.id}"
            class="w-4 h-4 accent-amber-500">

        <span class="text-sm font-medium text-gray-700">
            ${w.name}
        </span>
    `;

                // active style when checked
                const checkbox = div.querySelector("input");
                checkbox.addEventListener("change", () => {
                    if (checkbox.checked) {
                        div.classList.add("border-amber-500", "bg-amber-100");
                    } else {
                        div.classList.remove(
                            "border-amber-500",
                            "bg-amber-100",
                        );
                    }
                });

                container.appendChild(div);
            });
        } catch (err) {
            console.error("Error loading warehouses:", err);
        }
    }

    document
        .getElementById("AddUserForm")
        .addEventListener("submit", async function (e) {
            e.preventDefault();

            reconcilePermissionsIntoForm();

            const form = e.target;
            const formData = new FormData(form);

            try {
                const res = await fetch("/users/store", {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector(
                            'input[name="_token"]',
                        ).value,
                    },
                });

                const data = await res.json();

                if (data.success) {
                    showToast({
                        message: data.message || "User created successfully ✅",
                        type: "success",
                    });
                    form.reset();
                } else {
                    showToast({
                        message: data.message || "Failed to create user ❌",
                        type: "error",
                    });
                }
            } catch (err) {
                console.error(err);

                showToast({
                    message: "Error",
                    type: "error",
                });
            }
        });

    // ------------------------
    // Update User
    // ------------------------
    let selectedUserId = null;

    function selectUserRow(id) {
        selectedUserId = id;
    }

    function closeEditUserModal() {
        document.getElementById("default-modal-edit-user").classList.add("hidden");
        document.getElementById("default-modal-edit-user").classList.remove("flex");
    }

    async function loadWarehouses_edit(checkedIds = []) {
        try {
            const res = await fetch("/warehouse-list-data");
            const data = await res.json();

            const container = document.getElementById("editWarehouseList");
            container.innerHTML = "";

            if (!data.length) {
                container.innerHTML =
                    "<p class='text-gray-500'>No warehouse found</p>";
                return;
            }

            const checkedSet = new Set(checkedIds.map((id) => String(id)));

            data.forEach((w) => {
                const isChecked = checkedSet.has(String(w.id));
                const div = document.createElement("label");
                div.className = `
        flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-150
        hover:border-amber-500 hover:bg-amber-50
        ${isChecked ? "border-amber-500 bg-amber-100" : "border-gray-300"}
    `;

                div.innerHTML = `
        <input type="checkbox" name="warehouses[]" value="${w.id}" ${isChecked ? "checked" : ""}
            class="w-4 h-4 accent-amber-500">

        <span class="text-sm font-medium text-gray-700">
            ${w.name}
        </span>
    `;

                const checkbox = div.querySelector("input");
                checkbox.addEventListener("change", () => {
                    if (checkbox.checked) {
                        div.classList.add("border-amber-500", "bg-amber-100");
                    } else {
                        div.classList.remove("border-amber-500", "bg-amber-100");
                    }
                });

                container.appendChild(div);
            });
        } catch (err) {
            console.error("Error loading warehouses:", err);
        }
    }

    const PERMISSION_SECTION_ICONS = {
        pos_sale: "fa-cash-register",
        warehouse: "fa-warehouse",
        product: "fa-box",
        category: "fa-tags",
        quotation: "fa-file-contract",
        customer: "fa-users",
        purchasing: "fa-basket-shopping",
        vendor: "fa-truck-field",
        exchange_rate: "fa-money-bill-transfer",
        user: "fa-user-shield",
        company_profile: "fa-building",
        report: "fa-chart-line",
    };
    const PERMISSION_ACTION_LABELS = {
        view: "View",
        create: "Create",
        edit: "Edit",
        delete: "Delete",
        sell: "Sell",
        edit_price: "Edit Price",
        edit_discount: "Edit Discount",
        purchase: "Purchase",
        purchase_return: "Purchase Return",
        dashboard: "Dashboard / KPI",
        sales: "Sales Report",
        expense: "Expense Report",
        stock: "Stock In/Out",
        view_grid: "Product View: Grid",
        view_list: "Product View: List",
        adjustment: "Stock Adjustment",
        transfer: "Transfer (Different Warehouse)",
        movement: "Movement (Between Bins)",
    };

    // Permission key pairs that can never both be granted to the same user —
    // checking one auto-unchecks the other (POS product picker is either
    // Grid or List, never both, decided per-user by whoever holds this).
    const EXCLUSIVE_PERMISSION_GROUPS = [
        ["pos_sale.view_grid", "pos_sale.view_list"],
    ];

    function refreshPermissionPillStyle(input) {
        const pill = input.closest("label");
        if (!pill) return;
        pill.classList.toggle("border-amber-400", input.checked);
        pill.classList.toggle("bg-amber-50", input.checked);
        pill.classList.toggle("text-amber-700", input.checked);
        pill.classList.toggle("border-gray-200", !input.checked);
        pill.classList.toggle("text-gray-500", !input.checked);
    }

    function syncPermissionCardHeader(card) {
        const selectAll = card.querySelector('input[data-role="section-all"]');
        const children = card.querySelectorAll('input[name="permissions[]"]');
        const checkedCount = Array.from(children).filter((c) => c.checked).length;
        selectAll.checked = checkedCount === children.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < children.length;

        const badge = card.querySelector('[data-role="section-badge"]');
        if (badge) badge.textContent = `${checkedCount}/${children.length}`;
        card.classList.toggle("border-amber-300", checkedCount > 0);
        card.classList.toggle("border-gray-200", checkedCount === 0);
    }

    function permissionSectionLabel(section) {
        return section.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function permissionActionLabel(p) {
        return PERMISSION_ACTION_LABELS[p.action] || p.action;
    }

    // containerEl here is always #permissionModalGrid — the id suffix in
    // element ids just needs to be unique per render, not tied to a form.
    function buildPermissionSection(section, rows, checkedIds, containerEl) {
        const wrap = document.createElement("div");
        wrap.dataset.section = section;
        wrap.className = "border border-gray-200 rounded-xl p-4 bg-white shadow-sm hover:shadow-md transition-shadow";

        const headerId = `perm_all_${section}`;
        const sectionLabel = permissionSectionLabel(section);
        const icon = PERMISSION_SECTION_ICONS[section] || "fa-circle-dot";

        wrap.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2 font-semibold text-sm text-gray-800">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <i class="fa-solid ${icon}"></i>
                    </span>
                    <span>${sectionLabel}</span>
                    <span data-role="section-badge" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">0/0</span>
                </div>
                <label for="${headerId}" class="inline-flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer select-none">
                    <input type="checkbox" id="${headerId}" data-role="section-all" class="w-4 h-4 accent-amber-500">
                    All
                </label>
            </div>
            <div class="grid grid-cols-2 gap-2" data-role="section-children"></div>
        `;

        const childContainer = wrap.querySelector('[data-role="section-children"]');

        rows.forEach((p) => {
            const isChecked = checkedIds.includes(p.id);
            const pillId = `perm_${p.id}`;
            const label = document.createElement("label");
            label.setAttribute("for", pillId);
            label.title = `${p.label}  ·  ${p.key}`; // hover info: full label + raw permission key
            label.className = `flex items-center justify-center gap-1.5 rounded-lg border px-2 py-1.5 text-xs font-medium cursor-pointer transition-colors select-none ${
                isChecked ? "border-amber-400 bg-amber-50 text-amber-700" : "border-gray-200 text-gray-500 hover:border-gray-300"
            }`;
            label.innerHTML = `
                <input type="checkbox" id="${pillId}" name="permissions[]" value="${p.id}" ${isChecked ? "checked" : ""} class="hidden">
                ${permissionActionLabel(p)}
            `;
            childContainer.appendChild(label);
        });

        const selectAll = wrap.querySelector(`#${headerId}`);
        selectAll.addEventListener("change", () => {
            // Setting .checked programmatically doesn't fire "change", so the
            // delegated grid listener won't see these — update the shared
            // checked-ids state directly here instead.
            const allIds = Array.from(childContainer.querySelectorAll('input[name="permissions[]"]')).map((c) => Number(c.value));
            const idsToCheck = new Set(selectAll.checked ? dedupeExclusiveIds(allIds) : []);
            childContainer.querySelectorAll('input[name="permissions[]"]').forEach((c) => {
                const id = Number(c.value);
                const shouldCheck = idsToCheck.has(id);
                c.checked = shouldCheck;
                if (shouldCheck) permissionCheckedIds.add(id); else permissionCheckedIds.delete(id);
                refreshPermissionPillStyle(c);
            });
            syncPermissionCardHeader(wrap);
            updatePermissionModalCounter();
        });

        syncPermissionCardHeader(wrap);
        containerEl.appendChild(wrap);
    }

    // ------------------------
    // Manage Permissions modal (shared by Add + Edit User)
    // ------------------------
    // Source of truth is a plain Set of checked permission ids — both the
    // Card view and the List view render *from* it and write *to* it, so
    // switching views mid-edit never loses a change either way.
    let permissionsModalContext = null; // 'add' | 'edit'
    let permissionsData = null; // cached { section: [ {id,section,action,key,label}, ... ] }
    let permissionCheckedIds = new Set();
    let permissionViewMode = "card"; // 'card' | 'list'
    let permissionListSort = { key: "section", dir: "asc" };

    function permissionsSourceContainerId(context) {
        return context === "edit" ? "editPermissionList" : "permissionList";
    }

    function allPermissionRows() {
        return permissionsData ? Object.values(permissionsData).flat() : [];
    }

    function permissionRowById(id) {
        return allPermissionRows().find((p) => p.id === id);
    }

    // If `changedId` was just checked and belongs to an exclusive group,
    // uncheck any other currently-checked id in that same group. Returns
    // true if it actually removed something (caller uses this to decide
    // whether a full re-render is needed to reflect the auto-uncheck).
    function enforceExclusiveGroups(changedId) {
        const row = permissionRowById(changedId);
        if (!row) return false;
        const group = EXCLUSIVE_PERMISSION_GROUPS.find((g) => g.includes(row.key));
        if (!group) return false;
        let changed = false;
        allPermissionRows()
            .filter((p) => group.includes(p.key) && p.id !== changedId)
            .forEach((p) => {
                if (permissionCheckedIds.delete(p.id)) changed = true;
            });
        return changed;
    }

    // Given a list of ids about to be checked all at once (Select All), drop
    // every id after the first one encountered from each exclusive group so
    // the result never contains both sides of a pair.
    function dedupeExclusiveIds(ids) {
        const seenGroupIndex = new Set();
        const result = [];
        ids.forEach((id) => {
            const row = permissionRowById(id);
            const groupIdx = row ? EXCLUSIVE_PERMISSION_GROUPS.findIndex((g) => g.includes(row.key)) : -1;
            if (groupIdx === -1) {
                result.push(id);
                return;
            }
            if (seenGroupIndex.has(groupIdx)) return;
            seenGroupIndex.add(groupIdx);
            result.push(id);
        });
        return result;
    }

    function updatePermissionSummary(context) {
        const container = document.getElementById(permissionsSourceContainerId(context));
        const checked = container.querySelectorAll('input[name="permissions[]"]:checked').length;
        const summary = document.getElementById(`permissionSummary_${context}`);
        if (summary) summary.textContent = `${checked} selected`;
    }

    function updatePermissionModalCounter() {
        document.getElementById("permissionModalCounter").textContent =
            `${permissionCheckedIds.size} of ${allPermissionRows().length} selected`;
    }

    // Fetches + caches the full permission dataset and materializes the
    // *currently checked* ones as real <input> elements inside the hidden
    // form container (containerId) — that's the actual <form> submission
    // state; unchecked permissions don't need a DOM node at all.
    async function loadPermissions(containerId, checkedIds = []) {
        try {
            const res = await fetch("/permissions-list-data");
            permissionsData = await res.json();

            const container = document.getElementById(containerId);
            container.innerHTML = "";

            const ids = checkedIds.map((id) => Number(id));
            ids.forEach((id) => {
                const input = document.createElement("input");
                input.type = "checkbox";
                input.name = "permissions[]";
                input.value = id;
                input.checked = true;
                container.appendChild(input);
            });

            updatePermissionSummary(containerId === "permissionList" ? "add" : "edit");
        } catch (err) {
            console.error("Error loading permissions:", err);
        }
    }

    function renderPermissionCardView() {
        const grid = document.getElementById("permissionModalGrid");
        grid.innerHTML = "";
        Object.entries(permissionsData || {}).forEach(([section, rows]) => {
            buildPermissionSection(section, rows, Array.from(permissionCheckedIds), grid);
        });
        filterPermissionsActiveView();
    }

    function permissionListSortIndicator(key) {
        if (permissionListSort.key !== key) return "";
        return permissionListSort.dir === "asc" ? " ↑" : " ↓";
    }

    function renderPermissionListHeader() {
        document.querySelectorAll("#permissionModalList thead [data-sort]").forEach((th) => {
            const key = th.dataset.sort;
            th.textContent = th.dataset.label + permissionListSortIndicator(key);
        });
    }

    function renderPermissionListView() {
        const tbody = document.getElementById("permissionModalListBody");
        tbody.innerHTML = "";

        const term = document.getElementById("permissionSearchInput").value.trim().toLowerCase();
        let rows = allPermissionRows();
        if (term) {
            rows = rows.filter((p) =>
                p.section.replace(/_/g, " ").toLowerCase().includes(term) ||
                p.label.toLowerCase().includes(term) ||
                permissionActionLabel(p).toLowerCase().includes(term) ||
                p.key.toLowerCase().includes(term)
            );
        }

        const { key, dir } = permissionListSort;
        rows = rows.slice().sort((a, b) => {
            let av, bv;
            if (key === "granted") {
                av = permissionCheckedIds.has(a.id) ? 1 : 0;
                bv = permissionCheckedIds.has(b.id) ? 1 : 0;
            } else if (key === "section") {
                av = permissionSectionLabel(a.section);
                bv = permissionSectionLabel(b.section);
            } else {
                av = a.label;
                bv = b.label;
            }
            if (av < bv) return dir === "asc" ? -1 : 1;
            if (av > bv) return dir === "asc" ? 1 : -1;
            return 0;
        });

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="3" class="px-3 py-6 text-center text-gray-400">No permissions match your search</td></tr>`;
        }

        rows.forEach((p) => {
            const isChecked = permissionCheckedIds.has(p.id);
            const tr = document.createElement("tr");
            tr.className = "border-b border-gray-100 hover:bg-amber-50 transition-colors";
            tr.title = p.key; // hover info: raw permission key on the whole row
            tr.innerHTML = `
                <td class="px-3 py-2 text-gray-500">${permissionSectionLabel(p.section)}</td>
                <td class="px-3 py-2 font-medium text-gray-800">${p.label}</td>
                <td class="px-3 py-2 text-center">
                    <input type="checkbox" name="permissions[]" value="${p.id}" class="w-4 h-4 accent-amber-500" ${isChecked ? "checked" : ""}>
                </td>
            `;
            tbody.appendChild(tr);
        });

        renderPermissionListHeader();
    }

    function filterPermissionsActiveView() {
        if (permissionViewMode === "list") {
            renderPermissionListView();
            return;
        }
        const term = document.getElementById("permissionSearchInput").value.trim().toLowerCase();
        document.querySelectorAll("#permissionModalGrid > [data-section]").forEach((card) => {
            const sectionMatches = !term || permissionSectionLabel(card.dataset.section).toLowerCase().includes(term);
            let anyChildMatches = false;
            card.querySelectorAll('[data-role="section-children"] label').forEach((pill) => {
                const input = pill.querySelector('input[name="permissions[]"]');
                const row = input ? permissionRowById(Number(input.value)) : null;
                const pillMatches = sectionMatches || !row || row.label.toLowerCase().includes(term) ||
                    permissionActionLabel(row).toLowerCase().includes(term) || row.key.toLowerCase().includes(term);
                pill.classList.toggle("hidden", !pillMatches);
                if (pillMatches) anyChildMatches = true;
            });
            card.classList.toggle("hidden", !(sectionMatches || anyChildMatches));
        });
    }

    function setPermissionViewMode(mode) {
        permissionViewMode = mode;
        document.getElementById("permissionModalGrid").classList.toggle("hidden", mode !== "card");
        document.getElementById("permissionModalList").classList.toggle("hidden", mode !== "list");
        document.getElementById("permissionViewCardBtn").classList.toggle("bg-white/20", mode === "card");
        document.getElementById("permissionViewListBtn").classList.toggle("bg-white/20", mode === "list");

        if (mode === "card") {
            renderPermissionCardView();
        } else {
            renderPermissionListView();
        }
    }

    function setAllPermissions(checked) {
        if (checked) {
            dedupeExclusiveIds(allPermissionRows().map((p) => p.id)).forEach((id) => permissionCheckedIds.add(id));
        } else {
            permissionCheckedIds.clear();
        }
        if (permissionViewMode === "card") {
            renderPermissionCardView();
        } else {
            renderPermissionListView();
        }
        updatePermissionModalCounter();
    }

    function openPermissionsModal(context) {
        permissionsModalContext = context;

        const source = document.getElementById(permissionsSourceContainerId(context));
        permissionCheckedIds = new Set(
            Array.from(source.querySelectorAll('input[name="permissions[]"]:checked')).map((c) => Number(c.value))
        );

        document.getElementById("permissionSearchInput").value = "";
        setPermissionViewMode(permissionViewMode); // re-render whichever mode was last active
        updatePermissionModalCounter();

        const modal = document.getElementById("default-modal-manage-permissions");
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    }

    function closePermissionsModal() {
        const context = permissionsModalContext || "add";

        // Rebuild the hidden form container from the final checked-id state —
        // only checked permissions need a real <input>, unchecked ones simply
        // aren't submitted (standard checkbox form semantics).
        const target = document.getElementById(permissionsSourceContainerId(context));
        target.innerHTML = "";
        permissionCheckedIds.forEach((id) => {
            const input = document.createElement("input");
            input.type = "checkbox";
            input.name = "permissions[]";
            input.value = id;
            input.checked = true;
            target.appendChild(input);
        });

        updatePermissionSummary(context);

        const modal = document.getElementById("default-modal-manage-permissions");
        modal.classList.add("hidden");
        modal.classList.remove("flex");
        permissionsModalContext = null;
    }

    // Safety net: if the modal was ever dismissed without going through
    // closePermissionsModal() (e.g. a stray Escape-key handler), make sure
    // the current in-modal selection still lands in the <form> before it
    // submits — otherwise it'd silently be left out of FormData.
    function reconcilePermissionsIntoForm() {
        if (!permissionsModalContext) return;
        closePermissionsModal();
    }

    document.getElementById("permissionModalGrid").addEventListener("change", (e) => {
        if (e.target.name !== "permissions[]") return;
        const id = Number(e.target.value);
        if (e.target.checked) {
            permissionCheckedIds.add(id);
            if (enforceExclusiveGroups(id)) {
                // An exclusive partner got auto-unchecked elsewhere — full
                // re-render so its pill/header reflects that too.
                renderPermissionCardView();
                updatePermissionModalCounter();
                return;
            }
        } else {
            permissionCheckedIds.delete(id);
        }
        refreshPermissionPillStyle(e.target);
        const card = e.target.closest("[data-section]");
        if (card) syncPermissionCardHeader(card);
        updatePermissionModalCounter();
    });

    document.getElementById("permissionModalList").addEventListener("change", (e) => {
        if (e.target.name !== "permissions[]") return;
        const id = Number(e.target.value);
        if (e.target.checked) {
            permissionCheckedIds.add(id);
            if (enforceExclusiveGroups(id)) {
                renderPermissionListView();
                updatePermissionModalCounter();
                return;
            }
        } else {
            permissionCheckedIds.delete(id);
        }
        updatePermissionModalCounter();
    });

    document.getElementById("permissionModalList").addEventListener("click", (e) => {
        const th = e.target.closest("[data-sort]");
        if (!th) return;
        const key = th.dataset.sort;
        if (permissionListSort.key === key) {
            permissionListSort.dir = permissionListSort.dir === "asc" ? "desc" : "asc";
        } else {
            permissionListSort = { key, dir: "asc" };
        }
        renderPermissionListView();
    });

    document.getElementById("permissionViewCardBtn").addEventListener("click", () => setPermissionViewMode("card"));
    document.getElementById("permissionViewListBtn").addEventListener("click", () => setPermissionViewMode("list"));

    document.getElementById("permissionSearchInput").addEventListener("input", () => {
        filterPermissionsActiveView();
    });

    function fillEditUserForm(user) {
        document.getElementById("edit_user_id").value = user.id;
        document.getElementById("edit_display_name").value = user.display_name || "";
        document.getElementById("edit_username").value = user.username || "";
        document.getElementById("edit_role").value = user.role || "cashier";
        document.getElementById("edit_email").value = user.email || "";
        document.getElementById("edit_password").value = "";
        document.getElementById("edit_status").checked = Number(user.status) === 1;
    }

    async function openEditUser() {
        if (!selectedUserId) {
            showToast({ message: "Please select a user first.", type: "error" });
            return;
        }

        try {
            const response = await fetch(`/users/${selectedUserId}`, {
                method: "GET",
                headers: { Accept: "application/json" },
            });

            if (!response.ok) {
                showToast({ message: "User not found", type: "error" });
                return;
            }

            const user = await response.json();

            fillEditUserForm(user);
            await loadWarehouses_edit(user.warehouses || []);
            await loadPermissions("editPermissionList", user.permissions || []);

            const modal = document.getElementById("default-modal-edit-user");
            modal.classList.remove("hidden");
            modal.classList.add("flex");
        } catch (err) {
            console.error(err);
            showToast({ message: "Failed to load user", type: "error" });
        }
    }
    // `async function` declarations inside a block are NOT auto-exposed on
    // `window` the way plain `function` declarations are (Annex B legacy
    // hoisting explicitly excludes async/generator functions), so the
    // inline onclick="openEditUser()" attribute in the blade template
    // can't find it unless we attach it explicitly.
    window.openEditUser = openEditUser;

    async function updateUser() {
        reconcilePermissionsIntoForm();

        const userId = document.getElementById("edit_user_id").value;
        const form = document.getElementById("EditUserForm");
        const formData = new FormData(form);
        formData.append("_method", "PUT"); // PHP won't parse multipart bodies on a real PUT request

        const btn = document.getElementById("editSubmitBtn");
        btn.disabled = true;

        try {
            const res = await fetch(`/users/${userId}`, {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                    Accept: "application/json",
                },
            });

            const data = await res.json();

            if (data.success) {
                showToast({
                    message: data.message || "User updated successfully",
                    type: "success",
                });
                closeEditUserModal();
                fetchUsers();
            } else {
                showToast({
                    message: data.message || "Failed to update user",
                    type: "error",
                });
            }
        } catch (err) {
            console.error(err);
            showToast({ message: "Error updating user", type: "error" });
        } finally {
            btn.disabled = false;
        }
    }
    window.updateUser = updateUser; // see note above openEditUser — same async-in-block issue

    // ------------------------
    // Change Log
    // ------------------------
    let changeLogPage = 1;
    let changeLogLastPage = 1;
    const CHANGE_LOG_SECTIONS = [
        "pos_sale", "warehouse", "product", "category", "quotation", "customer",
        "purchasing", "exchange_rate", "user", "company_profile",
    ];

    function initChangeLogFilters() {
        const sectionSelect = document.getElementById("change_log_section");
        CHANGE_LOG_SECTIONS.forEach((section) => {
            const opt = document.createElement("option");
            opt.value = section;
            opt.textContent = section.replace(/_/g, " ");
            sectionSelect.appendChild(opt);
        });

        fetch("/users-list-data")
            .then((res) => res.json())
            .then((users) => {
                const userSelect = document.getElementById("change_log_user");
                users.forEach((u) => {
                    const opt = document.createElement("option");
                    opt.value = u.id;
                    opt.textContent = u.name;
                    userSelect.appendChild(opt);
                });
            })
            .catch((err) => console.error("Error loading users for change log filter:", err));

        ["change_log_user", "change_log_section", "change_log_action", "change_log_date_from", "change_log_date_to"]
            .forEach((id) => {
                document.getElementById(id).addEventListener("change", () => fetchChangeLog(1));
            });

        document.getElementById("change_log_prev").addEventListener("click", () => {
            if (changeLogPage > 1) fetchChangeLog(changeLogPage - 1);
        });
        document.getElementById("change_log_next").addEventListener("click", () => {
            if (changeLogPage < changeLogLastPage) fetchChangeLog(changeLogPage + 1);
        });

        document
            .getElementById("default-modal-change-log")
            .addEventListener("transitionend", function () {
                if (!this.classList.contains("hidden")) {
                    fetchChangeLog(1);
                }
            });
    }

    // Fields that are never useful to show in a human-facing diff — noise,
    // not something anyone changed on purpose.
    const CHANGE_LOG_SKIP_FIELDS = new Set(["id", "created_at", "updated_at", "password", "remember_token"]);

    function formatChangeLogValue(v) {
        if (v === null || v === undefined || v === "") return "—";
        if (Array.isArray(v)) return v.length ? v.join(", ") : "—";
        if (typeof v === "object") return JSON.stringify(v);
        return String(v);
    }

    // Diffs old_values/new_values down to just the fields that actually
    // changed — the raw JSON blobs include every column on the row
    // (id, timestamps, unrelated fields) which is unreadable at a glance.
    function formatChangeLogValues(row) {
        const oldVals = row.old_values || {};
        const newVals = row.new_values || {};
        if (!row.old_values && !row.new_values) return "-";

        const keys = new Set([...Object.keys(oldVals), ...Object.keys(newVals)]);
        const changes = [];
        keys.forEach((key) => {
            if (CHANGE_LOG_SKIP_FIELDS.has(key)) return;
            const ov = oldVals[key];
            const nv = newVals[key];
            if (JSON.stringify(ov) === JSON.stringify(nv)) return;
            const oldDisplay = formatChangeLogValue(ov);
            const newDisplay = formatChangeLogValue(nv);
            if (oldDisplay === newDisplay) return; // e.g. null vs "" — both show as "—"
            changes.push({ field: key, old: oldDisplay, new: newDisplay });
        });

        if (!changes.length) return `<span class="text-gray-400">No field changes</span>`;

        const MAX_FIELDS_SHOWN = 8;
        const shown = changes.slice(0, MAX_FIELDS_SHOWN);
        const hiddenCount = changes.length - shown.length;

        return shown.map((c) => `
            <div class="mb-0.5">
                <span class="font-semibold">${c.field}</span>:
                <span class="text-red-600">${c.old}</span>
                &rarr;
                <span class="text-green-600">${c.new}</span>
            </div>
        `).join("") + (hiddenCount > 0 ? `<div class="text-gray-400 italic">…and ${hiddenCount} more field${hiddenCount === 1 ? "" : "s"}</div>` : "");
    }

    async function fetchChangeLog(page = 1) {
        const params = new URLSearchParams({
            page,
            user_id: document.getElementById("change_log_user").value,
            section: document.getElementById("change_log_section").value,
            action: document.getElementById("change_log_action").value,
            date_from: document.getElementById("change_log_date_from").value,
            date_to: document.getElementById("change_log_date_to").value,
        });

        try {
            const res = await fetch(`/change-log-data?${params.toString()}`);
            const result = await res.json();

            changeLogPage = result.current_page || 1;
            changeLogLastPage = result.last_page || 1;

            const tbody = document.getElementById("change-log-table-body");
            tbody.innerHTML = "";

            const rows = result.data || [];
            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">No activity found</td></tr>`;
            } else {
                rows.forEach((row) => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td class="px-4 py-2 text-nowrap">${row.created_at ?? "-"}</td>
                        <td class="px-4 py-2">${row.user_name ?? "System"}</td>
                        <td class="px-4 py-2">${row.action}</td>
                        <td class="px-4 py-2">${row.section ?? "-"}</td>
                        <td class="px-4 py-2">${row.model_type ?? "-"} ${row.model_id ? "#" + row.model_id : ""}</td>
                        <td class="px-4 py-2 text-xs break-all max-w-md">${formatChangeLogValues(row)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById("change_log_pagination_info").textContent =
                `Page ${changeLogPage} of ${changeLogLastPage} (${result.total ?? rows.length} total)`;
        } catch (err) {
            console.error("Error loading change log:", err);
        }
    }

    initChangeLogFilters();
}
