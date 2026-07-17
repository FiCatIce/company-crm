<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\User;
use App\Support\CapabilityResolver;

/**
 * Authorization for the admin user-management area (DESIGN_RBAC.md §3.5). Gates
 * on the granular user/role/permission grants; the money/customer permissions are
 * unrelated. The role- and permission-changing abilities additionally forbid
 * acting on oneself (D2) — the anti-self-escalation guard.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::UserView->value);
    }

    public function view(User $user, ?User $target = null): bool
    {
        return $user->can(P::UserView->value);
    }

    /**
     * The UNRESTRICTED admin user-management area (existing /users create UI): may
     * create a user of ANY role. Gated on permission.assign — the grant-anything
     * power — so a DELEGATED creator (a manager holding user.create but not
     * permission.assign) can never reach it. Their path is createType() instead.
     */
    public function create(User $user): bool
    {
        return $user->can(P::UserCreate->value) && $user->can(P::PermissionAssign->value);
    }

    /**
     * Delegated creation of a specific role TYPE (DH4): a manager creating a
     * whitelisted team member. Type-scoped + escalation-guarded (a delegate can
     * never mint a user-administrator) via CapabilityResolver.
     */
    public function createType(User $user, string $type): bool
    {
        return CapabilityResolver::canCreateUserType($user, $type);
    }

    /**
     * May the actor use the delegated team-members area at all (H4)? True iff they
     * have at least one delegable type — a manager, never the admin (whose /users
     * UI is the create path, so its delegated whitelist is empty). Sales, holding
     * only user.assign, never reaches it.
     */
    public function manageTeamMembers(User $user): bool
    {
        return CapabilityResolver::creatableTypes($user) !== [];
    }

    /**
     * May the actor manage THIS specific team member (reset password now; the
     * offboarding/deactivate lifecycle lands in H7)? The target must be a
     * delegable type WITHIN the actor's reach — provisioned by them or a member of
     * their team — and never the actor themselves. This bounds a manager to their
     * own book: they can never touch a peer manager, an admin, or an unrelated rep.
     */
    public function manageTeamMember(User $user, ?User $target = null): bool
    {
        if ($target === null || $user->id === $target->id) {
            return false;
        }

        $targetRole = $target->getRoleNames()->first();
        if (! is_string($targetRole)
            || ! in_array($targetRole, CapabilityResolver::assignableTypes($user), true)) {
            return false;
        }

        if ($target->created_by_user === $user->id) {
            return true;
        }

        $team = $user->team();

        return $team !== null && $target->teams()->whereKey($team->id)->exists();
    }

    public function update(User $user, ?User $target = null): bool
    {
        return $user->can(P::UserUpdate->value);
    }

    /**
     * Deleting a user needs the permission, and can never target oneself — that
     * guard is also re-checked in the controller (which additionally blocks
     * removing the last admin).
     */
    public function delete(User $user, ?User $target = null): bool
    {
        return $user->can(P::UserDelete->value)
            && $target !== null
            && $user->id !== $target->id;
    }

    /**
     * May the actor change THIS user's role? Requires role.assign and a target
     * that is not the actor — you cannot re-template your own permissions (D2).
     */
    public function assignRole(User $user, ?User $target = null): bool
    {
        return $user->can(P::RoleAssign->value)
            && $target !== null
            && $user->id !== $target->id;
    }

    /**
     * May the actor toggle THIS user's direct permissions? Requires
     * permission.assign and, again, a non-self target — the core mitigation for
     * the admin paradox: an admin cannot self-grant data access (D2).
     */
    public function managePermissions(User $user, ?User $target = null): bool
    {
        return $user->can(P::PermissionAssign->value)
            && $target !== null
            && $user->id !== $target->id;
    }
}
