<?php

namespace App\Policies;

use App\Enums\InteractionSource;
use App\Models\Interaction;
use App\Models\User;

/**
 * Manually logged interactions may be edited/deleted by their author, or by a
 * supervisor/admin (DELETE_ROLES). Auto-logged CTI / import records are an
 * immutable audit trail — nobody may edit or delete them. Creating is open to
 * any CRM role (inherited from RolePolicy).
 */
class InteractionPolicy extends RolePolicy
{
    public function update(User $user, ?Interaction $interaction = null): bool
    {
        return $this->manages($user, $interaction);
    }

    public function delete(User $user, ?Interaction $interaction = null): bool
    {
        return $this->manages($user, $interaction);
    }

    protected function manages(User $user, ?Interaction $interaction): bool
    {
        if ($interaction === null || $interaction->source !== InteractionSource::Manual) {
            return false;
        }

        if (! $user->hasAnyRole(static::MANAGE_ROLES)) {
            return false;
        }

        // Author manages their own; supervisors/admins may manage anyone's.
        return $user->id === $interaction->user_id
            || $user->hasAnyRole(static::DELETE_ROLES);
    }
}
