<?php

namespace App\Http\Requests;

use App\Concerns\InteractionValidationRules;
use App\Models\Customer;
use App\Models\Interaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInteractionRequest extends FormRequest
{
    use InteractionValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();
        $customer = $this->route('customer');

        // Logging an interaction requires BOTH the create permission AND that the
        // target customer is within the user's scope — otherwise a Sales user could
        // POST a call onto another rep's customer by id (write IDOR). Mirrors the
        // customer view scope so the two never diverge (DESIGN_RBAC.md §6).
        return $user !== null
            && $customer instanceof Customer
            && $user->can('create', Interaction::class)
            && $user->can('view', $customer);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->interactionRules();
    }
}
