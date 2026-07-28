<?php

namespace App\Http\Requests;

use App\Enums\TicketCoverage;
use App\Enums\TicketPriority;
use App\Models\ServiceTicket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Work-field edits only (DESIGN_MAINTENANCE.md M2): notes, item details, the
 * coverage call, priority. Status and its stamps NEVER pass through here — they
 * move exclusively via the TicketFlow transitions.
 */
class UpdateServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('service_ticket');

        return $ticket instanceof ServiceTicket
            && ($this->user()?->can('update', $ticket) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'complaint' => ['sometimes', 'required', 'string', 'max:5000'],
            'item_description' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:64'],
            'work_notes' => ['nullable', 'string', 'max:10000'],
            'coverage' => ['sometimes', Rule::enum(TicketCoverage::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }
}
