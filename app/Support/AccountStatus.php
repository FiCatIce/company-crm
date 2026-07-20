<?php

namespace App\Support;

use App\Enums\PermissionName;
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
     * Whether removing this user's access would leave nobody able to administer the
     * system — the ONE lockout guard, shared by deactivate, delete, offboard and the
     * role change.
     *
     * It counts the POWER, not the label. Holding the `admin` ROLE is not the same
     * as holding admin power: permissions live on the user (model_has_permissions)
     * and `role_has_permissions` is intentionally empty for system roles, so a user
     * promoted to `admin` through the edit screen carries the role name and none of
     * its abilities. A guard that counted role members could therefore be satisfied
     * by a powerless stand-in while the real administrator was removed — locking
     * everyone out of /users and /roles with no way back through the UI.
     *
     * `permission.assign` is the right measure: it is the grant-anything power, the
     * one thing needed to restore any other. Inactive holders don't count — they
     * cannot log in (H7b), so they cannot administer anything.
     */
    public static function isLastAdmin(User $user): bool
    {
        $power = PermissionName::PermissionAssign->value;

        if (! $user->can($power)) {
            return false;
        }

        return User::query()
            ->permission($power)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
