/* Print the purchase currently open in the Purchase Details modal.
   currentPurchase is set by openPurchaseLineModal() in script_purchase.js. */

async function printPurchaseOrder(purchase) {
    // typeof, not a bare read: an undeclared identifier throws on read, and a
    // missing shop profile should print a plain letterhead rather than abort.
    const posInfo =
        typeof pos_profile_for_print !== "undefined"
            ? pos_profile_for_print
            : (window.pos_profile_for_print ?? null);

    await printPurchaseOrderA4(purchase, posInfo);
}

window.printPurchase = async function () {
    if (!currentPurchase) {
        showToast({ message: "Open a purchase first", type: "error" });
        return;
    }

    // This used to call printPurchaseOrder() without awaiting it and without a
    // catch, so anything that went wrong surfaced as an unhandled promise
    // rejection in the console and nothing at all on screen — the print simply
    // did not happen, and gave no reason.
    try {
        await printPurchaseOrder(currentPurchase);
    } catch (err) {
        console.error("Purchase print failed:", err);
        showToast({
            message: `Failed to print ${currentPurchase.no ?? "purchase"} — ${err?.name ?? "Error"}: ${err?.message ?? err}`,
            type: "error",
            duration: 12000,
        });
    }
};
