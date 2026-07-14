<?php

namespace App\Policies;

use App\Enums\PermissionName as P;

class CustomerPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        return [P::CustomerViewAll->value, P::CustomerViewOwn->value];
    }

    protected function createPermission(): string
    {
        return P::CustomerCreate->value;
    }

    protected function updatePermissions(): array
    {
        return [P::CustomerUpdateAll->value, P::CustomerUpdateOwn->value];
    }

    protected function deletePermission(): string
    {
        return P::CustomerDelete->value;
    }
}
