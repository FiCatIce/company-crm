<?php

namespace App\Concerns;

use App\Enums\RoleName;
use App\Support\CapabilityResolver;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Validation for a role's `assignable_types` whitelist (DH4), shared by the role
 * builder's create and update requests.
 *
 * Beyond "must be a real role, never admin", it enforces the delegation invariant at
 * CONFIGURATION time: a role may only delegate types whose powers it holds itself.
 * Without this, an admin could quietly place a custom role carrying
 * customer.view.all into a manager's whitelist — which finding #5 showed was the
 * chain that let a manager mint org-wide readers and bypass every cross-team bound
 * from H1–H7. The runtime guard (CapabilityResolver::canCreateUserType/canAssign)
 * refuses those anyway, so validating here is what stops an admin saving a whitelist
 * that silently never works, and says why.
 */
trait AssignableTypeRules
{
    /**
     * @return array<string, mixed>
     */
    protected function assignableTypeRules(): array
    {
        return [
            'assignable_types' => ['sometimes', 'array'],
            'assignable_types.*' => [
                'string',
                Rule::notIn([RoleName::Admin->value]),
                Rule::exists('roles', 'name'),
                $this->withinGrantorReach(),
            ],
        ];
    }

    /**
     * Each delegable type must carry no admin or data power the role being saved
     * lacks. The grantor's permissions are the ones SUBMITTED, so the check reflects
     * the role as it will exist after this request, not as it was before.
     */
    protected function withinGrantorReach(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            /** @var list<string> $granted */
            $granted = array_values((array) $this->input('permissions', []));

            $excess = CapabilityResolver::excessPowersFor($granted, $value);

            if ($excess !== []) {
                $fail(sprintf(
                    'Peran "%s" memiliki akses yang tidak dimiliki peran ini (%s), jadi tidak boleh didelegasikan.',
                    $value,
                    implode(', ', $excess),
                ));
            }
        };
    }
}
