<?php

namespace App\Http\Requests;

use App\Concerns\InteractionValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInteractionRequest extends FormRequest
{
    use InteractionValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('interaction')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->interactionRules();
    }
}
