<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\CapabilityResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Delegated team-member creation (DESIGN_HIERARCHY.md batch H4). A manager submits
 * a name/email/password + a TYPE from their whitelist — never a permission list.
 *
 * Three layers of defence guard the type: authorize() gates the area, the `type`
 * rule pins it to the actor's creatable set (a friendly 422 on a bad value), and
 * DelegatedUserCreator re-checks canCreateUserType before writing (the hard
 * backstop, covered by the H2 escalation tests).
 */
class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTeamMembers', User::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $actor */
        $actor = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'extension' => ['nullable', 'string', 'max:20'],
            // Only a type this manager may actually create — the escalation-guarded
            // whitelist, so 'admin'/'supervisor' can never be submitted here.
            'type' => ['required', Rule::in(CapabilityResolver::creatableTypes($actor))],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
