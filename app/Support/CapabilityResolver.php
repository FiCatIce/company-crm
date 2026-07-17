<?php

namespace App\Support;

use App\Enums\PermissionName as P;
use App\Models\Role;
use App\Models\User;

/**
 * Central authority for the delegated capability model (DESIGN_HIERARCHY.md DH4):
 * "who may CREATE or ASSIGN which user types". Read by User::canCreateUserType()/
 * canAssign(), UserPolicy, and the delegated-creation/assignment services.
 *
 * The type whitelist lives on each role (roles.assignable_types, set by an admin
 * in the role builder), falling back to config/hierarchy.php defaults when never
 * explicitly set — so the capability behaves correctly before any admin override.
 *
 * SECURITY: this is the escalation boundary. A delegate (a non-admin creator) may
 * never mint or assign a role that itself wields user-administration power — even
 * if such a type were (mis)configured into a whitelist.
 */
final class CapabilityResolver
{
    /**
     * Permissions that make a role a "user administrator" (can create/manage users
     * or grant roles/permissions). A delegate may never create such a role.
     *
     * Deliberately EXCLUDES user.assign: assigning existing support to oneself
     * grants no power to create users or hand out permissions, so it is not an
     * escalation vector.
     *
     * @var list<P>
     */
    private const ADMIN_POWERS = [
        P::UserView, P::UserCreate, P::UserUpdate, P::UserDelete,
        P::RoleAssign, P::PermissionAssign, P::RoleManage,
    ];

    /**
     * The user types (role slugs) $actor's role may create/assign. Reads the
     * role's assignable_types column; when it was never set (null), falls back to
     * the config default for that slug. An explicit empty array means "nothing"
     * and is respected (no fallback).
     *
     * @return list<string>
     */
    public static function assignableTypes(User $actor): array
    {
        $role = $actor->roles->first();

        if (! $role instanceof Role) {
            return [];
        }

        $explicit = $role->assignable_types;
        if ($explicit !== null) {
            return array_values($explicit);
        }

        /** @var array<string, list<string>> $defaults */
        $defaults = config('hierarchy.assignable_types', []);

        return $defaults[$role->name] ?? [];
    }

    /**
     * The concrete user types $actor may create via DELEGATION (the team-member
     * form dropdown, H4). Its whitelist filtered to types that actually pass
     * canCreateUserType — so an escalation-guarded slug never reaches the dropdown.
     *
     * The unrestricted admin returns [] here on purpose: admin creates users
     * through the full /users UI, not the delegated team form, so a role with no
     * assignable_types whitelist (admin's default) has nothing to delegate. This is
     * exactly what separates a manager (non-empty) from an admin (empty) for the
     * team-area gate.
     *
     * @return list<string>
     */
    public static function creatableTypes(User $actor): array
    {
        if (! $actor->can(P::UserCreate->value)) {
            return [];
        }

        return array_values(array_filter(
            self::assignableTypes($actor),
            fn (string $type): bool => self::canCreateUserType($actor, $type),
        ));
    }

    /**
     * May $actor CREATE a user of role $type? Requires user.create, and either
     * unrestricted user-administration (admin) or $type in the actor's whitelist —
     * but NEVER a role that itself wields user-administration power (escalation).
     */
    public static function canCreateUserType(User $actor, string $type): bool
    {
        if (! $actor->can(P::UserCreate->value)) {
            return false;
        }

        // Minting another user-administrator is reserved for the unrestricted admin
        // (and happens through the admin user-management UI, not delegation).
        if (self::isAdminPowerRole($type)) {
            return self::isUnrestricted($actor);
        }

        if (self::isUnrestricted($actor)) {
            return true;
        }

        return in_array($type, self::assignableTypes($actor), true);
    }

    /**
     * May $actor ASSIGN an existing user of role $type to themselves (support
     * assignment, DH5)? Requires user.assign and $type in the whitelist; never an
     * admin-power role.
     */
    public static function canAssign(User $actor, string $type): bool
    {
        if (! $actor->can(P::UserAssign->value)) {
            return false;
        }

        if (self::isAdminPowerRole($type)) {
            return false;
        }

        return in_array($type, self::assignableTypes($actor), true);
    }

    /**
     * The unrestricted user-administrator (admin): may create any role and set
     * permissions. Marked by permission.assign — the grant-anything power that a
     * delegated creator never holds.
     */
    public static function isUnrestricted(User $actor): bool
    {
        return $actor->can(P::PermissionAssign->value);
    }

    /**
     * Whether a role slug's EFFECTIVE permissions include any user-administration
     * power — so delegated creation/assignment of it is forbidden.
     */
    private static function isAdminPowerRole(string $slug): bool
    {
        $role = Role::where('name', $slug)->with('permissions')->first();

        if (! $role instanceof Role) {
            // Unknown type: nothing to create/assign — treat as non-privileged so
            // the whitelist check (which it will also fail) is the decider.
            return false;
        }

        $effective = RolePresets::effectivePermissions($role);

        foreach (self::ADMIN_POWERS as $power) {
            if (in_array($power->value, $effective, true)) {
                return true;
            }
        }

        return false;
    }
}
