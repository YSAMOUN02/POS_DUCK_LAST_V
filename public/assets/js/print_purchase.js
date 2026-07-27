



window.printPurchase = function () {
    if (!currentPurchase) {
        alert("Please open purchase first");
        return;
    }

    printPurchaseOrder(currentPurchase);
};

async function printPurchaseOrder() {
    await printPurchaseOrderA4(currentPurchase, pos_profile_for_print);
}
