<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
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
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->id : null;

        return [
            'name' => ['required', 'string', 'max:50', Rule::notIn(RoleName::values()), Rule::unique('roles', 'name')->ignore($roleId)],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(PermissionName::values())],
        ];
    }
}
