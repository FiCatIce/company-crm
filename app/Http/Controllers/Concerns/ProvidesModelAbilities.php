<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ProvidesModelAbilities
{
    /**
     * Whether the user may see monetary amounts (transaction values, totals,
     * revenue). True when they can view transactions at all — a user without a
     * transaction view permission never sees money (DESIGN_RBAC.md §4.3).
     */
    protected function canSeeAmount(User $user): bool
    {
        return $user->can(PermissionName::TransactionViewAll->value)
            || $user->can(PermissionName::TransactionViewOwn->value);
    }

    /**
     * Role-based abilities for the given model, surfaced to the UI. The abilities
     * are row-independent (the policies gate on role, not ownership), so a blank
     * instance is sufficient for the update/delete checks.
     *
     * @return array<string, bool>
     */
    protected function abilities(Request $request, Model $model): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('create', $model::class),
            'update' => $user->can('update', $model),
            'delete' => $user->can('delete', $model),
        ];
    }
}
