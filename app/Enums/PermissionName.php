<?php

namespace App\Enums;

/**
 * Granular permissions gating every CRM capability. Single source of truth:
 * PermissionSeeder seeds exactly these, policies check them, RolePresets maps
 * roles to them. See DESIGN_RBAC.md §3.2.
 *
 * Scope is a separate axis from action: `*.view.all` vs `*.view.own`. The row
 * filtering those imply (`created_by`/`assigned_to`) is NOT wired in this batch
 * (B0) — it lands in B1. Here the strings merely exist and gate the same actions
 * they gated before.
 */
enum PermissionName: string
{
    // Customer
    case CustomerViewAll = 'customer.view.all';
    case CustomerViewOwn = 'customer.view.own';
    case CustomerViewProducts = 'customer.view.products';
    case CustomerCreate = 'customer.create';
    case CustomerUpdateAll = 'customer.update.all';
    case CustomerUpdateOwn = 'customer.update.own';
    case CustomerDelete = 'customer.delete';
    case CustomerReassign = 'customer.reassign';

    // Transaction / money
    case TransactionViewAll = 'transaction.view.all';
    case TransactionViewOwn = 'transaction.view.own';
    case TransactionCreate = 'transaction.create';
    case TransactionUpdate = 'transaction.update';
    case TransactionDelete = 'transaction.delete';
    case RevenueView = 'revenue.view';

    // Product
    case ProductView = 'product.view';
    case ProductCreate = 'product.create';
    case ProductUpdate = 'product.update';
    case ProductDelete = 'product.delete';

    // Reseller
    case ResellerView = 'reseller.view';
    case ResellerCreate = 'reseller.create';
    case ResellerUpdate = 'reseller.update';
    case ResellerDelete = 'reseller.delete';

    // Interaction / call log
    case InteractionViewAll = 'interaction.view.all';
    case InteractionViewOwn = 'interaction.view.own';
    case InteractionCreate = 'interaction.create';
    case InteractionUpdate = 'interaction.update';
    case InteractionDelete = 'interaction.delete';
    // Edit/delete a manual interaction authored by ANYONE (the old DELETE_ROLES
    // tier). Author-only editing needs just InteractionUpdate/Delete.
    case InteractionManageAll = 'interaction.manage.all';

    // Dashboard
    case DashboardView = 'dashboard.view';
    case DashboardStatsAggregate = 'dashboard.stats.aggregate';

    // User management (dormant in B0 — no UI/route exercises these until B5)
    case UserView = 'user.view';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';
    case RoleAssign = 'role.assign';
    case PermissionAssign = 'permission.assign';

    /**
     * All permission strings.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
