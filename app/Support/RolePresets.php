<?php

namespace App\Support;

use App\Enums\PermissionName as P;
use App\Enums\RoleName;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Maps each role to its default permission set, and applies it to a user.
 *
 * Model (DESIGN_RBAC.md §3.4, decision D6-A): roles are TEMPLATES. Permissions
 * live on the user (direct `model_has_permissions`), NOT on the role — so an
 * admin can toggle a single permission on/off per user in either direction.
 * `role_has_permissions` is intentionally left empty. `assign()` writes both the
 * role (label) and the synced direct permissions.
 *
 * BATCH B0 NOTE — the foundation batch introduced these presets mirroring the
 * legacy org-wide access; later batches tightened them toward the
 * DESIGN_RBAC.md §3.3 target:
 *   - B3 (done) removed all transaction/revenue permissions from cs, so CS (like
 *     maintenance) sees customers + purchased products but never money.
 *   - B4 (done) stripped ALL data permissions from admin — admin is now a
 *     system/user-management role that sees only aggregate stats + the call log,
 *     never customer/transaction/product detail or money. This is the design's
 *     central "admin flip": admin is NOT a data super-user.
 * The sales/maintenance presets are already the §3.3 target for the money axis.
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
            RoleName::Admin => self::adminSystem(),
            RoleName::Supervisor => self::fullDomainAccess(),
            RoleName::Cs => self::customerService(),
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
     * The effective permission strings a role currently grants — the single
     * source of truth read by the role builder, the user-management preset map,
     * and the re-sync seeder. Resolves the three kinds of role:
     *
     *  - Admin: ALWAYS its locked code preset. The admin role can never be
     *    customized (privilege-escalation / lockout guard), so its attached
     *    permissions, if any, are ignored.
     *  - A role carrying its own permissions (role_has_permissions): those — a
     *    custom role, OR a non-admin system role an admin has edited/renamed via
     *    the role builder (the edit "detaches" it from its code preset).
     *  - An un-customized system role (no attached permissions): its code preset
     *    default.
     *  - Anything else: empty.
     *
     * Results are reduced to valid permission names in the enum's canonical
     * order, so callers get a clean, deterministic list.
     *
     * @return list<string>
     */
    public static function effectivePermissions(Role $role): array
    {
        if ($role->name === RoleName::Admin->value) {
            return self::permissions(RoleName::Admin);
        }

        $held = $role->permissions->pluck('name');
        if ($held->isNotEmpty()) {
            return array_values(array_filter(
                P::values(),
                fn (string $permission): bool => $held->contains($permission),
            ));
        }

        if ($systemRole = RoleName::tryFrom($role->name)) {
            return self::permissions($systemRole);
        }

        return [];
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
            // H2 (DESIGN_HIERARCHY.md DH4): the manager may CREATE team members —
            // limited to its assignable_types (sales/cs/maintenance) and never a
            // user-administrator. It holds NO permission.assign, so it can never
            // reach the unrestricted admin user UI nor set a user's permissions.
            P::UserCreate,
        ];
    }

    /**
     * Admin (DESIGN_RBAC.md §3.3 / batch B4): a system role, NOT a data super-user.
     * User management + permission granting, dashboard AGGREGATE stats only (counts,
     * never rows), and the org-wide call log (per the user's revised B4 matrix).
     * Deliberately holds NO customer/transaction/product/reseller/revenue permission,
     * so every data route 403s and the dashboard hides every detail widget.
     *
     * @return list<P>
     */
    private static function adminSystem(): array
    {
        return [
            P::DashboardView, P::DashboardStatsAggregate,
            P::InteractionViewAll,
            P::UserView, P::UserCreate, P::UserUpdate, P::UserDelete,
            P::RoleAssign, P::PermissionAssign, P::RoleManage,
        ];
    }

    /**
     * Customer Service (DESIGN_RBAC.md §3.3, batch B3): front-line agents who see
     * every customer's profile + purchased products and manage the call log, but
     * NOT money — no transaction access, no revenue. Still create/update customers
     * (unlike read-only maintenance) and no destructive/moderation access.
     *
     * @return list<P>
     */
    private static function customerService(): array
    {
        return [
            P::CustomerViewAll, P::CustomerViewProducts, P::CustomerCreate,
            P::CustomerUpdateAll, P::CustomerReassign,
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
            // H2 (DESIGN_HIERARCHY.md DH5): sales may ASSIGN existing CS/maintenance
            // to itself (not create users) — limited to its assignable_types.
            P::UserAssign,
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
