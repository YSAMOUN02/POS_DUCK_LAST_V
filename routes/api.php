<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\ItemLedgerEntryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/get_data_item_ledger_entry/{year}',
        [ApiController::class, 'api_get_item_ledger_entry_by_year']
    );
     Route::get(
        '/get_data_sale_order/{year}',
        [ApiController::class, 'api_get_sale_order_by_year']
    );
});
