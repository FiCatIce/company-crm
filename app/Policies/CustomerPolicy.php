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
     * WRITE FOLLOWS SIGHT (batch H7). A user may only modify a customer they can
     * SEE: `.all` is bounded by the viewer's hierarchy visibility, `.own` narrows
     * further to their own book. Before H7 `customer.update.all` was unbounded, so
     * a manager could edit another TEAM's customer and a CS agent could edit the
     * book of a rep who never assigned them — a write IDOR behind a read-tight
     * scope (H3 closed reads only).
     *
     * Deriving the bound from visibility (rather than adding update.team /
     * update.assigned tiers) keeps the invariant in ONE place: any future view
     * tier is automatically write-bounded too, with no new permission to forget.
     * A truly global role still writes org-wide — it holds customer.view.all, so
     * canSeeCustomer is always true for it.
     *
     * A null customer is the CLASS-level check ("may they edit customers at all?")
     * used for menu/button capability — never for a concrete row.
     */
    public function update(User $user, ?Customer $customer = null): bool
    {
        if (! $this->hasAny($user, $this->updatePermissions())) {
            return false;
        }

        if ($customer === null) {
            return true;
        }

        if (! HierarchyResolver::canSeeCustomer($user, $customer)) {
            return false;
        }

        return $user->can(P::CustomerUpdateAll->value) || $this->owns($user, $customer);
    }

    /**
     * Deleting follows the same rule (H7): the permission alone is not enough —
     * the record must be within the actor's visibility. Previously inherited from
     * ResourcePolicy, which checks the permission only, so any customer.delete
     * holder could destroy ANY customer by id.
     */
    public function delete(User $user, ?Customer $customer = null): bool
    {
        if (! $user->can($this->deletePermission())) {
            return false;
        }

        return $customer === null || HierarchyResolver::canSeeCustomer($user, $customer);
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
