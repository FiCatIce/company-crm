<?php

namespace App\Policies;

use App\Enums\PermissionName as P;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Support\HierarchyResolver;

/**
 * Authorization for service tickets (DESIGN_MAINTENANCE.md §2.3, batch M2).
 * Row reach mirrors ServiceTicket::scopeVisibleTo (D3) so list and record checks
 * never diverge: customer visibility UNION the viewer's own work.
 *
 * WHO does WHAT: a technician works only tickets ASSIGNED to them (plus claiming
 * from the pool); a ticket.assign holder (supervisor) overrides within their
 * sight. CS holds create-only, so intake never becomes cross-editing.
 */
class ServiceTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(P::TicketView->value);
    }

    public function view(User $user, ?ServiceTicket $ticket = null): bool
    {
        if (! $user->can(P::TicketView->value)) {
            return false;
        }

        return $ticket === null || $this->sees($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->can(P::TicketCreate->value);
    }

    /**
     * Editing work fields + driving the status transitions. Bounded to the
     * ticket's OWNER (assigned technician); a ticket.assign holder overrides —
     * but only within the rows they can see (write follows sight, H7).
     */
    public function update(User $user, ?ServiceTicket $ticket = null): bool
    {
        if (! $user->can(P::TicketUpdate->value)) {
            return false;
        }

        if ($ticket === null) {
            return true;
        }

        if (! $this->sees($user, $ticket)) {
            return false;
        }

        return $ticket->assigned_to === $user->id
            || $user->can(P::TicketAssign->value);
    }

    /**
     * Claiming from the pool = assigning a ticket to ONESELF. Needs the work
     * permission (not ticket.assign — that is the routing power), an unclaimed
     * ticket, and sight of it. State (only `received` is claimable) is enforced
     * in TicketFlow.
     */
    public function claim(User $user, ?ServiceTicket $ticket = null): bool
    {
        return $user->can(P::TicketUpdate->value)
            && $ticket !== null
            && $ticket->assigned_to === null
            && $this->sees($user, $ticket);
    }

    /**
     * Routing work to a named technician (and re-routing). Supervisor power,
     * bounded by sight.
     */
    public function assign(User $user, ?ServiceTicket $ticket = null): bool
    {
        if (! $user->can(P::TicketAssign->value)) {
            return false;
        }

        return $ticket === null || $this->sees($user, $ticket);
    }

    /**
     * Hard delete (D9): data hygiene, permission-gated and deliberately NOT
     * sight-bounded — the D9 holder cleans up org-wide the way admin user
     * management does. No preset holds ticket.delete (M2), so this is reachable
     * only through an explicit per-user grant.
     */
    public function delete(User $user, ?ServiceTicket $ticket = null): bool
    {
        return $user->can(P::TicketDelete->value) && $ticket !== null;
    }

    /**
     * The D3 reach: the customer axis (L1) union the viewer's own work. Mirrors
     * ServiceTicket::scopeVisibleTo — keep the two in lockstep.
     */
    private function sees(User $user, ServiceTicket $ticket): bool
    {
        if ($ticket->assigned_to === $user->id || $ticket->created_by === $user->id) {
            return true;
        }

        $customer = $ticket->customer;

        return $customer !== null && HierarchyResolver::canSeeCustomer($user, $customer);
    }
}
