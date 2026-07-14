<?php

namespace App\Policies;

use App\Enums\PermissionName as P;

class TransactionPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        return [P::TransactionViewAll->value, P::TransactionViewOwn->value];
    }

    protected function createPermission(): string
    {
        return P::TransactionCreate->value;
    }

    protected function updatePermissions(): array
    {
        return [P::TransactionUpdate->value];
    }

    protected function deletePermission(): string
    {
        return P::TransactionDelete->value;
    }
}
