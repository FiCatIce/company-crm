<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared role-based authorization for the CRM's domain resources. Access is
 * granted purely by spatie role (records are shared org-wide, not owner-scoped);
 * a subclass may override the role lists to tighten a specific resource.
 */
abstract class RolePolicy
{
    /**
     * Roles allowed to view / create / update the resource.
     *
     * @var list<string>
     */
    protected const MANAGE_ROLES = ['admin', 'supervisor', 'cs'];

    /**
     * Roles allowed to delete the resource (destructive; excludes cs).
     *
     * @var list<string>
     */
    protected const DELETE_ROLES = ['admin', 'supervisor'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(static::MANAGE_ROLES);
    }

    public function view(User $user): bool
    {
        return $user->hasAnyRole(static::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(static::MANAGE_ROLES);
    }

    public function update(User $user): bool
    {
        return $user->hasAnyRole(static::MANAGE_ROLES);
    }

    public function delete(User $user): bool
    {
        return $user->hasAnyRole(static::DELETE_ROLES);
    }
}
