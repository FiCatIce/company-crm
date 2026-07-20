<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The account lifecycle switch (DESIGN_HIERARCHY.md batch H7b). Deactivation
 * revokes ACCESS and nothing else:
 *
 *   - is_active = false blocks every login path (password, 2FA, passkey) and cuts
 *     any live session on the next request;
 *   - customers.assigned_to and the sales_assignee rows are NOT touched.
 *
 * That is the ketokan: deactivation is REVERSIBLE, so it must be lossless —
 * auto-transferring a book would make reactivation lossy and would hide a data
 * move behind a toggle. Handing a book to someone else is a separate, explicit
 * action (H7c). The cost is that a deactivated member's customers could go quietly
 * untended, so "Tim Saya" and the member list flag inactive accounts.
 *
 * Same shape as the other delegated services (DelegatedUserCreator, SupportAssignments):
 * the guards live here and throw, so no caller can bypass them, while the controllers
 * authorize first for a clean 403 and translate the last-admin case into a friendly
 * flash rather than an error page.
 */
final class AccountStatus
{
    /**
     * Flip $target's account switch on behalf of $actor. Idempotent: setting the
     * status it already has is a no-op (no audit noise).
     *
     * @throws AuthorizationException
     */
    public static function set(User $actor, User $target, bool $active): void
    {
        if (! $actor->can('setStatus', $target)) {
            throw new AuthorizationException('Anda tidak berwenang mengubah status akun ini.');
        }

        if (! $active && self::isLastAdmin($target)) {
            throw new AuthorizationException('Tidak dapat menonaktifkan admin terakhir.');
        }

        if ($target->is_active === $active) {
            return;
        }

        $target->forceFill(['is_active' => $active])->save();

        AuditLog::record($actor, $target, $active ? 'user.reactivated' : 'user.deactivated');
    }

    /**
     * Whether deactivating this user would leave the system with no ACTIVE admin —
     * the lockout guard, the status-side twin of UserController's delete guard.
     * Counts active admins only, so admin A cannot be deactivated after admin B
     * already was.
     */
    public static function isLastAdmin(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value)
            && User::role(RoleName::Admin->value)->where('is_active', true)->count() <= 1;
    }
}
