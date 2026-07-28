/* =====================================================================
   DRAG ITEM CARD -> CART
   ---------------------------------------------------------------------
   Optional input mode: instead of tapping a product card, drag it into the
   cart panel. A 3D copy of the card follows the pointer, tilts with the
   drag, and flies into the cart on release.

   Off by default and remembered per user. Tapping remains the fastest way
   to ring up a sale, and a drag gesture on a touch screen competes with
   scrolling the product grid — so this is opt-in, not a replacement.

   Pointer Events are used rather than HTML5 drag-and-drop: HTML5 DnD does
   not fire on touch screens, which is most of the POS hardware here.
   ===================================================================== */
(function () {
    "use strict";

    const CARD_SELECTOR = ".add-to-cart-btn[data-product]";
    const CART_SELECTOR = "#sidebar";
    const DRAG_THRESHOLD = 8; // px before a press becomes a drag, not a tap

    const storageKey = () =>
        `pos_drag_to_cart_${typeof user_id !== "undefined" ? user_id : "anon"}`;

    let enabled = false;
    let drag = null; // active drag state, null when idle
    let suppressClickUntil = 0;

    // ---------------------------------------------------------------- styles
    function injectStyles() {
        if (document.getElementById("dtc-styles")) return;
        const css = document.createElement("style");
        css.id = "dtc-styles";
        css.textContent = `
            body.dtc-on ${CARD_SELECTOR} {
                cursor: grab;
                touch-action: none;
                -webkit-user-select: none;
                user-select: none;
            }
            body.dtc-on ${CARD_SELECTOR}:active { cursor: grabbing; }

            /* Product cards contain an <img>. Pressing an image and moving starts
               the BROWSER's own drag, which fires pointercancel and killed this
               drag before it began — the card simply would not pick up. Images
               must not be natively draggable, and the pointer has to reach the
               card rather than being captured by the image. */
            body.dtc-on ${CARD_SELECTOR} img {
                -webkit-user-drag: none;
                user-drag: none;
                pointer-events: none;
            }

            .dtc-ghost {
                position: fixed; z-index: 10000; pointer-events: none;
                margin: 0; border-radius: 14px; overflow: hidden;
                background: #fff; box-shadow: 0 22px 45px rgba(15,23,42,.35);
                transform-origin: center center;
                will-change: transform, opacity;
            }
            /* The card is lifted out of the page and tilted, so it reads as a
               solid object being carried rather than a flat copy sliding. */
            .dtc-ghost.dtc-flying { transition: transform .42s cubic-bezier(.4,0,.2,1), opacity .42s ease; }

            .dtc-drop-active {
                outline: 3px dashed #10b981;
                outline-offset: -6px;
                background-color: rgba(16,185,129,.06);
                transition: outline-color .15s ease, background-color .15s ease;
            }
            .dtc-pulse { animation: dtcPulse .45s ease; }
            @keyframes dtcPulse {
                0%   { transform: scale(1); }
                45%  { transform: scale(1.03); }
                100% { transform: scale(1); }
            }
            @media (prefers-reduced-motion: reduce) {
                .dtc-ghost.dtc-flying { transition-duration: .01ms; }
                .dtc-pulse { animation: none; }
            }
        `;
        document.head.appendChild(css);
    }

    // ------------------------------------------------------------- toggle
    function applyEnabled(on) {
        enabled = !!on;
        document.body.classList.toggle("dtc-on", enabled);
        try {
            localStorage.setItem(storageKey(), enabled ? "1" : "0");
        } catch (e) {
            /* private mode — the toggle still works for this session */
        }
    }

    function initToggle() {
        const box = document.getElementById("dragToCartToggle");
        if (!box || box.dataset.dtcBound) return;
        box.dataset.dtcBound = "1";

        let saved = "0";
        try {
            saved = localStorage.getItem(storageKey()) ?? "0";
        } catch (e) {
            /* ignore */
        }
        box.checked = saved === "1";
        applyEnabled(box.checked);

        box.addEventListener("change", () => applyEnabled(box.checked));
    }

    // -------------------------------------------------------------- helpers
    const cartEl = () => document.querySelector(CART_SELECTOR);

    function overCart(x, y) {
        const el = cartEl();
        if (!el) return false;
        const r = el.getBoundingClientRect();
        return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
    }

    function buildGhost(card) {
        const r = card.getBoundingClientRect();
        const ghost = card.cloneNode(true);

        ghost.classList.add("dtc-ghost");
        ghost.classList.remove("dtc-drop-active");

        // A deep clone copies every id inside the card too (product-image123 and
        // the like). Two elements sharing an id breaks getElementById for the
        // real card underneath, so strip them from the copy.
        ghost.removeAttribute("id");
        ghost.querySelectorAll("[id]").forEach((n) => n.removeAttribute("id"));

        // Lazy images can arrive undecoded in a clone; force them to load now so
        // the card being dragged is not a blank rectangle.
        ghost.querySelectorAll("img").forEach((img) => {
            img.loading = "eager";
            img.draggable = false;
        });
        ghost.style.width = `${r.width}px`;
        ghost.style.height = `${r.height}px`;
        ghost.style.left = `${r.left}px`;
        ghost.style.top = `${r.top}px`;

        document.body.appendChild(ghost);
        return { ghost, rect: r };
    }

    function moveGhost(state, x, y) {
        const dx = x - state.startX;
        const dy = y - state.startY;

        // Tilt follows how fast the pointer is moving, capped so the card never
        // spins far enough to read as a bug.
        const vx = x - state.lastX;
        const vy = y - state.lastY;
        state.lastX = x;
        state.lastY = y;

        const tiltY = Math.max(-18, Math.min(18, vx * 1.6));
        const tiltX = Math.max(-18, Math.min(18, -vy * 1.6));

        state.ghost.style.transform =
            `perspective(700px) translate3d(${dx}px, ${dy}px, 60px) ` +
            `rotateX(${tiltX}deg) rotateY(${tiltY}deg) scale(1.06)`;
    }

    function endDrag(commit, x, y) {
        const state = drag;
        drag = null;
        if (!state) return;

        const cart = cartEl();
        if (cart) cart.classList.remove("dtc-drop-active");

        // A drag must not also fire the card's click handler, or the item would
        // be added twice.
        suppressClickUntil = Date.now() + 400;

        const ghost = state.ghost;
        ghost.classList.add("dtc-flying");

        if (commit && cart) {
            const cr = cart.getBoundingClientRect();
            const toX = cr.left + cr.width / 2 - (state.rect.left + state.rect.width / 2);
            const toY = cr.top + 90 - (state.rect.top + state.rect.height / 2);

            ghost.style.transform =
                `perspective(700px) translate3d(${toX}px, ${toY}px, 0) ` +
                `rotateX(0deg) rotateY(0deg) scale(.12)`;
            ghost.style.opacity = "0";

            cart.classList.add("dtc-pulse");
            setTimeout(() => cart.classList.remove("dtc-pulse"), 500);

            try {
                Livewire.dispatch("add-product", state.productJson);
            } catch (err) {
                console.error("drag-to-cart: add-product dispatch failed", err);
            }
        } else {
            // Snap home — nothing was added.
            ghost.style.transform = "perspective(700px) translate3d(0,0,0) scale(1)";
            ghost.style.opacity = "0";
        }

        setTimeout(() => ghost.remove(), 450);
    }

    // --------------------------------------------------------------- events
    document.addEventListener(
        "pointerdown",
        (e) => {
            if (!enabled || e.button !== 0) return;

            const card = e.target.closest(CARD_SELECTOR);
            if (!card) return;

            drag = {
                card,
                productJson: card.dataset.product,
                startX: e.clientX,
                startY: e.clientY,
                lastX: e.clientX,
                lastY: e.clientY,
                started: false,
                pointerId: e.pointerId,
            };
        },
        true,
    );

    document.addEventListener("pointermove", (e) => {
        if (!drag || e.pointerId !== drag.pointerId) return;

        if (!drag.started) {
            const moved = Math.hypot(e.clientX - drag.startX, e.clientY - drag.startY);
            if (moved < DRAG_THRESHOLD) return; // still a tap

            const built = buildGhost(drag.card);
            drag.ghost = built.ghost;
            drag.rect = built.rect;
            drag.started = true;
        }

        e.preventDefault(); // stop the grid scrolling under the drag
        moveGhost(drag, e.clientX, e.clientY);

        const cart = cartEl();
        if (cart) cart.classList.toggle("dtc-drop-active", overCart(e.clientX, e.clientY));
    });

    document.addEventListener("pointerup", (e) => {
        if (!drag || e.pointerId !== drag.pointerId) return;
        if (!drag.started) {
            drag = null; // a plain tap: let the existing click handler run
            return;
        }
        endDrag(overCart(e.clientX, e.clientY), e.clientX, e.clientY);
    });

    document.addEventListener("pointercancel", () => {
        if (drag?.started) endDrag(false);
        else drag = null;
    });

    // Belt and braces alongside the CSS above: some elements are natively
    // draggable regardless (images, links), and one native dragstart is enough
    // to cancel the pointer sequence this relies on.
    document.addEventListener(
        "dragstart",
        (e) => {
            if (enabled && e.target.closest(CARD_SELECTOR)) e.preventDefault();
        },
        true,
    );

    // Swallow the click a completed drag leaves behind.
    document.addEventListener(
        "click",
        (e) => {
            if (Date.now() < suppressClickUntil && e.target.closest(CARD_SELECTOR)) {
                e.stopPropagation();
                e.preventDefault();
            }
        },
        true,
    );

    // ----------------------------------------------------------------- boot
    function boot() {
        injectStyles();
        initToggle();
    }

    document.addEventListener("DOMContentLoaded", boot);
    // The cart header is a Livewire component, so the toggle is re-rendered on
    // every cart update — rebind it each time rather than only at load.
    document.addEventListener("livewire:navigated", boot);
    document.addEventListener("livewire:update", initToggle);
    if (document.readyState !== "loading") boot();
})();
