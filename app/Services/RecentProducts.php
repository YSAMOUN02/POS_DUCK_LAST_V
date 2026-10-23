<?php

namespace App\Services;

use App\Models\InvoiceLine;
use Illuminate\Support\Collection;

/**
 * The "Recent" tab on the POS and purchasing product pickers.
 *
 * Replaces "Top Product", which ranked by all-time quantity sold — a list that
 * barely moves once a shop has some history. This ranks by the most recent
 * sale instead, so whatever is selling right now is one tap away.
 */
class RecentProducts
{
    /**
     * @param  Collection  $products  products already loaded for this user and
     *                                warehouse scope, each carrying total_stock
     * @return array  up to $limit products, in stock first, and newest sale
     *                first within each of the two groups
     */
    public static function from(Collection $products, int $limit = 30): array
    {
        $byId = $products->keyBy('id');

        // quantity > 0: a sale return is stored as a NEGATIVE sale line, and a
        // return is not a sale. Without this, refunding an item would put it at
        // the front of the list.
        $rows = InvoiceLine::query()
            ->select('product_id')
            ->selectRaw('MAX(created_at) as last_sold_at')
            ->whereNotNull('product_id')
            ->where('quantity', '>', 0)
            ->groupBy('product_id')
            ->orderByDesc('last_sold_at')
            ->limit($limit * 4)   // buffer: inactive / out-of-scope ids drop out below
            ->get();

        $inStock = [];
        $outOfStock = [];

        foreach ($rows as $row) {
            $product = $byId->get($row->product_id);
            if (! $product) {
                continue;   // inactive, or not sellable for this user
            }

            if ((float) ($product->total_stock ?? 0) > 0) {
                $inStock[] = $product;
            } else {
                $outOfStock[] = $product;
            }

            if (count($inStock) + count($outOfStock) >= $limit) {
                break;
            }
        }

        // A stable partition, not a sort by stock: inside each group the order
        // stays most-recent-first. Sorting by quantity would bury the item sold
        // a minute ago under whatever happens to have the most units on hand.
        return array_merge($inStock, $outOfStock);
    }
}
