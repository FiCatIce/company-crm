<?php

namespace App\Policies;

use App\Enums\InteractionSource;
use App\Enums\PermissionName as P;
use App\Models\Interaction;
use App\Models\User;
use App\Support\HierarchyResolver;

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
     * update/delete permission) or by anyone who can moderate all interactions —
     * and, since H7, only while its CUSTOMER is within the actor's visibility.
     *
     * Without that bound `interaction.manage.all`, held by the (team-scoped)
     * manager preset, let a manager edit or delete any manual interaction in the
     * org by id — the call-log twin of the customer write IDOR. An interaction
     * whose customer is gone falls back to the author/moderator rule.
     */
    protected function manages(User $user, ?Interaction $interaction, string $basePermission): bool
    {
        if ($interaction === null || $interaction->source !== InteractionSource::Manual) {
            return false;
        }

        if (! $user->can($basePermission)) {
            return false;
        }

        // The author always manages their own entry. This path is self-limiting,
        // not an IDOR vector: an interaction can only be authored against a
        // customer the author could see (StoreInteractionRequest scope-checks it),
        // and authorship is durable even if the customer later moves away.
        if ($user->id === $interaction->user_id) {
            return true;
        }

        // Moderating SOMEONE ELSE's entry follows sight. Unbounded,
        // interaction.manage.all let the (team-scoped) manager preset edit or
        // delete any manual interaction in the org by id.
        if (! $user->can(P::InteractionManageAll->value)) {
            return false;
        }

        $customer = $interaction->customer;

        return $customer === null || HierarchyResolver::canSeeCustomer($user, $customer);
    }
}
