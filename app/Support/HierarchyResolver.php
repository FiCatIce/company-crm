<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\User;
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
