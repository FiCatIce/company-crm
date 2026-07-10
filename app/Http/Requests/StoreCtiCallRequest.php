<?php

namespace App\Http\Requests;

use App\Enums\InteractionOutcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCtiCallRequest extends FormRequest
{
    /**
     * The bearer token must carry the cti:ingest ability. auth:sanctum has
     * already established the integration user; here we gate the scope.
     */
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('cti:ingest') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'external_call_id' => ['required', 'string', 'max:64'],
            'direction' => ['required', 'in:in,out'],
            'from_number' => ['required', 'string'],
            'to_number' => ['required', 'string'],
            'agent_extension' => ['nullable', 'string'],
            'agent_email' => ['nullable', 'email'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
            'answered' => ['required', 'boolean'],
            'duration_sec' => ['nullable', 'integer', 'min:0'],
            'outcome' => ['nullable', Rule::enum(InteractionOutcome::class)],
            'recording_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
