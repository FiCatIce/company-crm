<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The ONE place the app resolves a team-role display label. Backed by
 * config/hierarchy.php so L3 white-label can override labels per-tenant WITHOUT
 * touching any caller (DESIGN_HIERARCHY.md L3 seam). Never hardcode 'Sales' /
 * 'Manager' / 'CS' elsewhere — read them from here.
 */
final class TeamRoleLabels
{
    /**
     * Display label for a role slug. Falls back to a headline-cased slug when the
     * slug is unknown, so a new/custom role never renders blank.
     */
    public static function label(string $roleSlug): string
    {
        /** @var array<string, string> $labels */
        $labels = config('hierarchy.role_labels', []);

        return $labels[$roleSlug] ?? Str::headline($roleSlug);
    }

    /**
     * The full slug => label map, for building dropdowns/legends in one read.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        /** @var array<string, string> $labels */
        $labels = config('hierarchy.role_labels', []);

        return $labels;
    }
}
