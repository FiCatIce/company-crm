<?php

namespace App\Policies;

use App\Models\Reseller;
use App\Models\User;

class ResellerPolicy
{
    /**
     * Roles allowed to view / create / update resellers.
     *
     * @var list<string>
     */
    private const MANAGE_ROLES = ['admin', 'supervisor', 'cs'];

    /**
     * Roles allowed to delete resellers (destructive; excludes cs).
     *
     * @var list<string>
     */
    private const DELETE_ROLES = ['admin', 'supervisor'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function view(User $user, Reseller $reseller): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function update(User $user, Reseller $reseller): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function delete(User $user, Reseller $reseller): bool
    {
        return $user->hasAnyRole(self::DELETE_ROLES);
    }
}
