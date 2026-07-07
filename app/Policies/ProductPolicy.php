<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Roles allowed to view / create / update products.
     *
     * @var list<string>
     */
    private const MANAGE_ROLES = ['admin', 'supervisor', 'cs'];

    /**
     * Roles allowed to delete products (destructive; excludes cs).
     *
     * @var list<string>
     */
    private const DELETE_ROLES = ['admin', 'supervisor'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasAnyRole(self::DELETE_ROLES);
    }
}
