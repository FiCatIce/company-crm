<?php

namespace App\Concerns;

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
            'reseller_id' => ['required', 'integer', Rule::exists('resellers', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
