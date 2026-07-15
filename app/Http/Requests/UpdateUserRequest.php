<?php

namespace App\Http\Requests;

use App\Concerns\UserValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use UserValidationRules;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $ignoreId = $target instanceof User ? $target->id : null;

        return [
            ...$this->userProfileRules($ignoreId),
            // Password optional on edit — only rotated when provided.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            ...$this->permissionRules(),
        ];
    }
}
