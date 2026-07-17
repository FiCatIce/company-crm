<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::RoleManage->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A custom role name — must be unique and must not shadow a system
            // role slug (those are code-defined and locked).
            'name' => ['required', 'string', 'max:50', Rule::notIn(RoleName::values()), Rule::unique('roles', 'name')],
            // Server never trusts the client's list blindly — each must be real.
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(PermissionName::values())],
            // DH4 capability config (optional): the user types this role may
            // create/assign. Each must be a real role and never `admin` — the
            // unrestricted role is never delegable.
            'assignable_types' => ['sometimes', 'array'],
            'assignable_types.*' => ['string', Rule::notIn([RoleName::Admin->value]), Rule::exists('roles', 'name')],
        ];
    }
}
