<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesPermissionCatalog;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\AuditLog;
use App\Support\RolePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Admin role builder (extends the B5 user-management surface). Create, edit, and
 * delete CUSTOM roles and set their permission templates.
 *
 * The five system roles (App\Enums\RoleName) are LOCKED here — visible so an
 * admin can see what they grant, but never renamed, re-permissioned, or deleted;
 * their presets are code (App\Support\RolePresets), the single source of truth.
 *
 * A custom role stores its permissions ON the role (role_has_permissions), so a
 * user assigned it inherits them through Spatie getAllPermissions() — the same
 * effective-permission API every policy, model scope, and the nav/dashboard
 * gating already read, so custom roles "just work" with no change to enforcement.
 *
 * Gated by role.manage (admin only). No Gate::before bypass.
 */
class RoleController extends Controller
{
    use ProvidesPermissionCatalog;

    public function index(Request $request): Response
    {
        $this->authorizeManage($request);

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->roleLabel($role->name),
                'is_system' => $this->isSystem($role->name),
                'users_count' => $role->users_count,
                'permissions_count' => count($this->permissionsFor($role)),
            ])
            ->all();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Roles/Create', [
            'permissionGroups' => $this->permissionCatalog(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);

        AuditLog::record($request->user(), null, 'role.created', [
            'role' => $role->name,
            'permissions' => ['added' => $data['permissions'], 'removed' => []],
        ]);

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" berhasil dibuat.");
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->roleLabel($role->name),
                'is_system' => $this->isSystem($role->name),
                'permissions' => $this->permissionsFor($role),
            ],
            'permissionGroups' => $this->permissionCatalog(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        // System roles are locked — their preset is code, not editable here. (The
        // request also refuses a system name, but guard explicitly for a clear msg.)
        if ($this->isSystem($role->name)) {
            return back()->with('error', 'Role sistem tidak bisa diubah.');
        }

        $data = $request->validated();
        $before = $this->permissionsFor($role);
        $changes = [];

        if ($role->name !== $data['name']) {
            $changes['name'] = ['from' => $role->name, 'to' => $data['name']];
            $role->name = $data['name'];
            $role->save();
        }

        $after = array_values(array_unique($data['permissions']));
        $role->syncPermissions($after);

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        if ($added !== [] || $removed !== []) {
            $changes['permissions'] = ['added' => $added, 'removed' => $removed];
        }

        if ($changes !== []) {
            AuditLog::record($request->user(), null, 'role.updated', ['role' => $role->name, ...$changes]);
        }

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" berhasil diperbarui.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($this->isSystem($role->name)) {
            return back()->with('error', 'Role sistem tidak bisa dihapus.');
        }

        // Don't strand users on a role that's about to vanish — make the admin
        // reassign them first.
        if ($role->users()->exists()) {
            return back()->with('error', 'Role masih dipakai user. Pindahkan user-nya dulu sebelum menghapus.');
        }

        AuditLog::record($request->user(), null, 'role.deleted', [
            'role' => $role->name,
            'permissions' => ['added' => [], 'removed' => $this->permissionsFor($role)],
        ]);

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    /**
     * The role builder is admin-only; there is no per-target nuance, so a flat
     * permission check (mirrors DashboardController) rather than a spatie-Role
     * policy that would need explicit registration.
     */
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can(PermissionName::RoleManage->value), 403);
    }

    /**
     * Whether this is one of the five locked system roles.
     */
    private function isSystem(string $name): bool
    {
        return in_array($name, RoleName::values(), true);
    }

    /**
     * UI label — the enum's label for a system role, a headline-cased name for a
     * custom one.
     */
    private function roleLabel(string $name): string
    {
        return RoleName::tryFrom($name)?->label() ?? Str::headline($name);
    }

    /**
     * The permission strings a role grants: a system role's coded preset, or a
     * custom role's own attached permissions (role_has_permissions).
     *
     * @return list<string>
     */
    private function permissionsFor(Role $role): array
    {
        if ($systemRole = RoleName::tryFrom($role->name)) {
            return RolePresets::permissions($systemRole);
        }

        // A custom role's own permissions, intersected with the enum so the result
        // is a clean, deterministically-ordered list of valid permission strings.
        $held = $role->permissions->pluck('name')->all();

        return array_values(array_filter(
            PermissionName::values(),
            fn (string $permission): bool => in_array($permission, $held, true),
        ));
    }
}
