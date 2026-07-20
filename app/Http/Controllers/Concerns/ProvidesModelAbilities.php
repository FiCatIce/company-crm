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
     * CLASS-LEVEL abilities for a resource, surfaced to the UI — "may this user
     * create/edit/delete this kind of record at all", never "may they touch THIS
     * row". Row-level permission is per-record and belongs on the row itself
     * (can_edit / can_delete), because ownership- and hierarchy-scoped policies
     * answer differently for every record.
     *
     * Passing the CLASS (not a blank instance) is what makes that honest: a blank
     * `new Customer` has no owner, so a scoped policy answered "false" for it and
     * the UI hid Edit/Delete on every row — even the user's own. Laravel resolves
     * a class-name check by calling the policy with a null model, which those
     * policies treat as the class-level question.
     *
     * @param  class-string<Model>  $modelClass
     * @return array<string, bool>
     */
    protected function abilities(Request $request, string $modelClass): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('create', $modelClass),
            'update' => $user->can('update', $modelClass),
            'delete' => $user->can('delete', $modelClass),
        ];
    }
}
