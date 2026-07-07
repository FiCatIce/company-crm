<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ProvidesModelAbilities
{
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
