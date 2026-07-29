<?php

namespace App\Enums;

/**
 * The application's roles. Single source of truth for role names — seeders,
 * presets, and tests reference this enum rather than bare strings.
 *
 * A role is a preset/label only: runtime authorization checks PERMISSIONS
 * (never role names — see DESIGN_RBAC.md §3.1).
 *
 * Deliberately NO label() here (L3-B): display labels resolve through
 * App\Support\TeamRoleLabels alone, so an admin override (label_overrides)
 * reaches every page. A second resolver on this enum is exactly the drift the
 * consolidation removed — it disagreed with the registry over 'cs'.
 */
enum RoleName: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Sales = 'sales';
    case Maintenance = 'maintenance';
    case Cs = 'cs';

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
