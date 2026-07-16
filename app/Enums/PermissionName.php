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
    // Create/edit/delete custom roles + set their permission templates (role builder).
    case RoleManage = 'role.manage';

    /**
     * All permission strings.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * Display group for the admin permission-toggle UI. Keeps money-adjacent and
     * meta permissions with the domain they concern (revenue → Transaction;
     * role/permission → User & Access).
     */
    public function group(): string
    {
        return match ($this) {
            self::CustomerViewAll, self::CustomerViewOwn, self::CustomerViewProducts,
            self::CustomerCreate, self::CustomerUpdateAll, self::CustomerUpdateOwn,
            self::CustomerDelete, self::CustomerReassign => 'Customer',
            self::TransactionViewAll, self::TransactionViewOwn, self::TransactionCreate,
            self::TransactionUpdate, self::TransactionDelete, self::RevenueView => 'Transaction',
            self::ProductView, self::ProductCreate, self::ProductUpdate, self::ProductDelete => 'Product',
            self::ResellerView, self::ResellerCreate, self::ResellerUpdate, self::ResellerDelete => 'Reseller',
            self::InteractionViewAll, self::InteractionViewOwn, self::InteractionCreate,
            self::InteractionUpdate, self::InteractionDelete, self::InteractionManageAll => 'Interaction',
            self::DashboardView, self::DashboardStatsAggregate => 'Dashboard',
            self::UserView, self::UserCreate, self::UserUpdate, self::UserDelete,
            self::RoleAssign, self::PermissionAssign, self::RoleManage => 'User & Access',
        };
    }

    /**
     * Whether granting this permission opens broad PII, financial, or meta
     * (grant-other-permissions) access — the toggles that warrant an explicit
     * confirmation in the admin UI (DESIGN_RBAC.md §3.5).
     */
    public function sensitive(): bool
    {
        return match ($this) {
            self::CustomerViewAll,
            self::TransactionViewAll,
            self::TransactionViewOwn,
            self::RevenueView,
            self::RoleAssign,
            self::PermissionAssign,
            self::RoleManage,
            self::UserDelete => true,
            default => false,
        };
    }

    /**
     * Short human label (Indonesian UI copy) for the permission toggle.
     */
    public function label(): string
    {
        return match ($this) {
            self::CustomerViewAll => 'Lihat semua customer',
            self::CustomerViewOwn => 'Lihat customer sendiri',
            self::CustomerViewProducts => 'Lihat produk yang dibeli',
            self::CustomerCreate => 'Tambah customer',
            self::CustomerUpdateAll => 'Edit semua customer',
            self::CustomerUpdateOwn => 'Edit customer sendiri',
            self::CustomerDelete => 'Hapus customer',
            self::CustomerReassign => 'Pindah owner customer',
            self::TransactionViewAll => 'Lihat semua transaksi (uang)',
            self::TransactionViewOwn => 'Lihat transaksi sendiri (uang)',
            self::TransactionCreate => 'Catat transaksi',
            self::TransactionUpdate => 'Edit transaksi',
            self::TransactionDelete => 'Hapus transaksi',
            self::RevenueView => 'Lihat pendapatan agregat',
            self::ProductView => 'Lihat katalog produk',
            self::ProductCreate => 'Tambah produk',
            self::ProductUpdate => 'Edit produk',
            self::ProductDelete => 'Hapus produk',
            self::ResellerView => 'Lihat reseller',
            self::ResellerCreate => 'Tambah reseller',
            self::ResellerUpdate => 'Edit reseller',
            self::ResellerDelete => 'Hapus reseller',
            self::InteractionViewAll => 'Lihat semua call log',
            self::InteractionViewOwn => 'Lihat call log sendiri',
            self::InteractionCreate => 'Log interaksi',
            self::InteractionUpdate => 'Edit interaksi sendiri',
            self::InteractionDelete => 'Hapus interaksi sendiri',
            self::InteractionManageAll => 'Kelola interaksi siapa pun',
            self::DashboardView => 'Akses dashboard',
            self::DashboardStatsAggregate => 'Lihat statistik agregat',
            self::UserView => 'Lihat user',
            self::UserCreate => 'Tambah user',
            self::UserUpdate => 'Edit user',
            self::UserDelete => 'Hapus user',
            self::RoleAssign => 'Assign role',
            self::PermissionAssign => 'Atur izin per user',
            self::RoleManage => 'Kelola role & izin',
        };
    }
}
