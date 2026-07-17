<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesPermissionCatalog;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin role builder (extends the B5 user-management surface). Create, edit,
 * delete, and re-permission roles.
 *
 * ONLY the admin role is fully locked — never renamed, re-permissioned, or
 * deleted (privilege-escalation + lockout guard, mirroring B5's rule that an
 * admin can't edit their own role/permissions). Its preset is code
 * (App\Support\RolePresets), the single source of truth.
 *
 * The other system roles (supervisor/sales/cs/maintenance) are treated like
 * regular roles: an admin may rename them, change their permissions, and delete
 * them (unless in use). A rename/delete of one is warned about in the UI because
 * seeders reference the default slugs (App\Enums\RoleName). Editing a system
 * role "detaches" it from its code preset — the chosen permissions are stored on
 * the role (role_has_permissions) and pushed onto its members' direct permissions
 * (roles are templates; permissions live on the user — DESIGN_RBAC.md §3.4).
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
                'is_locked' => $this->isLocked($role->name),
                'users_count' => $role->users_count,
                'permissions_count' => count(RolePresets::effectivePermissions($role)),
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

        // DH4 capability config: which user types this role may create/assign.
        // Dormant until a member actually holds user.create/user.assign — the
        // capability check reads it then. Absent input stays null.
        /** @var list<string>|null $assignableTypes */
        $assignableTypes = $data['assignable_types'] ?? null;

        // Built via new+forceFill (not Role::create) so the concrete App\Models\Role
        // type — which carries the assignable_types cast — is what we hold.
        $role = new Role;
        $role->forceFill([
            'name' => $data['name'],
            'guard_name' => 'web',
            'assignable_types' => $assignableTypes,
        ])->save();
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
                'is_locked' => $this->isLocked($role->name),
                'permissions' => RolePresets::effectivePermissions($role),
                // DH4 capability config (surfaced for the H4 toggle UI).
                'assignable_types' => $role->assignable_types ?? [],
            ],
            'permissionGroups' => $this->permissionCatalog(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        // The admin role is the ONLY fully-locked role: never renamed or
        // re-permissioned (privilege-escalation + lockout guard). Every other
        // role — the remaining system roles included — is editable.
        if ($this->isLocked($role->name)) {
            return back()->with('error', 'Role admin terkunci dan tidak bisa diubah.');
        }

        $data = $request->validated();
        $before = RolePresets::effectivePermissions($role);

        // A system role's members hold their permissions DIRECTLY (roles are
        // templates — DESIGN_RBAC.md §3.4), so an edit must be pushed onto them;
        // a custom role's members inherit from the role and need no push. Decide
        // on the ORIGINAL slug, before any rename below.
        $pushToMembers = $this->isSystem($role->name);

        $changes = [];

        if ($role->name !== $data['name']) {
            $changes['name'] = ['from' => $role->name, 'to' => $data['name']];
            $role->name = $data['name'];
            $role->save();
        }

        $after = array_values(array_unique($data['permissions']));

        // Persist the chosen permissions on the role itself. For a custom role this
        // drives inheritance; for an edited system role it "detaches" the role from
        // its code preset so the admin's choice sticks (RolePresets::effectivePermissions).
        $role->syncPermissions($after);

        // DH4 capability config (only when submitted, so a permission-only edit
        // doesn't wipe it). Which user types this role may create/assign.
        if ($request->has('assignable_types')) {
            /** @var list<string>|null $assignableTypes */
            $assignableTypes = $data['assignable_types'] ?? null;
            $role->assignable_types = $assignableTypes;
            $role->save();
        }

        // Re-stamp system-role members' direct permissions so the change — additions
        // AND removals — takes effect immediately, replacing the preset they were
        // provisioned with. (Membership follows the role row, so the post-rename
        // name still resolves them.)
        if ($pushToMembers) {
            User::role($role->name)->get()
                ->each(fn (User $member) => $member->syncPermissions($after));
        }

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

        // Only the admin role is undeletable (privilege-escalation / lockout guard).
        if ($this->isLocked($role->name)) {
            return back()->with('error', 'Role admin terkunci dan tidak bisa dihapus.');
        }

        // Don't strand users on a role that's about to vanish — make the admin
        // reassign them first. Applies to EVERY role, system or custom.
        $inUse = $role->users()->count();
        if ($inUse > 0) {
            return back()->with('error', "Role \"{$role->name}\" masih dipakai {$inUse} user. Pindahkan dulu ke role lain sebelum menghapus.");
        }

        AuditLog::record($request->user(), null, 'role.deleted', [
            'role' => $role->name,
            'permissions' => ['added' => [], 'removed' => RolePresets::effectivePermissions($role)],
        ]);

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" berhasil dihapus.");
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
     * Whether this is one of the five code-defined system roles (App\Enums\RoleName).
     * System roles are still editable (except admin) — the flag only drives the UI
     * warning and the "push the edit onto members' direct permissions" behaviour.
     */
    private function isSystem(string $name): bool
    {
        return in_array($name, RoleName::values(), true);
    }

    /**
     * Whether this role is fully locked. ONLY the admin role is — it can never be
     * renamed, re-permissioned, or deleted (privilege-escalation + lockout guard,
     * mirroring B5's no-self-edit rule).
     */
    private function isLocked(string $name): bool
    {
        return $name === RoleName::Admin->value;
    }

    /**
     * UI label — the enum's label for a system role, a headline-cased name for a
     * custom one.
     */
    private function roleLabel(string $name): string
    {
        return RoleName::tryFrom($name)?->label() ?? Str::headline($name);
    }
}
