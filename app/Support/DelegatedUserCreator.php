<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Delegated user creation (DESIGN_HIERARCHY.md DH4/DH2). A manager creates a team
 * member of a WHITELISTED type only; the new user's permissions come from the role
 * preset (a delegate never sets permissions directly — the admin owns templates),
 * and the user is stamped with the creator (created_by_user) and joins the
 * creator's team.
 *
 * Backend logic + enforcement only — the UI lands in H4. The capability check is
 * the security boundary; it runs before anything is written.
 */
final class DelegatedUserCreator
{
    /**
     * @param  array{name: string, email: string, extension?: string|null, password: string}  $data
     *
     * @throws AuthorizationException when $creator may not create a $type user
     */
    public static function create(User $creator, string $type, array $data): User
    {
        if (! CapabilityResolver::canCreateUserType($creator, $type)) {
            throw new AuthorizationException("Tidak boleh membuat user tipe \"{$type}\".");
        }

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->extension = $data['extension'] ?? null;
        $user->password = $data['password']; // hashed by the model's 'hashed' cast
        $user->created_by_user = $creator->id; // delegated-creation trail (DH4)
        $user->save();

        // Role + preset permissions. A system role seeds its template onto the user
        // as direct permissions; a custom role carries its own (role_has_permissions),
        // inherited via getAllPermissions() — nothing to copy. Mirrors UserController.
        $user->syncRoles([$type]);
        if (RoleName::tryFrom($type) !== null) {
            $role = Role::where('name', $type)->with('permissions')->first();
            if ($role !== null) {
                $user->syncPermissions(RolePresets::effectivePermissions($role));
            }
        }

        // DH2: the new member permanently joins the creator's team (if any).
        $team = $creator->team();
        if ($team !== null) {
            $team->members()->syncWithoutDetaching([$user->id => ['role_in_team' => $type]]);
        }

        AuditLog::record($creator, $user, 'user.created.delegated', [
            'role' => $type,
            'team_id' => $team?->id,
        ]);

        return $user;
    }
}
