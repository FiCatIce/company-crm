<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\CapabilityResolver;
use App\Support\HierarchyResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A sales user wiring support (CS/maintenance) to themselves (DH5 / batch H5).
 *
 * The submitted ids are pinned to the actor's OWN candidate pool — same team,
 * whitelisted type — so a rep can neither reach outside their team nor assign a
 * non-support role. SupportAssignments::assign re-checks the type server-side
 * (the H2 backstop), and the actor is always the authenticated user: no field
 * names "which sales", so assigning to someone else is structurally impossible.
 */
class StoreSupportAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAssignments', User::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $actor */
        $actor = $this->user();

        $pool = HierarchyResolver::supportCandidateIds(
            $actor,
            CapabilityResolver::assignableCandidateTypes($actor),
        );

        return [
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::in($pool)],
        ];
    }
}
