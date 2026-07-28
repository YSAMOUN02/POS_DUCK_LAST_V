/* =====================================================================
   RIGHT-CLICK QUANTITY STEPPER
   ---------------------------------------------------------------------
   Right-click a cart line for a volume-style control at the pointer.
   Scroll the wheel — or use +/-, the arrow keys, or type a number — to
   set that line's quantity, then confirm.

   NOTHING is sent while you are still scrolling. The value is held here
   and committed once, as an ABSOLUTE quantity, when you stop.

   That is deliberate. The first version sent a delta per burst and let
   the cart re-render re-baseline the popup: with deltas in flight the
   baseline shifted underneath, the number snapped backwards, and the
   wheel could never climb past a dozen or so. One absolute value, sent
   once, has no such race — scroll to 90 and 90 is what arrives.
   ===================================================================== */
(function () {
    "use strict";

    const ROW_SELECTOR = ".ci-card[data-cart-index]";
    const IDLE_COMMIT = 650; // ms of stillness that counts as "stopped"
    const MIN_QTY = 0.01;

    let popup = null;
    let target = null; // { index, name, base }
    let value = 0; // the quantity being composed, absolute
    let idleTimer = null;
    let sending = false;

    // ---------------------------------------------------------------- styles
    function injectStyles() {
        if (document.getElementById("qs-styles")) return;
        const css = document.createElement("style");
        css.id = "qs-styles";
        css.textContent = `
            .qs-pop {
                position: fixed; z-index: 10050;
                display: flex; flex-direction: column; align-items: center; gap: 8px;
                padding: 12px 14px; border-radius: 16px;
                background: #0f172a; color: #fff;
                box-shadow: 0 18px 40px rgba(15,23,42,.45);
                font-family: inherit; user-select: none;
                animation: qsIn .12s ease-out;
            }
            @keyframes qsIn { from { opacity:0; transform: scale(.92); } to { opacity:1; transform: scale(1); } }

            .qs-name { max-width: 200px; font-size: 11px; font-weight: 600; color: #cbd5e1;
                       white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .qs-row  { display: flex; align-items: center; gap: 10px; }
            .qs-btn  {
                width: 34px; height: 34px; border: 0; border-radius: 10px; cursor: pointer;
                background: #1e293b; color: #fff; font-size: 18px; font-weight: 700; line-height: 1;
                display: flex; align-items: center; justify-content: center;
                transition: background .12s ease, transform .08s ease;
            }
            .qs-btn:hover  { background: #334155; }
            .qs-btn:active { transform: scale(.9); }

            /* Editable so a large jump can simply be typed rather than scrolled. */
            .qs-val {
                width: 84px; text-align: center; border: 0; outline: 0;
                background: transparent; color: #fff;
                font-family: inherit; font-size: 22px; font-weight: 800;
                font-variant-numeric: tabular-nums;
            }
            .qs-val.qs-dirty { color: #fbbf24; }

            .qs-actions { display: flex; gap: 8px; width: 100%; }
            .qs-apply, .qs-cancel {
                flex: 1; border: 0; border-radius: 10px; padding: 7px 0; cursor: pointer;
                font-family: inherit; font-size: 12px; font-weight: 700;
                transition: background .12s ease, opacity .12s ease;
            }
            .qs-apply  { background: #059669; color: #fff; }
            .qs-apply:hover { background: #047857; }
            .qs-apply:disabled { opacity: .45; cursor: default; }
            .qs-cancel { background: #1e293b; color: #cbd5e1; }
            .qs-cancel:hover { background: #334155; }

            .qs-hint { font-size: 10px; color: #64748b; }
        `;
        document.head.appendChild(css);
    }

    // ----------------------------------------------------------------- helpers
    const round2 = (n) => Math.round(n * 100) / 100;
    const trim = (n) => String(round2(n)).replace(/\.?0+$/, "") || "0";
    const dirty = () => target && round2(value) !== round2(target.base);

    function render() {
        if (!popup) return;
        const input = popup.querySelector(".qs-val");
        if (document.activeElement !== input) input.value = trim(value);
        input.classList.toggle("qs-dirty", dirty());
        popup.querySelector(".qs-apply").disabled = !dirty() || sending;
    }

    function destroy() {
        clearTimeout(idleTimer);
        popup?.remove();
        popup = null;
        target = null;
        sending = false;
    }

    // ------------------------------------------------------------------ commit
    function commit() {
        clearTimeout(idleTimer);
        if (!target || !dirty() || sending) return destroy();

        sending = true;
        const { index } = target;
        const qty = Math.max(MIN_QTY, round2(value));

        try {
            // Absolute value — the server clamps it against stock and floors it.
            Livewire.dispatch("set-qty", { index, qty });
        } catch (err) {
            console.error("qty stepper: set-qty dispatch failed", err);
        }
        destroy();
    }

    function scheduleIdleCommit() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(commit, IDLE_COMMIT);
    }

    function bump(step) {
        if (!target) return;
        value = Math.max(MIN_QTY, round2(value + step));
        render();
        scheduleIdleCommit(); // only fires once the wheel actually stops
    }

    // ------------------------------------------------------------------ popup
    function open(x, y, row) {
        destroy();

        const base = Number(row.dataset.cartQty) || 0;
        target = { index: Number(row.dataset.cartIndex), name: row.dataset.cartName || "", base };
        value = base;

        popup = document.createElement("div");
        popup.className = "qs-pop";
        popup.innerHTML = `
            <div class="qs-name"></div>
            <div class="qs-row">
                <button type="button" class="qs-btn" data-step="-1">&minus;</button>
                <input class="qs-val" inputmode="decimal" autocomplete="off">
                <button type="button" class="qs-btn" data-step="1">+</button>
            </div>
            <div class="qs-actions">
                <button type="button" class="qs-cancel">Cancel</button>
                <button type="button" class="qs-apply">Apply</button>
            </div>
            <div class="qs-hint">scroll / ↑↓ / type · applies when you stop</div>
        `;
        popup.querySelector(".qs-name").textContent = target.name;
        document.body.appendChild(popup);

        const r = popup.getBoundingClientRect();
        popup.style.left = `${Math.min(Math.max(8, x - r.width / 2), window.innerWidth - r.width - 8)}px`;
        popup.style.top = `${Math.min(Math.max(8, y - r.height - 12), window.innerHeight - r.height - 8)}px`;

        popup.addEventListener("click", (e) => {
            const step = e.target.closest("[data-step]");
            if (step) {
                e.stopPropagation();
                return bump(Number(step.dataset.step));
            }
            if (e.target.closest(".qs-apply")) {
                e.stopPropagation();
                return commit();
            }
            if (e.target.closest(".qs-cancel")) {
                e.stopPropagation();
                return destroy(); // discard, cart untouched
            }
        });

        // passive:false so preventDefault actually stops the cart scrolling.
        popup.addEventListener(
            "wheel",
            (e) => {
                e.preventDefault();
                e.stopPropagation();
                bump(e.deltaY < 0 ? 1 : -1);
            },
            { passive: false },
        );

        const input = popup.querySelector(".qs-val");
        input.addEventListener("input", () => {
            const n = parseFloat(input.value.replace(/,/g, ""));
            if (Number.isFinite(n)) {
                value = n;
                popup.querySelector(".qs-apply").disabled = !dirty();
                scheduleIdleCommit();
            }
        });
        input.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                commit();
            }
        });

        render();
    }

    // ----------------------------------------------------------------- events
    document.addEventListener("contextmenu", (e) => {
        const row = e.target.closest(ROW_SELECTOR);
        if (!row) return;
        if (row.dataset.cartLocked === "1") return; // posted cart is read-only

        e.preventDefault();
        open(e.clientX, e.clientY, row);
    });

    document.addEventListener("pointerdown", (e) => {
        if (popup && !e.target.closest(".qs-pop")) commit();
    });

    document.addEventListener("keydown", (e) => {
        if (!popup) return;
        if (e.key === "Escape") return destroy();
        if (e.key === "ArrowUp") {
            e.preventDefault();
            bump(1);
        }
        if (e.key === "ArrowDown") {
            e.preventDefault();
            bump(-1);
        }
    });

    // The popup owns the number while it is open, so a cart re-render must NOT
    // write back into it — that re-baselining is exactly what made the value
    // jump backwards mid-scroll. Once the value has been sent the popup is gone
    // anyway, so there is nothing to reconcile.

    window.addEventListener("blur", () => popup && commit());

    injectStyles();
})();
