<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Extends Spatie's Role to carry `assignable_types` — the DH4 capability config
 * (which user types this role may create/assign). Registered as the permission
 * package's role model in config/permission.php so every role resolves with the
 * cast applied.
 *
 * DORMANT in H1: the column is populated (HierarchySeeder) but nothing enforces
 * it yet — delegated creation/assignment gating lands in H2/H4.
 *
 * @property array<int, string>|null $assignable_types
 */
class Role extends SpatieRole
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignable_types' => 'array',
        ];
    }
}
