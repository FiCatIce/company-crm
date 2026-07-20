<?php

namespace App\Http\Requests;

use App\Concerns\CustomerValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Customer 360 header's quick owner re-assignment (assigned_to only).
 *
 * A FormRequest rather than an inline $request->validate(), because handing a
 * customer over is an ACCESS GRANT (Customer::scopeVisibleTo matches created_by OR
 * assigned_to) and must obey the SAME hierarchy bound as the full customer form.
 * H7a bounded that field in CustomerValidationRules but this quick-change route
 * carried its own hand-rolled `exists:users,id`, which let a reassigner push a
 * customer to any user in the org — leaking it to another team and dropping it out
 * of their own team's view. Reusing the shared trait is what keeps the two paths
 * from drifting apart again.
 */
class UpdateCustomerOwnerRequest extends FormRequest
{
    use CustomerValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => $this->customerRules()['assigned_to'],
        ];
    }
}
