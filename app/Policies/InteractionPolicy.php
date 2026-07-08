<?php

namespace App\Policies;

use App\Enums\InteractionSource;
use App\Models\Interaction;
use App\Models\User;

/**
 * Interactions inherit the shared role matrix, but auto-logged records
 * (CTI / import) are an immutable audit trail — only manually logged
 * interactions may be edited or deleted. The source check is per-row.
 */
class InteractionPolicy extends RolePolicy
{
    public function update(User $user, ?Interaction $interaction = null): bool
    {
        return parent::update($user)
            && (! $interaction || $interaction->source === InteractionSource::Manual);
    }

    public function delete(User $user, ?Interaction $interaction = null): bool
    {
        return parent::delete($user)
            && (! $interaction || $interaction->source === InteractionSource::Manual);
    }
}
