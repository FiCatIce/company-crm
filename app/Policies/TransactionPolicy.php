<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        return [P::TransactionViewAll->value, P::TransactionViewOwn->value];
    }

    protected function createPermission(): string
    {
        return P::TransactionCreate->value;
    }

    protected function updatePermissions(): array
    {
        return [P::TransactionUpdate->value];
    }

    protected function deletePermission(): string
    {
        return P::TransactionDelete->value;
    }

    public function view(User $user, ?Transaction $transaction = null): bool
    {
        return $this->sees($user, $transaction);
    }

    /**
     * A user may modify a transaction only if they hold the permission AND the
     * transaction is within their visibility scope — otherwise transaction.update
     * would let a Sales user edit any transaction by id (write IDOR).
     */
    public function update(User $user, ?Transaction $transaction = null): bool
    {
        return $user->can(P::TransactionUpdate->value) && $this->sees($user, $transaction);
    }

    public function delete(User $user, ?Transaction $transaction = null): bool
    {
        return $user->can(P::TransactionDelete->value) && $this->sees($user, $transaction);
    }

    /**
     * Whether the transaction is within the user's visibility scope. Mirrors
     * Transaction::scopeVisibleTo. A null transaction (class-level check) resolves
     * to true only for view-all users — own-scoped users need a concrete record.
     */
    private function sees(User $user, ?Transaction $transaction): bool
    {
        if ($user->can(P::TransactionViewAll->value)) {
            return true;
        }

        if ($user->can(P::TransactionViewOwn->value)) {
            $customer = $transaction?->customer;

            return $customer !== null
                && ($customer->created_by === $user->id || $customer->assigned_to === $user->id);
        }

        return false;
    }
}
