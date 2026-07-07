<?php

namespace App\Rules;

use App\Models\Reseller;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensures a reseller's chosen parent does not create a cycle: the parent may be
 * neither the reseller itself nor any of its descendants.
 */
class NotADescendantReseller implements ValidationRule
{
    public function __construct(private Reseller $reseller) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $candidateId = (int) $value;

        if ($candidateId === $this->reseller->id) {
            $fail('Reseller tidak boleh menjadi induk dari dirinya sendiri.');

            return;
        }

        if (in_array($candidateId, $this->reseller->descendantIds(), true)) {
            $fail('Induk tidak boleh salah satu turunan (anak/cucu) reseller ini.');
        }
    }
}
