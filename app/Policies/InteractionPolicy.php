<?php

namespace App\Policies;

use App\Enums\InteractionSource;
use App\Enums\PermissionName as P;
use App\Models\Interaction;
use App\Models\User;

/**
 * Manually logged interactions may be edited/deleted by their author, or by
 * anyone holding `interaction.manage.all` (managers/admin). Auto-logged CTI /
 * import records are an immutable audit trail — nobody may edit or delete them.
 * Creating and viewing are inherited from ResourcePolicy.
 */
class InteractionPolicy extends ResourcePolicy
{
    protected function viewPermissions(): array
    {
        return [P::InteractionViewAll->value, P::InteractionViewOwn->value];
    }

    protected function createPermission(): string
    {
        return P::InteractionCreate->value;
    }

    protected function updatePermissions(): array
    {
        return [P::InteractionUpdate->value];
    }

    protected function deletePermission(): string
    {
        return P::InteractionDelete->value;
    }

    public function update(User $user, ?Interaction $interaction = null): bool
    {
        return $this->manages($user, $interaction, P::InteractionUpdate->value);
    }

    public function delete(User $user, ?Interaction $interaction = null): bool
    {
        return $this->manages($user, $interaction, P::InteractionDelete->value);
    }

    /**
     * A manual interaction is manageable by its author (with the base
     * update/delete permission) or by anyone who can moderate all interactions.
     */
    protected function manages(User $user, ?Interaction $interaction, string $basePermission): bool
    {
        if ($interaction === null || $interaction->source !== InteractionSource::Manual) {
            return false;
        }

        if (! $user->can($basePermission)) {
            return false;
        }

        // Author manages their own; managers/admin may manage anyone's.
        return $user->id === $interaction->user_id
            || $user->can(P::InteractionManageAll->value);
    }
}
