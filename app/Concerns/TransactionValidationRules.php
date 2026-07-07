<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait TransactionValidationRules
{
    /**
     * Validation rules shared by transaction create and update requests.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function transactionRules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'reseller_id' => ['required', 'integer', Rule::exists('resellers', 'id')],
            // A purchase can't be recorded in the future.
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
