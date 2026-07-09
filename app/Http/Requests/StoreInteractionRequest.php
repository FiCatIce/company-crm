<?php

namespace App\Http\Requests;

use App\Concerns\InteractionValidationRules;
use App\Models\Interaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInteractionRequest extends FormRequest
{
    use InteractionValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Interaction::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->interactionRules();
    }
}
