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
    public const ADMIN_POWERS = [
        P::UserView, P::UserCreate, P::UserUpdate, P::UserDelete,
        P::RoleAssign, P::PermissionAssign, P::RoleManage,
    ];

    /**
     * Permissions that WIDEN data reach beyond the holder's own book. A delegate may
     * never mint or assign someone who holds one of these that they lack themselves
     * — otherwise a manager could mint org-wide readers at will and every bit of
     * cross-team isolation built in H1–H7 would be bypassable through delegation.
     *
     * Only genuinely BROADENING tiers belong here. `customer.view.assigned` does NOT:
     * it is a different axis, not a wider one (a CS sees the books of the reps who
     * assigned them, which is narrower than a team roll-up), and treating it as
     * "more" would block a manager from creating CS/maintenance at all — the H4 flow.
     * `revenue.view` does not either: since H7d it is scoped by the holder's own
     * transaction tier, so it widens nothing on its own.
     *
     * @var list<P>
     */
    public const DATA_POWERS = [
        P::CustomerViewAll, P::CustomerViewTeam,
        P::TransactionViewAll, P::InteractionViewAll,
    ];

    /**
     * Whether $actor is at least as powerful as $target — i.e. $target holds no
     * administrative power that $actor lacks.
     *
     * The rule the delegated model already runs on (H2), applied to EDITING rather
     * than creating: you may never act on someone who outranks you. Without it,
     * `user.update` alone was a full takeover primitive — its policy accepted a
     * target and ignored it, so any holder could rewrite the admin's password (or
     * their email, and then use the password-reset mail flow) and sign in as them.
     * No shipped preset grants user.update outside admin, but the role builder can
     * mint one, which makes this a real gap rather than a theoretical one.
     *
     * Ties pass: identical power means neither outranks the other, and an actor
     * always outranks themselves.
     */
    public static function outranks(User $actor, User $target): bool
    {
        foreach (self::ADMIN_POWERS as $power) {
            if ($target->can($power->value) && ! $actor->can($power->value)) {
                return false;
            }
        }

        return true;
    }

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

        if (! in_array($type, self::assignableTypes($actor), true)) {
            return false;
        }

        // Finding #5: the whitelist alone was not enough. An admin could place a
        // custom role holding customer.view.all into a manager's assignable_types,
        // and the manager could then mint org-wide readers — bypassing every bit of
        // cross-team isolation from H1–H7 through the delegation path.
        return ! self::exceedsDataReach($actor, $type);
    }

    /**
     * The user types $actor may ASSIGN to themselves as support (the H5 candidate
     * pool's type filter). Its whitelist filtered to types that pass canAssign, so
     * an admin-power slug never reaches the picker.
     *
     * @return list<string>
     */
    public static function assignableCandidateTypes(User $actor): array
    {
        if (! $actor->can(P::UserAssign->value)) {
            return [];
        }

        return array_values(array_filter(
            self::assignableTypes($actor),
            fn (string $type): bool => self::canAssign($actor, $type),
        ));
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

        if (! in_array($type, self::assignableTypes($actor), true)) {
            return false;
        }

        // Same data-reach bound as creation (#5): pulling in a support agent whose
        // role sees more than the rep does would widen reach by the back door.
        return ! self::exceedsDataReach($actor, $type);
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
     * The powers role $slug carries that $grantorPermissions does not — the single
     * "you may not hand out what you do not hold" computation, in BOTH dimensions.
     *
     * Shared by the runtime guard (delegated create/assign) and the role builder's
     * validation, so the configuration screen refuses the same combinations the
     * runtime would, instead of letting an admin save a whitelist that silently
     * never works.
     *
     * @param  list<string>  $grantorPermissions
     * @return list<string> the offending permission names, empty when within reach
     */
    public static function excessPowersFor(array $grantorPermissions, string $slug): array
    {
        $role = Role::where('name', $slug)->with('permissions')->first();

        if (! $role instanceof Role) {
            return [];
        }

        $effective = RolePresets::effectivePermissions($role);
        $excess = [];

        foreach ([...self::ADMIN_POWERS, ...self::DATA_POWERS] as $power) {
            if (in_array($power->value, $effective, true)
                && ! in_array($power->value, $grantorPermissions, true)) {
                $excess[] = $power->value;
            }
        }

        return array_values(array_unique($excess));
    }

    /**
     * Whether role $slug reaches further into the DATA than $actor does — the
     * finding-#5 guard. Mirrors the finding-#1 rule (outranks) on the other axis:
     * you may not create or assign someone who can see more than you can.
     */
    private static function exceedsDataReach(User $actor, string $slug): bool
    {
        $role = Role::where('name', $slug)->with('permissions')->first();

        if (! $role instanceof Role) {
            return false;
        }

        $effective = RolePresets::effectivePermissions($role);

        foreach (self::DATA_POWERS as $power) {
            if (in_array($power->value, $effective, true) && ! $actor->can($power->value)) {
                return true;
            }
        }

        return false;
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
