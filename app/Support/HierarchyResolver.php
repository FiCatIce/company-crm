<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the hierarchy sets that drive visibility roll-up (DESIGN_HIERARCHY.md
 * H3): a manager sees their whole team's book; a CS/maintenance user sees the book
 * of every sales who assigned them. The ONE place "which owners may this user see"
 * is computed — shared by Customer::scopeVisibleTo and the policies so the list and
 * the per-record checks never diverge.
 */
final class HierarchyResolver
{
    /**
     * User-ids of every member of every team $user belongs to (includes $user) —
     * the owners a team roll-up viewer (manager) may see customers for.
     *
     * @return list<int>
     */
    public static function teamMemberIds(User $user): array
    {
        $teamIds = DB::table('team_user')->where('user_id', $user->id)->pluck('team_id')->all();

        if ($teamIds === []) {
            return [];
        }

        $ids = DB::table('team_user')->whereIn('team_id', $teamIds)->pluck('user_id')->all();

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Sales user-ids that have assigned $user (a CS/maintenance) to themselves —
     * the owners an assignment-based viewer may see customers for.
     *
     * @return list<int>
     */
    public static function assignerSalesIds(User $user): array
    {
        $ids = DB::table('sales_assignee')->where('assignee_user_id', $user->id)->pluck('sales_user_id')->all();

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Support users (CS/maintenance) $sales may assign to themselves: members of
     * the sales user's OWN team whose role is in $types, excluding $sales.
     *
     * TEAM-SCOPED BY DESIGN (H5 decision). An assignment GRANTS the assignee sight
     * of this sales user's customers, so an org-wide pool would let a rep
     * unilaterally open their team's book to an outsider — exactly the cross-team
     * hole H3's isolation closes. A support agent may still serve MANY sales (the
     * pivot is many-to-many) as long as they share a team, which covers the common
     * "one CS desk for the whole team" case.
     *
     * L4-SEAM: a genuinely shared/central support pool (one desk serving several
     * teams) widens THIS method only — UI, pivot, and enforcement stay untouched.
     *
     * @param  list<string>  $types
     * @return list<int>
     */
    public static function supportCandidateIds(User $sales, array $types): array
    {
        if ($types === []) {
            return [];
        }

        $teamIds = DB::table('team_user')->where('user_id', $sales->id)->pluck('team_id')->all();

        if ($teamIds === []) {
            return []; // not on a team yet → nothing to assign from
        }

        $memberIds = array_values(array_diff(
            array_unique(array_map('intval', DB::table('team_user')->whereIn('team_id', $teamIds)->pluck('user_id')->all())),
            [(int) $sales->id],
        ));

        if ($memberIds === []) {
            return [];
        }

        // H7b: only ACTIVE accounts are offered. Existing assignments to a
        // deactivated agent are deliberately left alone (deactivation is reversible
        // and lossless) — but handing them NEW work is a fresh act, and this list is
        // exactly "who can we hand work to".
        return array_values(array_map('intval', User::query()
            ->whereIn('id', $memberIds)
            ->active()
            ->whereHas('roles', fn (Builder $role) => $role->whereIn('name', $types))
            ->pluck('id')
            ->all()));
    }

    /**
     * The customer-owner user-ids $user may see through the hierarchy tiers they
     * hold — team roll-up ∪ own ∪ assigner union. Does NOT cover customer.view.all
     * (callers short-circuit that to "unscoped"). Empty when the user holds no
     * non-global view tier (→ sees nothing).
     *
     * @return list<int>
     */
    public static function visibleOwnerIds(User $user): array
    {
        $ids = [];

        if ($user->can(PermissionName::CustomerViewTeam->value)) {
            $ids = array_merge($ids, self::teamMemberIds($user));
        }

        if ($user->can(PermissionName::CustomerViewOwn->value)) {
            $ids[] = (int) $user->id;
        }

        if ($user->can(PermissionName::CustomerViewAssigned->value)) {
            $ids = array_merge($ids, self::assignerSalesIds($user));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether $user may see a specific customer — the record-level mirror of
     * Customer::scopeVisibleTo, used by the policies.
     */
    public static function canSeeCustomer(User $user, Customer $customer): bool
    {
        if ($user->can(PermissionName::CustomerViewAll->value)) {
            return true;
        }

        $ids = self::visibleOwnerIds($user);

        return ($customer->created_by !== null && in_array($customer->created_by, $ids, true))
            || ($customer->assigned_to !== null && in_array($customer->assigned_to, $ids, true));
    }
}
