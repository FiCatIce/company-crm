<?php

namespace App\Policies;

use App\Enums\PermissionName as P;

class ResellerPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        return [P::ResellerView->value];
    }

    protected function createPermission(): string
    {
        return P::ResellerCreate->value;
    }

    protected function updatePermissions(): array
    {
        return [P::ResellerUpdate->value];
    }

    protected function deletePermission(): string
    {
        return P::ResellerDelete->value;
    }
}
