<?php

namespace App\Concerns;

use App\Enums\PermissionName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait UserValidationRules
{
    /**
     * Profile + role rules shared by user create and update. Pass the edited
     * user's id on update so the unique-email check ignores their own row.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function userProfileRules(?int $ignoreUserId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreUserId)],
            'extension' => ['nullable', 'string', 'max:20'],
            // Any existing role — a system role (RoleName) or an admin-built custom
            // one. Never trust an arbitrary string: it must be a real role row.
            'role' => ['required', Rule::exists('roles', 'name')],
        ];
    }

    /**
     * The direct-permission checklist (Edit only). Each entry must be a real
     * permission string — server never trusts the client's list blindly.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function permissionRules(): array
    {
        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::in(PermissionName::values())],
        ];
    }
}
