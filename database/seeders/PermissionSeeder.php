<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed permission rows per section — only the actions that section
     * actually supports in the app (e.g. Exchange Rate has no create/delete,
     * it's only ever updated; Product/User have no delete route at all).
     * Idempotent (updateOrCreate) — safe to re-run when actions/sections change.
     * Any previously-seeded row for an action a section no longer supports
     * is removed (cascades to permission_user, so it silently drops from
     * any user who had it).
     */
    public function run(): void
    {
        $sectionLabels = [
            'pos_sale'        => 'POS / Sale',
            'warehouse'       => 'Manage Warehouse',
            'product'         => 'Manage Products',
            'category'        => 'Manage Categories',
            'quotation'       => 'Manage Quotes',
            'customer'        => 'Manage Customers',
            'purchasing'      => 'Purchasing',
            'vendor'          => 'Manage Vendors',
            'exchange_rate'   => 'Exchange Rate',
            'user'            => 'Manage Users',
            'company_profile' => 'Company Profile',
            'expense'         => 'Expenses',
            'report'          => 'Reports',
        ];

        // Only the actions each section genuinely has a route/guard for.
        // pos_sale and purchasing use domain-specific actions instead of generic
        // CRUD (e.g. "sell" / "edit_price" / "edit_discount" instead of "create") —
        // matches the real capabilities cashiers/purchasers are granted individually.
        // "report" bundles every report screen as its own individually-grantable
        // action instead of each report being its own top-level section — each
        // action is a distinct report a user can be allowed or not.
        $sectionActions = [
            'pos_sale'        => ['view', 'order', 'sell', 'edit_price', 'edit_discount', 'view_grid', 'view_list', 'mark_delivered'],
            'warehouse'       => ['view', 'create', 'edit', 'delete', 'adjustment', 'transfer', 'movement'],
            'product'         => ['view', 'create', 'edit'],
            'category'        => ['view', 'create', 'edit', 'delete'],
            'quotation'       => ['view', 'create', 'edit'],
            'customer'        => ['view', 'create', 'edit', 'delete'],
            'purchasing'      => ['view', 'purchase', 'purchase_return'],
            'vendor'          => ['view', 'create', 'edit'],
            'exchange_rate'   => ['view', 'edit'],
            'user'            => ['view', 'create', 'edit'],
            'company_profile' => ['view', 'edit'],
            // Recording an expense was previously ungated entirely — anyone who
            // could open the POS could book one. "report.expense" governs the
            // REPORT; these govern the feature itself.
            'expense'         => ['view', 'create', 'refund'],
            // "profit" is separate from "dashboard" so an admin can grant the
            // KPI/dashboard screens without exposing cost and margin figures.
            // "export" gates every Excel/CSV download across the app — a user can
            // be allowed to READ a report on screen without being able to take
            // the underlying data out of the building.
            'report'          => ['dashboard', 'sales', 'expense', 'stock', 'profit', 'export'],
        ];

        $actionLabels = [
            'view'            => 'View',
            'create'          => 'Create',
            'edit'            => 'Edit',
            'delete'          => 'Delete',
            'order'           => 'Create Order (No Stock Deduction)',
            'sell'            => 'Sell (Checkout)',
            'edit_price'      => 'Edit Price',
            'edit_discount'   => 'Edit Discount',
            'purchase'        => 'Purchase',
            'purchase_return' => 'Purchase Return',
            'mark_delivered'  => 'Mark All Delivered',
            'view_grid'       => 'Product View: Grid',
            'view_list'       => 'Product View: List',
            'adjustment'      => 'Stock Adjustment',
            'transfer'        => 'Transfer (Warehouse to Warehouse)',
            'movement'        => 'Movement (Bin to Bin)',
        ];

        // Overrides for the handful of "{action} {section}" combos that don't
        // read sensibly auto-generated.
        $labelOverrides = [
            'pos_sale.order'              => 'Create Order (Step 1 — No Stock Deduction Yet)',
            'pos_sale.sell'               => 'Sell (Checkout — Order + Deduct Stock in One Step)',
            'pos_sale.edit_price'         => 'Edit Price (POS)',
            'pos_sale.edit_discount'      => 'Edit Discount (POS)',
            'pos_sale.mark_delivered'     => 'Mark All Orders Delivered (bulk)',
            'pos_sale.view_grid'          => 'Product View: Grid (POS)',
            'pos_sale.view_list'          => 'Product View: List (POS)',
            'purchasing.purchase'         => 'Create Purchase',
            'purchasing.purchase_return'  => 'Purchase Return',
            'expense.view'                => 'View Expenses',
            'expense.create'              => 'Record Expense',
            'expense.refund'              => 'Refund Expense',
            'warehouse.adjustment'        => 'Stock Adjustment',
            'warehouse.transfer'          => 'Transfer (Different Warehouse)',
            'warehouse.movement'          => 'Movement (Same Warehouse, Between Bins)',
            'vendor.view'                 => 'View Vendors',
            'vendor.create'               => 'Create Vendor',
            'vendor.edit'                 => 'Edit Vendor',
            'report.dashboard'            => 'Dashboard / KPI',
            'report.sales'                => 'Sales Report',
            'report.expense'              => 'Expense Report',
            'report.stock'                => 'Stock In/Out Report',
            'report.profit'               => 'Profit / Cost Report',
            'report.export'               => 'Export to Excel / CSV',
        ];

        $keptIds = [];

        foreach ($sectionActions as $section => $actions) {
            foreach ($actions as $action) {
                $key = "{$section}.{$action}";
                $permission = Permission::updateOrCreate(
                    ['section' => $section, 'action' => $action],
                    [
                        'key'   => $key,
                        'label' => $labelOverrides[$key] ?? "{$actionLabels[$action]} {$sectionLabels[$section]}",
                    ]
                );
                $keptIds[] = $permission->id;
            }
        }

        Permission::whereNotIn('id', $keptIds)->delete();
    }
}
