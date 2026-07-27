<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function userListData(Request $request)
    {
        $query = \App\Models\User::query();

        // Search text
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->filled('role') && $request->role !== 'All') {
            $query->where('role', $request->role);
        }

        if ($request->active != 'All') {
            $active = $request->active == '1' ? 1 : 0;
            $query->where('status', $active);
        }

        $users = $query->orderBy('id', 'desc')->get();

        return response()->json($users);
    }
    /**
     * Creating and editing accounts is admin-only, and `role` is whitelisted.
     *
     * `role` used to be validated as a free-form string with no check on who was
     * calling, so anyone holding user.create / user.edit could post role=admin
     * and mint themselves a super-user — User::hasPermission() returns true
     * unconditionally for admins, so that single field bypassed all 46
     * permissions. The permission alone must not be enough to grant a role
     * higher than your own.
     */
    private const ASSIGNABLE_ROLES = ['admin', 'supervisor', 'cashier'];

    private function authorizeUserAdministration(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403, 'Only an administrator may manage user accounts.');
    }

    public function store_user(Request $request)
    {
        $this->authorizeUserAdministration();

        try {
            $request->validate([
                'display_name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users,username',
                'role' => ['required', 'string', Rule::in(self::ASSIGNABLE_ROLES)],
                'email' => 'required|email|unique:users,email',
                'warehouses' => 'nullable|array',
                'warehouses.*' => 'exists:warehouses,id',
                'permissions' => 'nullable|array',
                'permissions.*' => 'exists:permissions,id',
                'password' => 'required|string|min:4', // ✅ make required
            ]);

            $user = User::create([
                'name' => $request->display_name,
                'username' => $request->username,
                'role' => $request->role,
                'email' => $request->email,
                'password' => Hash::make($request->password), // ✅ always valid now
                'status' => 1,
                'created_by'  => Auth::user()->name ?? 'System',
            ]);

            // ✅ Better way using relationship (recommended)
            if ($request->filled('warehouses')) {
                $user->warehouses()->sync($request->warehouses);
            }

            $this->syncPermissionsAndLog($user, $request->input('permissions', []));

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function get_warehouse_list()
    {
        $warehouse =  Warehouse::select('id', 'name', 'location')
            ->get();

        return response()->json($warehouse);
    }

    public function show($id)
    {
        $user = User::with(['warehouses:id', 'permissions:id'])->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'id' => $user->id,
            'display_name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'warehouses' => $user->warehouses->pluck('id'),
            'permissions' => $user->permissions->pluck('id'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeUserAdministration();

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $request->validate([
            'display_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'role' => ['required', 'string', Rule::in(self::ASSIGNABLE_ROLES)],
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:4',
            'warehouses' => 'nullable|array',
            'warehouses.*' => 'exists:warehouses,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Editing yourself must not be able to drop your own admin rights or
        // deactivate your own login — `status` defaults to 0 whenever the
        // checkbox is absent from the payload, which made self-lockout a
        // one-request accident.
        $isSelf = (int) $user->id === (int) Auth::id();

        try {
            $data = [
                'name' => $request->display_name,
                'username' => $request->username,
                'role' => $isSelf ? $user->role : $request->role,
                'email' => $request->email,
                'status' => $isSelf ? 1 : ($request->has('status') ? 1 : 0),
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            $user->warehouses()->sync($request->input('warehouses', []));

            $this->syncPermissionsAndLog($user, $request->input('permissions', []));

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync a user's permission checkboxes and write a manual activity-log
     * row for it — sync() on a belongsToMany pivot never fires model events,
     * so LogsActivity's create/update hooks on User can't see this change.
     */
    private function syncPermissionsAndLog(User $user, array $permissionIds): void
    {
        $before = $user->permissions()->pluck('key')->sort()->values()->all();

        $user->permissions()->sync($permissionIds);

        $after = Permission::whereIn('id', $permissionIds)->pluck('key')->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        ActivityLog::create([
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name ?? 'System',
            'action'     => 'permissions_synced',
            'model_type' => 'User',
            'model_id'   => $user->id,
            'section'    => 'user',
            'old_values' => ['permissions' => $before],
            'new_values' => ['permissions' => $after],
            'ip_address' => request()?->ip(),
        ]);
    }
}
