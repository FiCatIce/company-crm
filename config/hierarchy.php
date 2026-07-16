<?php

use App\Enums\RoleName;

/*
|--------------------------------------------------------------------------
| Hierarchy / team configuration
|--------------------------------------------------------------------------
|
| The SINGLE source for anything L3 white-label (role labels) and L4 (team
| levels, capabilities) will later override per-tenant. Keeping these here —
| not scattered as string literals across the codebase — is the whole point
| (DESIGN_HIERARCHY.md naming hook). H1 ships the defaults; nothing enforces
| the capability map yet (it is dormant until H2/H4).
|
*/

return [

    /*
     | L3 white-label hook: role slug -> display label. Read ONLY via
     | App\Support\TeamRoleLabels so callers never hardcode 'Sales'/'Manager'.
     | Swap these (or override per-tenant) at L3 without touching any UI.
     */
    'role_labels' => [
        RoleName::Admin->value => 'Admin',
        RoleName::Supervisor->value => 'Manager',
        RoleName::Sales->value => 'Sales',
        RoleName::Cs->value => 'CS',
        RoleName::Maintenance->value => 'Maintenance',
    ],

    /*
     | DH4 capability defaults — which user types a role may create/assign.
     | Seeded onto roles.assignable_types. DORMANT in H1: no gate enforces this
     | until H2/H4. At L4 this becomes admin-editable per role.
     */
    'assignable_types' => [
        RoleName::Supervisor->value => [
            RoleName::Sales->value,
            RoleName::Cs->value,
            RoleName::Maintenance->value,
        ],
        RoleName::Sales->value => [
            RoleName::Cs->value,
            RoleName::Maintenance->value,
        ],
    ],

    /*
     | L4 hook: default team type. 'region'/'division' levels arrive later.
     */
    'default_team_type' => 'team',

];
