<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Support assignment (DESIGN_HIERARCHY.md DH5): a Sales user links an EXISTING
 * CS/Maintenance user to themselves (many-to-many, sales_assignee). No user is
 * created here — only linked.
 *
 * THE security backstop for the H5 pool decision, in full: an assignment GRANTS the
 * assignee sight of this rep's entire book, so it is bounded by TYPE (a whitelisted
 * support role, never an admin-power or wider-data one) AND by TEAM (same team as
 * the rep). The team half used to live only in StoreSupportAssignmentRequest's
 * Rule::in, so this class documented itself as a backstop while checking half of
 * what mattered — safe with one caller, an open cross-team hole the moment a second
 * one appeared.
 */
final class SupportAssignments
{
    /**
     * Assign an existing $assignee to $sales — bounded by type AND team.
     *
     * @throws AuthorizationException when $sales may not assign $assignee
     */
    public static function assign(User $sales, User $assignee): void
    {
        $type = $assignee->getRoleNames()->first();

        if (! is_string($type) || ! CapabilityResolver::canAssign($sales, $type)) {
            throw new AuthorizationException('Tidak boleh assign user ini.');
        }

        // The team bound, from the one seam that defines the pool (H5) — so widening
        // to a shared/central support desk in L4 still only changes that method.
        if (! in_array((int) $assignee->id, HierarchyResolver::supportCandidateIds($sales, [$type]), true)) {
            throw new AuthorizationException('Hanya bisa assign support dari tim Anda.');
        }

        $sales->assignees()->syncWithoutDetaching([$assignee->id]);

        AuditLog::record($sales, $assignee, 'support.assigned', ['type' => $type]);
    }

    /**
     * Remove an assignment. Idempotent — detaching a non-assignee is a no-op.
     */
    public static function unassign(User $sales, User $assignee): void
    {
        $sales->assignees()->detach($assignee->id);

        AuditLog::record($sales, $assignee, 'support.unassigned', null);
    }
}
