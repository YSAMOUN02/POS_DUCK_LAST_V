<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Models\Currency;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchasingController;
use App\Http\Controllers\SaleInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\PosProfileController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\BinController;
use App\Http\Controllers\ItemLedgerEntryController;
use App\Http\Controllers\SaleOrderController;
use App\Http\Controllers\QuotationController;


use App\Http\Controllers\GainCostController;
use Illuminate\Support\Facades\Auth;

Route::get('/', [AdminController::class, 'login'])->name('login');
// Handle login post
Route::post('/login-submit', [AdminController::class, 'login_submit']);

// Outside the auth group so the language can also be switched on the login screen.
Route::get('/locale/{locale}', [App\Http\Controllers\LocaleController::class, 'switch'])->name('locale.switch');


Route::middleware(['auth'])->group(function () {
    Route::get('/generate-token', function () {

        $user = Auth::user();

        return $user->createToken('excel')->plainTextToken;
    });


    Route::get('/Sale', [AdminController::class, 'index_by_page'])->middleware('permission:pos_sale.view');
    Route::get('/pos/products', [AdminController::class, 'getProducts'])->middleware('permission:pos_sale.view');
    // USER
    Route::get('/users-list-data', [UserController::class, 'userListData'])->name('users.list.data')->middleware('permission:user.view');
    Route::post('/users/store', [UserController::class, 'store_user'])->middleware('permission:user.create');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:user.view');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:user.edit');
    // Get Warehouse for User
    Route::get('/warehouse-list-data', [UserController::class, 'get_warehouse_list'])->middleware('permission:user.view');
    // Get Permissions for User
    Route::get('/permissions-list-data', [PermissionController::class, 'permissionListData'])->middleware('permission:user.view');

    Route::post('/purchase/products/search', [PurchasingController::class, 'search'])->middleware('permission:purchasing.view');

    Route::post('/products/category/search', [ProductController::class, 'searchByCategory'])->middleware('permission:product.view');

    // Shared read-only lookup used by both the POS screen and Purchasing screen — not gated to a single section
    Route::get('/categories', [CategoryController::class, 'getCategories']);

    // Manage Categories tool
    Route::get('/categories/manage', [CategoryController::class, 'index'])->middleware('permission:category.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:category.create');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->middleware('permission:category.edit');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware('permission:category.delete');


    Route::get('/currency/{code}', [AdminController::class, 'getByCode'])->middleware('permission:exchange_rate.view');
    Route::post('/currency/update-all', [AdminController::class, 'updateAll'])
        ->name('currency.updateAll')->middleware('permission:exchange_rate.edit');

    Route::post('/customers/store', [CustomerController::class, 'store'])->name('customers.store')->middleware('permission:customer.create');


    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search')->middleware('permission:customer.view');
    Route::get('/customers/list', [CustomerController::class, 'list'])->middleware('permission:customer.view');
    // DELETE customer
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customer.delete');

    // UPDATE customer
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customer.edit');
    Route::get('/customers/list_search', [CustomerController::class, 'list_search'])->middleware('permission:customer.view');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customer.view');


    Route::get('/warehouses/list', [WarehouseController::class, 'list_warehouse'])->middleware('permission:warehouse.view');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouse.create');
    Route::post('/warehouses/update/{id}', [WarehouseController::class, 'update'])->middleware('permission:warehouse.edit');
    Route::post('/warehouses/{id}/toggle-status', [WarehouseController::class, 'toggleStatus'])->middleware('permission:warehouse.edit');
    Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouse.delete');
    // get stock
    Route::get('/warehouses/{id}/stock', [WarehouseController::class, 'getStock'])->middleware('permission:warehouse.view');
    Route::get('/product/categories', [WarehouseController::class, 'getCategories'])->middleware('permission:warehouse.view');

    // Bins
    Route::get('/bins', [BinController::class, 'index'])->middleware('permission:warehouse.view');
    Route::post('/bins', [BinController::class, 'store'])->middleware('permission:warehouse.create');
    Route::put('/bins/{id}', [BinController::class, 'update'])->middleware('permission:warehouse.edit');
    Route::delete('/bins/{id}', [BinController::class, 'destroy'])->middleware('permission:warehouse.delete');

    // Company / print profile
    Route::get('/pos-profile', [PosProfileController::class, 'show'])->middleware('permission:company_profile.view');
    Route::post('/pos-profile', [PosProfileController::class, 'update'])->middleware('permission:company_profile.edit');
    Route::post('/pos-profile/logo', [PosProfileController::class, 'uploadLogo'])->middleware('permission:company_profile.edit');
    Route::delete('/pos-profile/logo', [PosProfileController::class, 'removeLogo'])->middleware('permission:company_profile.edit');


    // Get lot
    Route::get('/get-lot-data/{product_id}', [WarehouseController::class, 'getLotData'])->middleware('permission:warehouse.view');
    // transfer Lot — route-level middleware only confirms basic warehouse
    // access; transfer() / transferFefo() decide whether this specific
    // request is a cross-warehouse "transfer" or a same-warehouse
    // bin-to-bin "movement" from the payload and check the matching
    // permission themselves (a single endpoint serves both).
    Route::post('/transfer-lot', [WarehouseController::class, 'transfer'])->middleware('permission:warehouse.view');
    Route::post('/transfer-fefo', [WarehouseController::class, 'transferFefo'])->middleware('permission:warehouse.view');


    Route::post('/products/store', [ProductController::class, 'store'])->name('products.store')->middleware('permission:product.create');
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search')->middleware('permission:product.view');
    Route::get('/products/list_search', [ProductController::class, 'list_search'])->middleware('permission:product.view');
    Route::put('/product/{id}', [ProductController::class, 'update'])->name('product.update')->middleware('permission:product.edit');




    // Report




    Route::get('/sales-report', [SaleInvoiceController::class, 'salesReport'])->name('sales.report')->middleware('permission:report.sales');
    Route::get('/sales/categories', [SaleInvoiceController::class, 'getCategories'])->middleware('permission:report.sales');

    Route::get('/sales/customer-search', [SaleInvoiceController::class, 'searchCustomers'])->middleware('permission:report.sales');
    Route::get('/sales/product-search', [SaleInvoiceController::class, 'searchProducts'])->middleware('permission:report.sales');
    Route::get('/sales/payment-methods', [SaleInvoiceController::class, 'getPaymentMethods'])->middleware('permission:report.sales');








    Route::get('/forgot/password', [AdminController::class, 'forgot_password']);


    Route::get('/logout', [AdminController::class, 'logout']);



    // "Purchase" is usable standalone — a user granted only purchasing.purchase
    // (no purchasing.view) can still reach the page and use it, not just
    // someone who separately also has view.
    Route::get('/Purchasing', [PurchasingController::class, 'Purchasing'])->middleware('permission:purchasing.view,purchasing.purchase');
    // Removed: GET /fetch-purchase pointed at PurchasingController::fetchPurchase,
    // which does not exist (only fetchPurchaseDoc / fetchPurchaseLines do), so the
    // route could only ever return a 500. Nothing in the frontend referenced it.
    Route::post('/vendors', [VendorController::class, 'store'])->middleware('permission:vendor.create');
    Route::get('/vendors/list', [VendorController::class, 'list'])->name('vendors.list')->middleware('permission:vendor.view');
    Route::get('/vendors/{id}', [VendorController::class, 'show'])->name('vendors.show')->middleware('permission:vendor.view');
    Route::put('/vendors/{id}', [VendorController::class, 'update'])->name('vendors.update')->middleware('permission:vendor.edit');
    Route::post('/vendor-search', [VendorController::class, 'search'])
        ->name('vendor.search')->middleware('permission:vendor.view');




    Route::get('/item-ledger-entry', [ItemLedgerEntryController::class, 'index'])->middleware('permission:report.stock');

    Route::get('/expenses/latest', [ExpenseController::class, 'latest'])->middleware('permission:expense.view,report.expense');
    // Refund an expense — writes a negative mirror row, never edits the original.
    Route::post('/expenses/refund', [ExpenseController::class, 'refund'])->middleware('permission:expense.refund');

    Route::get('/get-sale-orders', [SaleOrderController::class, 'getSaleOrders'])->middleware('permission:pos_sale.view');

    Route::get('/sale-order-lines/{id}', [SaleOrderController::class, 'getSaleOrderLines'])->middleware('permission:pos_sale.view');
    Route::get('/picking-list-data/{id}', [SaleOrderController::class, 'pickingListData'])->middleware('permission:pos_sale.view');

    Route::post('/update-sale-order-status', [SaleOrderController::class, 'updateStatus'])
        ->name('sale-order.update-status')->middleware('permission:pos_sale.sell');

    // Quotations
    Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:quotation.view');
    Route::get('/quotations/{id}', [QuotationController::class, 'show'])->middleware('permission:quotation.view');
    Route::post('/quotations/update-status', [QuotationController::class, 'updateStatus'])
        ->name('quotations.update-status')->middleware('permission:quotation.edit');
    // routes/web.php
    Route::post('/sale-order/mark-all-delivered', [SaleOrderController::class, 'markAllDelivered'])->middleware('permission:pos_sale.mark_delivered');
    // Settles every unpaid order in the caller's CURRENT filtered view — the
    // same visibility scope as the list, so it can never reach further.
    Route::post('/sale-order/mark-all-paid', [SaleOrderController::class, 'markAllPaid'])->middleware('permission:pos_sale.mark_all_paid');
    Route::post('/sale-order/update-delivery-status', [SaleOrderController::class, 'updateDeliveryStatus'])
        ->name('sale-order.update-delivery-status')->middleware('permission:pos_sale.sell');


    Route::prefix('reports/gain-cost')->middleware('permission:report.profit')->group(function () {
        Route::get('/',             [GainCostController::class, 'index']);        // the page
        Route::get('/summary',      [GainCostController::class, 'summary']);      // KPI cards + deltas
        Route::get('/trend',        [GainCostController::class, 'trend']);        // line chart series
        Route::get('/breakdown',    [GainCostController::class, 'breakdown']);    // donut + category bar + top products
        Route::get('/transactions', [GainCostController::class, 'transactions']); // table (sales|purchases|expenses)
        Route::get('/detail',       [GainCostController::class, 'detail']);       // modal content (?type=&id=)
        Route::get('/stock',        [GainCostController::class, 'stock']);        // current stock charts (qty + value)
        Route::get('/export',       [GainCostController::class, 'export'])->middleware('permission:report.export');
        Route::get('/export-excel', [GainCostController::class, 'exportExcel'])->middleware('permission:report.export');
        Route::get('/export-stock', [GainCostController::class, 'exportStock'])->middleware('permission:report.export');
        Route::get('/sales-detail', [GainCostController::class, 'salesDetail']);  // line explorer (async, paginated; ?export=csv)
        Route::get('/inventory', [GainCostController::class, 'inventory']);
        Route::get('/services', [GainCostController::class, 'services']);
    });








    Route::get('/export-purchase', [PurchasingController::class, 'exportPurchase'])->name('purchase.export')->middleware(['permission:purchasing.view', 'permission:report.export']);
    Route::get('/sale-report/export-excel', [SaleOrderController::class, 'exportSalesExcel'])->name('sale.export.excel')->middleware(['permission:report.sales', 'permission:report.export']);
    Route::get('/products/export-excel', [ProductController::class, 'exportProducts'])->middleware(['permission:product.view', 'permission:report.export']);


    // purchasing.purchase is accepted too: posting a GRN only requires that
    // permission, and the "print GRN" step straight after posting reads the
    // document back through here — gating on view alone 403s the very user who
    // just created it.
    Route::get('/fetch-purchase-doc', [PurchasingController::class, 'fetchPurchaseDoc'])->middleware('permission:purchasing.view,purchasing.purchase');
    Route::get('/purchase-return/returnable', [PurchaseReturnController::class, 'returnable'])->middleware('permission:purchasing.view');
    Route::post('/purchase-return/confirm',   [PurchaseReturnController::class, 'confirm'])->middleware('permission:purchasing.purchase_return');
    Route::get('/fetch-purchase-lines', [PurchasingController::class, 'fetchPurchaseLines'])->middleware('permission:purchasing.view');

    // routes/web.php
Route::post('/pos/print-receipt', [App\Http\Controllers\ThermalPrintController::class, 'print'])->middleware('permission:pos_sale.view');
});
