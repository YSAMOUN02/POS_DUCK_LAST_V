<?php

namespace App\Concerns;

use App\Http\Controllers\PosProfileController;
use App\Models\InvoiceHeader;
use App\Models\PosProfile;
use App\Models\SaleOrderHeader;

/**
 * Letterhead resolution for printed sale documents.
 *
 * Shared by the Livewire cart (which prints straight after a sale) and the
 * sale-order JSON endpoints (which the browser reprints from), so a document
 * carries the same profile no matter which path printed it.
 */
trait ResolvesPrintProfile
{
    /**
     * Letterhead for a printed sale document.
     *
     * An invoice belongs to whoever ISSUED it, which is not always whoever
     * raised the order — a cashier can create the order and a supervisor issue
     * the invoice, and the printed invoice must carry the issuer's profile.
     * Neither is necessarily the person reprinting it later, so this never
     * looks at the signed-in user.
     *
     * The earliest invoice for the order is used, so a later credit note does
     * not rebrand the original. Falls back to the order's creator for documents
     * printed before any invoice exists (quotation, order, picking list).
     */
    protected function saleDocProfile(?SaleOrderHeader $saleOrder): array
    {
        $issuerId = null;

        if ($saleOrder) {
            $issuerId = InvoiceHeader::where('sale_order_id', $saleOrder->id)
                ->orderBy('id')
                ->value('created_user_id');
        }

        $profile = PosProfile::forUser($issuerId ?: ($saleOrder->created_user_id ?? null));
        $info = $profile ? $profile->toArray() : [];
        $info['logo_url'] = PosProfileController::logoUrl();

        return $info;
    }
}
