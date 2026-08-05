<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for "which documents may this user see".
 *
 *   admin      → everything, no filter
 *   supervisor → anything belonging to a warehouse they are assigned to
 *   cashier     → only documents they created themselves
 *
 * Kept in one trait because the rule was previously duplicated (and had
 * drifted) across SaleOrderController, SaleInvoiceController,
 * ItemLedgerEntryController and CustomerController — a cashier could see every
 * sale order in their warehouse while only seeing their own ledger rows.
 */
trait ScopesVisibilityByRole
{
    /**
     * For header tables that carry no warehouse_id of their own: reach the
     * warehouse through the item_ledger_entries rows the document produced.
     *
     * @param  string  $localColumn   Qualified document column, e.g. "sale_order_headers.document_no"
     * @param  string  $ledgerColumn  Matching ledger column, e.g. "ile.source_no"
     */
    protected function scopeVisibilityViaLedger(Builder $query, string $localColumn, string $ledgerColumn): Builder
    {
        $user = Auth::user();

        if (!$user || $user->role === 'admin') {
            return $query;
        }

        if ($user->role === 'cashier') {
            return $query->where('created_user_id', (string) $user->id);
        }

        // Supervisor: warehouse scope. Documents with no ledger trail yet
        // (Quotation/Ordered/Cancelled never touch stock) have no warehouse to
        // match on, so they stay visible to their creator rather than
        // disappearing entirely.
        $warehouseIds = $user->warehouses->pluck('id');

        return $query->where(function ($outer) use ($warehouseIds, $localColumn, $ledgerColumn, $user) {
            $outer->whereExists(function ($q) use ($warehouseIds, $localColumn, $ledgerColumn) {
                $q->selectRaw('1')
                    ->from('item_ledger_entries as ile')
                    ->whereIn('ile.warehouse_id', $warehouseIds)
                    ->whereColumn($ledgerColumn, $localColumn);
            })->orWhere('created_user_id', (string) $user->id);
        });
    }

    /**
     * For tables that already store warehouse_id (item_ledger_entries,
     * warehouse_product, …).
     */
    protected function scopeVisibilityByWarehouseColumn(Builder $query, string $warehouseColumn = 'warehouse_id'): Builder
    {
        $user = Auth::user();

        if (!$user || $user->role === 'admin') {
            return $query;
        }

        if ($user->role === 'cashier') {
            return $query->where('created_user_id', (string) $user->id);
        }

        return $query->whereIn($warehouseColumn, $user->warehouses->pluck('id'));
    }

    /**
     * Guard for acting on ONE record fetched by id.
     *
     * The scope helpers above only protect list queries. Every single-record
     * endpoint (`find($request->id)` → read / update / cancel / delete) was
     * unguarded, so an id from the request was enough to reach another
     * cashier's document. Call this immediately after loading the record.
     *
     * @param  string  $ownerColumn  column holding the creator's user id
     * @param  array{0:string,1:string}|null  $viaLedger
     *        [$recordProperty, $ledgerColumn] for headers that carry no
     *        warehouse_id, e.g. ['document_no', 'source_no']. Mirrors the
     *        arguments given to scopeVisibilityViaLedger() for the same table.
     */
    protected function authorizeDocumentAccess($record, string $ownerColumn = 'created_user_id', ?array $viaLedger = null): void
    {
        $user = Auth::user();

        if (!$user || $user->role === 'admin') {
            return;
        }

        abort_if($record === null, 404);

        if ($user->role === 'cashier') {
            abort_unless((string) ($record->{$ownerColumn} ?? '') === (string) $user->id, 403);
            return;
        }

        $isOwner = (string) ($record->{$ownerColumn} ?? '') === (string) $user->id;
        $warehouseIds = $user->warehouses->pluck('id');

        // Supervisor: allowed when the document belongs to one of their
        // warehouses, or when they created it themselves.
        if (isset($record->warehouse_id)) {
            abort_unless($warehouseIds->contains((int) $record->warehouse_id) || $isOwner, 403);
            return;
        }

        // No warehouse_id on the header — reach the warehouse through the
        // ledger rows the document produced, exactly as the list query does.
        // Without this the list and the detail disagreed: a supervisor saw an
        // order in the list via its ledger trail, then got 403 opening it,
        // because this guard accepted only documents they had created.
        if ($viaLedger !== null) {
            [$recordProperty, $ledgerColumn] = $viaLedger;
            $key = $record->{$recordProperty} ?? null;

            $inWarehouse = $key !== null && $key !== ''
                && \Illuminate\Support\Facades\DB::table('item_ledger_entries')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->where($ledgerColumn, $key)
                    ->exists();

            abort_unless($inWarehouse || $isOwner, 403);
            return;
        }

        abort_unless($isOwner, 403);
    }

    /**
     * Guard for any endpoint that accepts a warehouse id from the client.
     * The UI restricts the pickers, but the server re-reads the id from the
     * payload, so the restriction was cosmetic on the write paths.
     */
    protected function authorizeWarehouseAccess($warehouseId): void
    {
        $user = Auth::user();

        if (!$user || $user->role === 'admin') {
            return;
        }

        abort_unless(
            $warehouseId !== null && $user->warehouses->pluck('id')->contains((int) $warehouseId),
            403,
            'You are not assigned to that warehouse.'
        );
    }
}
