<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Activating/deactivating an account (DESIGN_HIERARCHY.md batch H7b). Shared by the
 * two surfaces that own the switch — the manager's /team/members area and the admin
 * /users area — because the gate is the same target-scoped ability either way
 * (UserPolicy::setStatus), not an area permission. The route parameter is named
 * differently per surface, hence the two lookups.
 *
 * Defence in depth: this authorize(), the controller's authorize('setStatus'), and
 * AccountStatus::set's own check — no caller can flip the switch unguarded.
 */
class UpdateAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('member') ?? $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('setStatus', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
