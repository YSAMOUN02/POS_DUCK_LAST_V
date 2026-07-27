<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /**
     * Mirrors the restriction the list/search queries already apply: a cashier
     * may only touch customers they created; admin and supervisor see all
     * (customers have no warehouse, so there is no location dimension here).
     *
     * `search()` and `list_search()` filtered by created_by, but show/update/
     * destroy were route-model-bound with no check at all — so a sequential id
     * was enough to read, rewrite or delete another cashier's customer.
     *
     * Note: customers.created_by stores the user *id* (not the username, unlike
     * some other tables in this schema).
     */
    private function authorizeCustomerAccess(Customer $customer): void
    {
        if (Auth::user()?->role !== 'cashier') {
            return;
        }

        abort_unless((string) $customer->created_by === (string) Auth::id(), 403);
    }




    public function store(Request $request)
    {
        try {
            // 🔥 only required
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // 🔢 generate customer_code
            $lastCustomer = Customer::whereNotNull('customer_code')
                ->where('customer_code', 'REGEXP', '^C[0-9]+$')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastCustomer && preg_match('/^C(\d+)$/', $lastCustomer->customer_code, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            } else {
                $nextNumber = 1;
            }

            // 🧠 only take needed fields (avoid _token crash)
            $data = $request->only([
                'name',
                'phone',
                'email',
                'address1',
                'address2',
                'city',
                'country',
                'contact_name',
                'contact_phone',
                'type',
                'discount_percent',
                'point',
            ]);

            // 🏷️ assign values
            $data['customer_code'] = 'C' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $data['type'] = $data['type'] ?? 'walk_in';
            $data['discount_percent'] = $data['discount_percent'] ?? 0;
            $data['point'] = $data['point'] ?? 0;

            // ✅ FIX checkbox (no more "on" error 💀)
            $data['status'] = $request->has('status') ? 1 : 0;

            // 👤 created by
            $data['created_by'] = Auth::user()->id ?? 'System';

            // 💾 save
            $customer = Customer::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'customer' => $customer,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Create failed',
                'error' => $e->getMessage(), // 🔥 keep this for debug
            ], 500);
        }
    }
public function search(Request $request)
{
    $query = $request->get('q', '');

    $sql = Customer::where(function ($q2) use ($query) {
        $q2->where('customer_code', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%");
    });

    if (Auth::user()->role == 'cashier') {
        $sql->where('created_by', Auth::id());
    }

    $customers = $sql->limit(15)
        ->get([
            'id',
            'customer_code',
            'name',
            'discount_percent',
            'address1',
            'phone'
        ]);

    return response()->json($customers);
}
    public function list_search(Request $request)
    {
        $limit = $request->query('limit', 20);

        $query = Customer::query();

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('customer_code', 'like', "%$s%")
                    ->orWhere('name', 'like', "%$s%")
                    ->orWhere('phone', 'like', "%$s%")
                    ->orWhere('email', 'like', "%$s%");
            });
        }
        // A cashier only ever sees the customers they created; for anyone else
        // (admin / supervisor) the Seller dropdown can narrow the list down to a
        // single cashier. customers.created_by holds the user id, not the
        // username, so it compares against the id directly.
        if (Auth::user()->role == 'cashier') {
           $query->where('created_by', Auth::user()->id);
        } elseif ($request->filled('user_id')) {
            $query->where('created_by', $request->user_id);
        }


        // Filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        if ($request->filled('sort_by') && in_array($request->sort_by, [
            'id',
            'customer_code',
            'name',
            'phone',
            'email',
            'address1',
            'address2',
            'contact',
            'contact_phone',
            'type',
            'discount_percent',
            'balance',
            'point',
            'status'
        ])) {
            $dir = $request->query('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->sort_by, $dir);
        } else {
            $query->orderBy('id', 'desc');
        }

        return response()->json($query->paginate($limit));
    }
    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCustomerAccess($customer);

        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',

                'phone' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',

                'address1' => 'nullable|string|max:255',
                'address2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',

                'type' => 'nullable|in:walk_in,member,vip',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'point' => 'nullable|integer|min:0',
                'status' => 'nullable|in:0,1',
            ]);

            $customer->update($data);

            return response()->json([
                'message' => 'Customer updated successfully',
                'customer' => $customer->fresh(),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Customer $customer)
    {
        $this->authorizeCustomerAccess($customer);

        return response()->json([
            'customer' => $customer
        ]);
    }
    // DELETE (you already have this)
    public function destroy(Customer $customer)
    {
        $this->authorizeCustomerAccess($customer);

        try {
            $customer->delete();
            return response()->json([
                'message' => 'Customer deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
