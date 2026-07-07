<?php

namespace App\Http\Requests;

use App\Concerns\ResellerValidationRules;
use App\Models\Reseller;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateResellerRequest extends FormRequest
{
    use ResellerValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('reseller')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Reseller $reseller */
        $reseller = $this->route('reseller');

        return $this->resellerRules($reseller);
    }
}
