<?php

namespace App\Concerns;

use App\Enums\PermissionName;

trait ProvidesPermissionCatalog
{
    /**
     * The full permission catalog grouped by domain, each flagged sensitive or
     * not — drives the grouped checklist + warning badges on the user-edit and
     * role-builder pages.
     *
     * @return list<array{group: string, permissions: list<array{name: string, label: string, sensitive: bool}>}>
     */
    protected function permissionCatalog(): array
    {
        $groups = [];

        foreach (PermissionName::cases() as $permission) {
            $groups[$permission->group()][] = [
                'name' => $permission->value,
                'label' => $permission->label(),
                'sensitive' => $permission->sensitive(),
            ];
        }

        return array_map(
            fn (string $group, array $permissions) => ['group' => $group, 'permissions' => $permissions],
            array_keys($groups),
            array_values($groups),
        );
    }
}
