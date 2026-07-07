<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ProductValidationRules
{
    /**
     * Validation rules shared by product create and update requests.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Warranty in whole months; 0 means no warranty. Capped at 50 years.
            'warranty_months' => ['required', 'integer', 'min:0', 'max:600'],
        ];
    }
}
