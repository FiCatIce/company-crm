<?php

namespace App\Concerns;

use App\Enums\InteractionDirection;
use App\Enums\InteractionOutcome;
use App\Enums\InteractionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait InteractionValidationRules
{
    /**
     * Validation rules shared by interaction create and update requests.
     * (source is set server-side to "manual" — never accepted from input.)
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function interactionRules(): array
    {
        return [
            'type' => ['required', Rule::enum(InteractionType::class)],
            'direction' => [
                'nullable',
                Rule::enum(InteractionDirection::class),
                // A call must record its direction (incoming vs outgoing).
                Rule::requiredIf(fn (): bool => $this->input('type') === InteractionType::Call->value),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'outcome' => ['nullable', Rule::enum(InteractionOutcome::class)],
            'duration_sec' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
