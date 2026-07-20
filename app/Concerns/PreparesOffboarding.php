<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\TeamRoleLabels;
use App\Support\UserOffboarding;

/**
 * Shared payload for the offboard screen (DESIGN_HIERARCHY.md batch H7c). Both the
 * manager area (/team/members) and the admin area (/users) render the SAME page —
 * a manager can never reach a peer manager, so a departing manager is necessarily
 * offboarded by an admin, and the two flows have to stay identical in what they
 * show. Keeping the payload here means the holdings summary and the successor list
 * cannot drift apart between the two surfaces.
 */
trait PreparesOffboarding
{
    /**
     * @return array<string, mixed>
     */
    private function offboardPayload(User $target, string $submitUrl, string $cancelUrl): array
    {
        return [
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'email' => $target->email,
                'type' => $this->offboardTypeView($target),
                'is_active' => $target->is_active,
            ],
            'holdings' => UserOffboarding::holdings($target),
            'successors' => UserOffboarding::eligibleSuccessors($target)
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'type' => $this->offboardTypeView($u),
                ])
                ->values()
                ->all(),
            'submitUrl' => $submitUrl,
            'cancelUrl' => $cancelUrl,
        ];
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private function offboardTypeView(User $user): ?array
    {
        $slug = $user->getRoleNames()->first();

        return is_string($slug)
            ? ['value' => $slug, 'label' => TeamRoleLabels::label($slug)]
            : null;
    }
}
