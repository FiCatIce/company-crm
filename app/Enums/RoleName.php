<?php

namespace App\Enums;

/**
 * The application's roles. Single source of truth for role names — seeders,
 * presets, and tests reference this enum rather than bare strings.
 *
 * A role is a preset/label only: runtime authorization checks PERMISSIONS
 * (never role names — see DESIGN_RBAC.md §3.1). "Manager" is the UI label for
 * the `supervisor` slug (kept to avoid a data rename — DESIGN_RBAC.md D5).
 */
enum RoleName: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Sales = 'sales';
    case Maintenance = 'maintenance';
    case Cs = 'cs';

    /**
     * Human label for the role (Indonesian UI copy).
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Supervisor => 'Manager',
            self::Sales => 'Sales',
            self::Maintenance => 'Maintenance',
            self::Cs => 'Customer Service',
        };
    }

    /**
     * All role slugs.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }
}
