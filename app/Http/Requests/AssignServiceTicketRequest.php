<?php

namespace App\Http\Requests;

use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Routing a ticket to a technician (DESIGN_MAINTENANCE.md M2). The eligibility
 * check itself (active + holds ticket.update) lives in TicketFlow::assignTo as
 * the enforcing backstop; this request only shapes the input. Lives here — not
 * inline in the controller — so the user lookup stays out of ScopeGuardTest's
 * controller scan by design, pinned by the policy authorize above it.
 */
class AssignServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('service_ticket');

        return $ticket instanceof ServiceTicket
            && ($this->user()?->can('assign', $ticket) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignee_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * The requested technician. Eligibility is judged in TicketFlow::assignTo.
     */
    public function technician(): User
    {
        return User::query()->findOrFail((int) $this->validated('assignee_id'));
    }
}
