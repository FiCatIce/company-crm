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
            'reseller_id' => [
                'required', 'integer', Rule::exists('resellers', 'id'),
                // The reseller must be the one that owns the selected customer, so
                // sales can't be misattributed to an unrelated reseller.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $customerId = $this->input('customer_id');
                    $ownerId = $customerId ? Customer::whereKey($customerId)->value('reseller_id') : null;

                    if ($ownerId !== null && (int) $value !== (int) $ownerId) {
                        $fail('Reseller harus sama dengan reseller pemilik customer.');
                    }
                },
            ],
            // A purchase can't be recorded in the future.
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            // Sale value — optional (legacy rows have none); fits decimal(14,2).
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }
}
