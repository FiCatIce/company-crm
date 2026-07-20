<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\UserOffboarding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Offboarding a user (DESIGN_HIERARCHY.md batch H7c). Like the status switch, the
 * gate is the target-scoped ability rather than an area permission, so the same
 * request serves the manager's /team/members flow and the admin's /users flow.
 *
 * The successor is constrained to the eligible set (Rule::in) for a friendly 422
 * instead of a 403; UserOffboarding re-checks it as the backstop, so a hand-rolled
 * POST cannot smuggle in an ineligible successor.
 */
class OffboardUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('member') ?? $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('offboard', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('member') ?? $this->route('user');

        $eligible = $target instanceof User
            ? UserOffboarding::eligibleSuccessors($target)->pluck('id')->all()
            : [];

        return [
            'successor_id' => ['required', 'integer', Rule::in($eligible)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'successor_id.required' => 'Pilih pengganti terlebih dahulu.',
            'successor_id.in' => 'Pengganti tidak memenuhi syarat (harus aktif, peran sama, dan satu tim atau belum punya tim).',
        ];
    }
}
