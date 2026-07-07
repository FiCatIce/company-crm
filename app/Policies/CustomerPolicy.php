<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Roles allowed to view / create / update customers.
     *
     * @var list<string>
     */
    private const MANAGE_ROLES = ['admin', 'supervisor', 'cs'];

    /**
     * Roles allowed to delete customers (destructive; excludes cs).
     *
     * @var list<string>
     */
    private const DELETE_ROLES = ['admin', 'supervisor'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole(self::DELETE_ROLES);
    }
}
