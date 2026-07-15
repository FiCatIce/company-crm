<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin user management (DESIGN_RBAC.md §3.5 / batch B5). Create users, assign a
 * role (which seeds a preset), and toggle individual permissions in either
 * direction — roles are templates, direct permissions are the effective truth
 * (D6). Role/permission changes are forbidden on oneself (D2) and every one is
 * written to the audit log.
 */
class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'extension' => $user->extension,
                'role' => $this->roleView($user),
                'is_self' => $user->id === $actor->id,
                'can_delete' => $actor->can('delete', $user),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'can' => [
                'create' => $actor->can('create', User::class),
                'update' => $actor->can('update', User::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->extension = $data['extension'] ?? null;
        $user->password = $data['password']; // hashed by the model's 'hashed' cast
        $user->save();

        // Role seeds the preset as direct permissions (the one provisioning path).
        RolePresets::assign($user, RoleName::from($data['role']));

        AuditLog::record($request->user(), $user, 'user.created', ['role' => $data['role']]);

        return redirect()->route('users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $actor = $request->user();

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'extension' => $user->extension,
                'role' => $this->roleSlug($user),
                'permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
            ],
            'roles' => $this->roleOptions(),
            'permissionGroups' => $this->permissionCatalog(),
            'rolePresets' => $this->rolePresetMap(),
            'isSelf' => $actor->id === $user->id,
            'can' => [
                // D2: role/permission editing is disabled on oneself in the UI, and
                // re-checked server-side in update(). Profile edits are always allowed.
                'assignRole' => $actor->can('assignRole', $user),
                'managePermissions' => $actor->can('managePermissions', $user),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->extension = $data['extension'] ?? null;
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        $changes = [];

        // Role change — only when permitted for this target (never self, D2).
        if ($actor->can('assignRole', $user)) {
            $oldRole = $this->roleSlug($user);
            $newRole = $data['role'];

            if ($oldRole !== $newRole) {
                $user->syncRoles([$newRole]);
                $changes['role'] = ['from' => $oldRole, 'to' => $newRole];
            }
        }

        // Direct-permission toggle — the two-way grant/revoke (D6), also self-guarded.
        // Keyed on an explicit flag so clearing every box (revoke-all) still syncs,
        // rather than being indistinguishable from "permissions not submitted".
        if ($actor->can('managePermissions', $user) && $request->boolean('manage_permissions')) {
            $before = $user->getDirectPermissions()->pluck('name')->all();
            $after = array_values(array_unique($data['permissions'] ?? []));

            $user->syncPermissions($after);

            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));

            if ($added !== [] || $removed !== []) {
                $changes['permissions'] = ['added' => $added, 'removed' => $removed];
            }
        }

        if ($changes !== []) {
            AuditLog::record($actor, $user, 'user.updated', $changes);
        }

        return redirect()->route('users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Policy already forbids deleting oneself; this adds the last-admin guard.
        $this->authorize('delete', $user);

        if ($this->isLastAdmin($user)) {
            return back()->with('error', 'Tidak dapat menghapus admin terakhir.');
        }

        // Capture identity in the diff: target_user_id is null-on-delete, so it is
        // wiped once the row is gone — the changes payload keeps the trail intact.
        AuditLog::record($request->user(), $user, 'user.deleted', [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'role' => $this->roleSlug($user),
        ]);

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * The user's role slug, or null if they have none.
     */
    private function roleSlug(User $user): ?string
    {
        $role = $user->getRoleNames()->first();

        return is_string($role) ? $role : null;
    }

    /**
     * The user's role as a {value,label} pair for display, or null.
     *
     * @return array{value: string, label: string}|null
     */
    private function roleView(User $user): ?array
    {
        $slug = $this->roleSlug($user);

        return $slug !== null
            ? ['value' => $slug, 'label' => RoleName::from($slug)->label()]
            : null;
    }

    /**
     * Role options for the create/edit dropdown (slug + UI label, e.g. Manager).
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_map(
            fn (RoleName $role) => ['value' => $role->value, 'label' => $role->label()],
            RoleName::cases(),
        );
    }

    /**
     * The full permission catalog grouped by domain, each flagged sensitive or
     * not — drives the grouped checklist + warning badges on the Edit page.
     *
     * @return list<array{group: string, permissions: list<array{name: string, label: string, sensitive: bool}>}>
     */
    private function permissionCatalog(): array
    {
        $groups = [];

        foreach (PermissionName::cases() as $permission) {
            $groups[$permission->group()][] = [
                'name' => $permission->value,
                'label' => $permission->label(),
                'sensitive' => $permission->sensitive(),
            ];
        }

        return array_map(
            fn (string $group, array $permissions) => ['group' => $group, 'permissions' => $permissions],
            array_keys($groups),
            array_values($groups),
        );
    }

    /**
     * Map of role slug → its preset permission strings, so the Edit page can reset
     * the checklist to a role's template when the dropdown changes.
     *
     * @return array<string, list<string>>
     */
    private function rolePresetMap(): array
    {
        $map = [];

        foreach (RoleName::cases() as $role) {
            $map[$role->value] = RolePresets::permissions($role);
        }

        return $map;
    }

    /**
     * Whether deleting this user would remove the last remaining admin.
     */
    private function isLastAdmin(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value)
            && User::role(RoleName::Admin->value)->count() <= 1;
    }
}
