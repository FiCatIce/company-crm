<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\User;

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

    public function create(User $user): bool
    {
        return $user->can(P::UserCreate->value);
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
