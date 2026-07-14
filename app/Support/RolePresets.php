<?php

namespace App\Support;

use App\Enums\PermissionName as P;
use App\Enums\RoleName;
use App\Models\User;

/**
 * Maps each role to its default permission set, and applies it to a user.
 *
 * Model (DESIGN_RBAC.md §3.4, decision D6-A): roles are TEMPLATES. Permissions
 * live on the user (direct `model_has_permissions`), NOT on the role — so an
 * admin can toggle a single permission on/off per user in either direction.
 * `role_has_permissions` is intentionally left empty. `assign()` writes both the
 * role (label) and the synced direct permissions.
 *
 * BATCH B0 NOTE — this is the *foundation* batch and must not change behaviour.
 * The admin/supervisor/cs presets below therefore MIRROR the legacy org-wide
 * access (admin == supervisor == full; cs == full minus destructive). They are
 * tightened toward the DESIGN_RBAC.md §3.3 target in later batches:
 *   - B3 removes transaction/revenue from cs (money hidden).
 *   - B4 strips all data permissions from admin (stats + user-management only).
 * The sales/maintenance presets are already the §3.3 target; those roles are
 * created but not yet exercised (their row-scoping lands in B1).
 */
final class RolePresets
{
    /**
     * The permissions granted to a role by default.
     *
     * @return list<P>
     */
    public static function for(RoleName $role): array
    {
        return match ($role) {
            RoleName::Admin => [...self::fullDomainAccess(), ...self::userManagement()],
            RoleName::Supervisor => self::fullDomainAccess(),
            RoleName::Cs => self::customerServiceLegacy(),
            RoleName::Sales => self::sales(),
            RoleName::Maintenance => self::maintenance(),
        };
    }

    /**
     * The preset permissions as plain strings (for Spatie syncPermissions).
     *
     * @return list<string>
     */
    public static function permissions(RoleName $role): array
    {
        return array_map(fn (P $permission): string => $permission->value, self::for($role));
    }

    /**
     * Assign a role and sync its preset onto the user as direct permissions.
     * The single provisioning path used by seeders, tests, and (later) the admin
     * UI — keeping "how a user gets access" in one place.
     */
    public static function assign(User $user, RoleName $role): void
    {
        $user->syncRoles([$role->value]);
        $user->syncPermissions(self::permissions($role));
    }

    /**
     * Legacy full access shared by admin + supervisor (org-wide, incl. delete
     * and moderating anyone's interactions). Mirrors the pre-RBAC behaviour.
     *
     * @return list<P>
     */
    private static function fullDomainAccess(): array
    {
        return [
            P::CustomerViewAll, P::CustomerViewProducts, P::CustomerCreate,
            P::CustomerUpdateAll, P::CustomerReassign, P::CustomerDelete,
            P::TransactionViewAll, P::TransactionCreate, P::TransactionUpdate,
            P::TransactionDelete, P::RevenueView,
            P::ProductView, P::ProductCreate, P::ProductUpdate, P::ProductDelete,
            P::ResellerView, P::ResellerCreate, P::ResellerUpdate, P::ResellerDelete,
            P::InteractionViewAll, P::InteractionCreate, P::InteractionUpdate,
            P::InteractionDelete, P::InteractionManageAll,
            P::DashboardView, P::DashboardStatsAggregate,
        ];
    }

    /**
     * User-management + permission granting. Admin only (dormant until B5).
     *
     * @return list<P>
     */
    private static function userManagement(): array
    {
        return [
            P::UserView, P::UserCreate, P::UserUpdate, P::UserDelete,
            P::RoleAssign, P::PermissionAssign,
        ];
    }

    /**
     * Legacy CS access: same as full access minus the destructive/moderation
     * permissions (no record delete, no moderating other agents' interactions).
     *
     * @return list<P>
     */
    private static function customerServiceLegacy(): array
    {
        return [
            P::CustomerViewAll, P::CustomerViewProducts, P::CustomerCreate,
            P::CustomerUpdateAll, P::CustomerReassign,
            P::TransactionViewAll, P::TransactionCreate, P::TransactionUpdate,
            P::RevenueView,
            P::ProductView, P::ProductCreate, P::ProductUpdate,
            P::ResellerView, P::ResellerCreate, P::ResellerUpdate,
            P::InteractionViewAll, P::InteractionCreate, P::InteractionUpdate,
            P::InteractionDelete,
            P::DashboardView, P::DashboardStatsAggregate,
        ];
    }

    /**
     * Sales (DESIGN_RBAC.md §3.3): only their own customers/transactions, no
     * org-wide money, no destructive access. Row-scoping activates in B1.
     *
     * @return list<P>
     */
    private static function sales(): array
    {
        return [
            P::CustomerViewOwn, P::CustomerViewProducts, P::CustomerCreate, P::CustomerUpdateOwn,
            P::TransactionViewOwn, P::TransactionCreate, P::TransactionUpdate,
            P::ProductView,
            P::ResellerView,
            P::InteractionViewOwn, P::InteractionCreate, P::InteractionUpdate, P::InteractionDelete,
            P::DashboardView, P::DashboardStatsAggregate,
        ];
    }

    /**
     * Maintenance (DESIGN_RBAC.md §3.3, decision D8): read-only customers +
     * purchased products, all call logs, NO money.
     *
     * @return list<P>
     */
    private static function maintenance(): array
    {
        return [
            P::CustomerViewAll, P::CustomerViewProducts,
            P::ProductView,
            P::InteractionViewAll, P::InteractionCreate, P::InteractionUpdate, P::InteractionDelete,
            P::DashboardView, P::DashboardStatsAggregate,
        ];
    }
}
