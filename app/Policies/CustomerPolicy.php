<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\Customer;
use App\Models\User;
use App\Support\HierarchyResolver;

class CustomerPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        // Any view tier grants list access; the concrete-record check below scopes
        // it to the customers actually reachable via the hierarchy (H3).
        return [
            P::CustomerViewAll->value,
            P::CustomerViewTeam->value,
            P::CustomerViewOwn->value,
            P::CustomerViewAssigned->value,
        ];
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
     * Viewing a specific customer is scope-checked (DESIGN_RBAC.md §4.2b +
     * DESIGN_HIERARCHY.md H3): a scoped viewer may only reach a customer within
     * their hierarchy — own book (Sales), team (Manager), or an assigning sales'
     * book (CS/maintenance). Without this a scoped user could open
     * /customers/{someone-elses-id} directly by URL.
     */
    public function view(User $user, ?Customer $customer = null): bool
    {
        if ($user->can(P::CustomerViewAll->value)) {
            return true;
        }

        // Class-level check (no record) — list access if they hold any view tier.
        if ($customer === null) {
            return $this->hasAny($user, $this->viewPermissions());
        }

        return HierarchyResolver::canSeeCustomer($user, $customer);
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
