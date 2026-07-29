<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Support\TeamRoleLabels;
use Illuminate\Foundation\Http\FormRequest;

/**
 * L3-C: the admin label-settings form. One `overrides` map, keys limited to
 * the overridable role slugs — the `array:` rule REJECTS any extra key, so an
 * 'admin' entry forged outside the UI fails validation (D2: the admin identity
 * is never renamable; the resolution layer ignores such a row as backstop).
 *
 * Max 40 chars: the longest composed surfaces ("<label> di Tim" stat card,
 * "<label> & <label> di tim" heading) still fit their cards at 40 without
 * breaking the grid — they wrap, never overflow. Empty/whitespace is allowed
 * and means RESET to the default (the controller deletes the row).
 */
class UpdateLabelOverridesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::SettingLabels->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overrides' => ['required', 'array:'.implode(',', TeamRoleLabels::overridableSlugs())],
            'overrides.*' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'overrides.array' => 'Slug label tidak dikenal.',
            'overrides.*.max' => 'Label maksimal 40 karakter.',
        ];
    }
}
