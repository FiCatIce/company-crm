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
        $currentName = $role instanceof Role ? $role->name : null;

        // A role may KEEP its own (system) slug, but must not rename TO a
        // different system slug — that would let, say, supervisor masquerade as
        // sales, or anything grab the locked `admin` slug.
        $reservedSlugs = array_values(array_filter(
            RoleName::values(),
            fn (string $slug): bool => $slug !== $currentName,
        ));

        return [
            'name' => ['required', 'string', 'max:50', Rule::notIn($reservedSlugs), Rule::unique('roles', 'name')->ignore($roleId)],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(PermissionName::values())],
        ];
    }
}
