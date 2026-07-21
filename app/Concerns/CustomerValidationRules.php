<?php

namespace App\Concerns;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\PermissionName as P;
use App\Models\User;
use App\Support\HierarchyResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CustomerValidationRules
{
    /**
     * Validation rules shared by customer create and update requests.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function customerRules(): array
    {
        return [
            // L2-A: reseller_id is no longer accepted (dropped from validated()), so
            // new customers are created with a null reseller — the first step of
            // deprecating the entity. The column and existing values stay untouched;
            // the drop is the destructive L2-D.
            // Owning agent. This is an ACCESS GATE, not mere attribution: since B1/H3
            // Customer::scopeVisibleTo matches created_by OR assigned_to, so handing a
            // customer over GRANTS the recipient sight of it. H7 therefore bounds the
            // recipient to the actor's own hierarchy — otherwise a rep could push a
            // customer to an arbitrary org user, both leaking it outward and dropping
            // it out of their own team's view. null = unassigned.
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id'), $this->ownerWithinHierarchy()],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            // Optional so callers that omit it fall back to the DB default ('active').
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'source' => ['nullable', Rule::enum(CustomerSource::class)],
        ];
    }

    /**
     * The owner must sit inside the actor's hierarchy — themselves or a teammate.
     * A genuinely global role (customer.view.all) is unrestricted, since it can
     * already see every customer either way. Mirrors the customer_id closure in
     * TransactionValidationRules.
     */
    protected function ownerWithinHierarchy(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var User|null $actor */
            $actor = $this->user();

            if ($value === null || $actor === null || $actor->can(P::CustomerViewAll->value)) {
                return;
            }

            $allowed = array_unique([
                (int) $actor->id,
                ...HierarchyResolver::teamMemberIds($actor),
            ]);

            if (! in_array((int) $value, $allowed, true)) {
                $fail('Agen tujuan harus Anda sendiri atau anggota tim Anda.');
            }
        };
    }
}
