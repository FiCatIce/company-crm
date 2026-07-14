<?php

namespace App\Policies;

use App\Enums\PermissionName as P;

class ProductPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        return [P::ProductView->value];
    }

    protected function createPermission(): string
    {
        return P::ProductCreate->value;
    }

    protected function updatePermissions(): array
    {
        return [P::ProductUpdate->value];
    }

    protected function deletePermission(): string
    {
        return P::ProductDelete->value;
    }
}
