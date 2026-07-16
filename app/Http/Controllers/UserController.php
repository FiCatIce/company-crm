<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesPermissionCatalog;
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
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

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
    use ProvidesPermissionCatalog;

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

        // Assign the role. A system role seeds its preset onto the user as direct
        // permissions (D6-A); a custom role carries its own permissions
        // (role_has_permissions), inherited via getAllPermissions() — nothing to
        // copy onto the user.
        $user->syncRoles([$data['role']]);
        if ($systemRole = RoleName::tryFrom($data['role'])) {
            $user->syncPermissions(RolePresets::permissions($systemRole));
        }

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
            ? ['value' => $slug, 'label' => $this->roleLabel($slug)]
            : null;
    }

    /**
     * UI label for a role slug — the enum's label for a system role (e.g.
     * supervisor → "Manager"), a headline-cased name for a custom role.
     */
    private function roleLabel(string $slug): string
    {
        return RoleName::tryFrom($slug)?->label() ?? Str::headline($slug);
    }

    /**
     * Role options for the create/edit dropdown — every role, system and custom.
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_values(
            Role::query()
                ->orderBy('name')
                ->pluck('name')
                ->map(fn (string $name) => ['value' => $name, 'label' => $this->roleLabel($name)])
                ->all()
        );
    }

    /**
     * Map of role slug → its template permission strings, so the Edit page can
     * reset the checklist when the dropdown changes: a system role's coded preset,
     * or a custom role's own permissions (role_has_permissions).
     *
     * @return array<string, list<string>>
     */
    private function rolePresetMap(): array
    {
        $map = [];

        foreach (Role::with('permissions:id,name')->get() as $role) {
            if ($systemRole = RoleName::tryFrom($role->name)) {
                $map[$role->name] = RolePresets::permissions($systemRole);

                continue;
            }

            // Custom role — its own permissions, intersected with the enum for a
            // clean, ordered list of valid permission strings.
            $held = $role->permissions->pluck('name')->all();
            $map[$role->name] = array_values(array_filter(
                PermissionName::values(),
                fn (string $permission): bool => in_array($permission, $held, true),
            ));
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
