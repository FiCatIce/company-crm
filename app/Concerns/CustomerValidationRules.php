<?php

namespace App\Concerns;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
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
            // Owning agent (attribution/filter only — NOT an access gate); null = unassigned.
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            // Optional so callers that omit it fall back to the DB default ('active').
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'source' => ['nullable', Rule::enum(CustomerSource::class)],
        ];
    }
}
