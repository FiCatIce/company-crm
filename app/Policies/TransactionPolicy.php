<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Roles allowed to view / create / update transactions.
     *
     * @var list<string>
     */
    private const MANAGE_ROLES = ['admin', 'supervisor', 'cs'];

    /**
     * Roles allowed to delete transactions (destructive; excludes cs).
     *
     * @var list<string>
     */
    private const DELETE_ROLES = ['admin', 'supervisor'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->hasAnyRole(self::DELETE_ROLES);
    }
}
