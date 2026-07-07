<?php

namespace App\Http\Requests;

use App\Concerns\CustomerValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
        return $this->customerRules();
    }
}
