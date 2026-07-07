<?php

namespace App\Concerns;

use App\Models\Reseller;
use App\Rules\NotADescendantReseller;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ResellerValidationRules
{
    /**
     * Validation rules shared by reseller create and update requests.
     * Pass the existing reseller (on update) to enforce cycle-free parenting.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function resellerRules(?Reseller $reseller = null): array
    {
        $parentRules = ['nullable', 'integer', Rule::exists('resellers', 'id')];

        if ($reseller !== null) {
            $parentRules[] = new NotADescendantReseller($reseller);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => $parentRules,
        ];
    }
}
