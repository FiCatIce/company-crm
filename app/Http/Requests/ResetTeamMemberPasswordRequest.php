<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * A manager resetting a team member's password (DESIGN_HIERARCHY.md batch H4 —
 * the one limited edit; the deactivate/offboard lifecycle is H7). authorize()
 * gates the AREA; the controller additionally authorizes the SPECIFIC member
 * ('manageTeamMember') so a manager can only touch their own book.
 */
class ResetTeamMemberPasswordRequest extends FormRequest
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
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
