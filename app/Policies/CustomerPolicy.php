<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        return [P::CustomerViewAll->value, P::CustomerViewOwn->value];
    }

    protected function createPermission(): string
    {
        return P::CustomerCreate->value;
    }

    protected function updatePermissions(): array
    {
        return [P::CustomerUpdateAll->value, P::CustomerUpdateOwn->value];
    }

    protected function deletePermission(): string
    {
        return P::CustomerDelete->value;
    }

    /**
     * Viewing a specific customer is scope-checked (DESIGN_RBAC.md §4.2b): an
     * own-scoped user may only reach a customer they created or own. Without this
     * a Sales user could open /customers/{someone-elses-id} directly by URL.
     */
    public function view(User $user, ?Customer $customer = null): bool
    {
        if ($user->can(P::CustomerViewAll->value)) {
            return true;
        }

        if ($user->can(P::CustomerViewOwn->value)) {
            return $customer !== null && $this->owns($user, $customer);
        }

        return false;
    }

    /**
     * Updating a specific customer is scope-checked the same way — otherwise
     * `customer.update.own` would let a Sales user write to any customer by id.
     */
    public function update(User $user, ?Customer $customer = null): bool
    {
        if ($user->can(P::CustomerUpdateAll->value)) {
            return true;
        }

        if ($user->can(P::CustomerUpdateOwn->value)) {
            return $customer !== null && $this->owns($user, $customer);
        }

        return false;
    }

    /**
     * A customer is "own" if the user created it or currently owns it (D1-B) —
     * mirrors Customer::scopeVisibleTo so list and record checks never diverge.
     */
    private function owns(User $user, Customer $customer): bool
    {
        return $customer->created_by === $user->id
            || $customer->assigned_to === $user->id;
    }
}
