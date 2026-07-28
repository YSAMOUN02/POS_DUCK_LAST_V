/* =====================================================================
   HORIZONTAL DRAG DIVIDER FOR THE PRODUCT GRID
   ---------------------------------------------------------------------
   Companion to the vertical #resizer that sets the cart's width. This one
   sits under the product grid on the left and sets its HEIGHT, so the
   card area can be made taller or shorter without touching the cart.

   Shared by the sales and purchasing screens: both render #mainContent
   with a scrolling #tab-content grid inside, so the same bar works on
   each with no per-page configuration.

   The height is remembered per user, and only applied on wide screens —
   below the lg breakpoint the layout stacks to one column and a saved
   desktop height would squash the grid on a phone.
   ===================================================================== */
(function () {
    "use strict";

    const MIN_H = 180; // never collapse the grid to nothing
    const BOTTOM_GAP = 120; // leave room for the page chrome below
    const DESKTOP = 1024; // matches Tailwind's lg breakpoint

    const storageKey = () =>
        `pos_grid_height_${typeof user_id !== "undefined" ? user_id : "anon"}`;

    function injectStyles() {
        if (document.getElementById("gr-styles")) return;
        const css = document.createElement("style");
        css.id = "gr-styles";
        css.textContent = `
            .gr-bar {
                height: 10px; flex: 0 0 auto; cursor: row-resize;
                background: #e5e7eb; position: relative; z-index: 20;
                transition: background .15s ease;
                touch-action: none;
            }
            .gr-bar:hover, .gr-bar.gr-active { background: #60a5fa; }
            /* The grip is the visible affordance — a 10px strip alone does not
               read as draggable. */
            .gr-bar::after {
                content: ""; position: absolute; top: 50%; left: 50%;
                width: 46px; height: 3px; border-radius: 999px;
                transform: translate(-50%, -50%);
                background: #9ca3af;
            }
            .gr-bar:hover::after, .gr-bar.gr-active::after { background: #fff; }
            @media (max-width: ${DESKTOP - 1}px) { .gr-bar { display: none; } }
        `;
        document.head.appendChild(css);
    }

    function init() {
        const grid = document.getElementById("tab-content");
        const main = document.getElementById("mainContent");
        if (!grid || !main || document.querySelector(".gr-bar")) return;

        const maxH = () => Math.max(MIN_H, window.innerHeight - BOTTOM_GAP);
        const clamp = (h) => Math.max(MIN_H, Math.min(maxH(), h));

        function setHeight(h) {
            const v = clamp(h);
            grid.style.height = `${v}px`;
            grid.style.maxHeight = `${v}px`;
            return v;
        }

        const bar = document.createElement("div");
        bar.className = "gr-bar";
        bar.title = "Drag to resize the product area";
        grid.insertAdjacentElement("afterend", bar);

        // Desktop only — see the note at the top of this file.
        const saved = parseInt(localStorage.getItem(storageKey()), 10);
        if (saved && window.innerWidth >= DESKTOP) setHeight(saved);

        let resizing = false;

        const start = () => {
            resizing = true;
            bar.classList.add("gr-active");
            document.body.style.cursor = "row-resize";
            document.body.style.userSelect = "none";
        };

        const move = (clientY) => {
            if (!resizing) return;
            // Height is measured from the grid's own top, so the divider tracks
            // the pointer regardless of what sits above it.
            setHeight(clientY - grid.getBoundingClientRect().top);
        };

        const stop = () => {
            if (!resizing) return;
            resizing = false;
            bar.classList.remove("gr-active");
            document.body.style.cursor = "";
            document.body.style.userSelect = "";
            try {
                localStorage.setItem(storageKey(), String(grid.getBoundingClientRect().height | 0));
            } catch (e) {
                /* private mode — the size still holds for this session */
            }
        };

        bar.addEventListener("pointerdown", (e) => {
            e.preventDefault();
            bar.setPointerCapture?.(e.pointerId);
            start();
        });
        window.addEventListener("pointermove", (e) => move(e.clientY));
        window.addEventListener("pointerup", stop);
        window.addEventListener("pointercancel", stop);

        // A saved height taller than the new viewport would push the page into a
        // scroll it never had; re-clamp instead.
        window.addEventListener("resize", () => {
            if (grid.style.height) setHeight(parseInt(grid.style.height, 10));
        });

        // Double-click clears the override and hands the height back to CSS.
        bar.addEventListener("dblclick", () => {
            grid.style.height = "";
            grid.style.maxHeight = "";
            try {
                localStorage.removeItem(storageKey());
            } catch (e) {
                /* ignore */
            }
        });
    }

    function boot() {
        injectStyles();
        init();
    }

    document.addEventListener("DOMContentLoaded", boot);
    document.addEventListener("livewire:navigated", boot);
    if (document.readyState !== "loading") boot();
})();
