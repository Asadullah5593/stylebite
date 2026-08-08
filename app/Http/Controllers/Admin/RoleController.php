<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * The admin role is the canonical "everything" role — rename, permission
     * edits, and deletion are blocked so a slip can never brick the panel.
     */
    private const LOCKED_ROLES = ['admin'];

    /**
     * Seeded roles that mirror the users.role enum column. Their permissions
     * can be tuned, but name and existence are fixed.
     */
    private const SEEDED_ROLES = ['admin', 'moderator', 'creator', 'user'];

    public function index(): View
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.RolesPage', [
            'roles' => $roles,
            'lockedRoles' => self::LOCKED_ROLES,
            'seededRoles' => self::SEEDED_ROLES,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.RoleFormPage', [
            'role' => null,
            'rolePermissions' => [],
            'permissionGroups' => $this->permissionGroups(),
            'locked' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_\- ]+$/i',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ], [
            'name.regex' => 'Role names may only contain letters, numbers, spaces, dashes and underscores.',
            'permissions.required' => 'Select at least one permission for this role.',
        ]);

        $role = Role::create([
            'name' => Str::lower($data['name']),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions']);

        $this->logActivity('role_created', $role->id, [
            'name' => $role->name,
            'permissions' => $data['permissions'],
        ]);

        return redirect()
            ->route('admin.roles.index')
            ->with('status', "Role \"{$role->name}\" created successfully.");
    }

    public function edit(Role $role): View
    {
        abort_unless($role->guard_name === 'web', 404);

        return view('admin.roles.RoleFormPage', [
            'role' => $role,
            'rolePermissions' => $role->permissions->pluck('name')->all(),
            'permissionGroups' => $this->permissionGroups(),
            'locked' => in_array($role->name, self::LOCKED_ROLES, true),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        if (in_array($role->name, self::LOCKED_ROLES, true)) {
            return back()->with('status', "The \"{$role->name}\" role is locked and always keeps every permission.");
        }

        $isSeeded = in_array($role->name, self::SEEDED_ROLES, true);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_\- ]+$/i',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ], [
            'name.regex' => 'Role names may only contain letters, numbers, spaces, dashes and underscores.',
            'permissions.required' => 'Select at least one permission for this role.',
        ]);

        // Seeded roles mirror the users.role enum, so their names are fixed.
        $newName = $isSeeded ? $role->name : Str::lower($data['name']);

        $role->name = $newName;
        $role->save();
        $role->syncPermissions($data['permissions']);

        $this->logActivity('role_updated', $role->id, [
            'name' => $role->name,
            'permissions' => $data['permissions'],
        ]);

        return redirect()
            ->route('admin.roles.index')
            ->with('status', "Role \"{$role->name}\" updated successfully.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        if (in_array($role->name, self::SEEDED_ROLES, true)) {
            return back()->with('status', "The \"{$role->name}\" role is built in and cannot be deleted.");
        }

        $assigned = $role->users()->count();

        if ($assigned > 0) {
            return back()->with('status', "Cannot delete \"{$role->name}\" — {$assigned} user(s) still hold it. Reassign them first.");
        }

        $name = $role->name;
        $role->delete();

        $this->logActivity('role_deleted', null, ['name' => $name]);

        return redirect()
            ->route('admin.roles.index')
            ->with('status', "Role \"{$name}\" deleted successfully.");
    }

    /**
     * Permissions grouped by module for the checkbox grid:
     * ['users' => [['name' => 'users.view', 'action' => 'view'], …], …]
     */
    private function permissionGroups(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'))
            ->map(fn ($group) => $group->map(fn (Permission $permission) => [
                'name' => $permission->name,
                'action' => Str::after($permission->name, '.'),
            ])->values()->all())
            ->sortKeys()
            ->all();
    }

    private function logActivity(string $eventName, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => 'role',
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
