<?php

namespace App\Concerns;

use App\Models\Customer;
use Closure;
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
            'customer_id' => [
                'required', 'integer', Rule::exists('customers', 'id'),
                // Own-scoped users (Sales) may only transact for customers within
                // their visibility scope — never another rep's customer. Skipped
                // when the id doesn't exist (the exists rule reports that).
                function (string $attribute, mixed $value, Closure $fail): void {
                    $user = $this->user();

                    if ($user === null || ! Customer::whereKey($value)->exists()) {
                        return;
                    }

                    if (! Customer::visibleTo($user)->whereKey($value)->exists()) {
                        $fail('Customer tidak valid.');
                    }
                },
            ],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            // L2-A: reseller_id is no longer accepted. Dropping the rule means it
            // falls out of validated(), so new transactions are recorded with a null
            // reseller — the deprecation direction. This also retires the old
            // "reseller must match the customer's reseller" data-integrity closure,
            // which only existed to keep the two reseller pointers in sync.
            // A purchase can't be recorded in the future.
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            // Sale value — optional (legacy rows have none); fits decimal(14,2).
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }
}
