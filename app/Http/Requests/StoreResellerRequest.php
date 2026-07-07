<?php

namespace App\Http\Requests;

use App\Concerns\ResellerValidationRules;
use App\Models\Reseller;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreResellerRequest extends FormRequest
{
    use ResellerValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Reseller::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->resellerRules();
    }
}
