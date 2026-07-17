<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Support assignment (DESIGN_HIERARCHY.md DH5): a Sales user links an EXISTING
 * CS/Maintenance user to themselves (many-to-many, sales_assignee). No user is
 * created here — only linked — and only whitelisted types may be assigned.
 *
 * Backend logic + enforcement only; the UI lands in H5.
 */
final class SupportAssignments
{
    /**
     * Assign an existing $assignee to $sales. Gated by the sales user's assign
     * capability against the assignee's role type.
     *
     * @throws AuthorizationException when $sales may not assign $assignee
     */
    public static function assign(User $sales, User $assignee): void
    {
        $type = $assignee->getRoleNames()->first();

        if (! is_string($type) || ! CapabilityResolver::canAssign($sales, $type)) {
            throw new AuthorizationException('Tidak boleh assign user ini.');
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
