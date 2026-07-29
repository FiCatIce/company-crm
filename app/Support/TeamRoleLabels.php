<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\LabelOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The ONE place the app resolves a team-role display label. Resolution (L3-A,
 * ketok D1/D2): admin override in `label_overrides` (cached) → default in
 * config/hierarchy.php → headline-cased slug, so a new/custom role never
 * renders blank. Never hardcode 'Sales' / 'Manager' / 'CS' elsewhere — read
 * them from here (or, in Vue, from the shared `labels` prop / useLabels()).
 *
 * The 'admin' slug is deliberately un-overridable (D2): it is the locked
 * system identity, and masking it would only confuse audit trails.
 *
 * Cost per render: one Cache::rememberForever hit per request (memoized in
 * $overrides after that). LabelOverride writes flush both layers.
 */
final class TeamRoleLabels
{
    public const CACHE_KEY = 'labels.role_overrides';

    /**
     * Per-request memo of the override map (null = not read yet this request).
     *
     * Safe under PHP-FPM (process state dies with the request). Under a
     * long-running worker (Octane/queue) it goes stale after another process
     * writes an override, until this worker recycles or writes one itself —
     * acceptable for display labels; revisit if the app ever moves to Octane.
     *
     * @var array<string, string>|null
     */
    private static ?array $overrides = null;

    /**
     * Display label for a role slug — Title Case, for headings and labels.
     */
    public static function label(string $roleSlug): string
    {
        /** @var array<string, string> $defaults */
        $defaults = config('hierarchy.role_labels', []);

        return self::overrides()[$roleSlug] ?? $defaults[$roleSlug] ?? Str::headline($roleSlug);
    }

    /**
     * Sentence-context variant (D4 kapitalisasi otomatis): each word lowercased
     * UNLESS it is an all-caps acronym — "Sales" → "sales", "Account Manager" →
     * "account manager", but "CS" stays "CS". For copy like
     * "…dari {sales} yang menugaskan Anda".
     */
    public static function labelLower(string $roleSlug): string
    {
        return self::lowerWords(self::label($roleSlug));
    }

    /**
     * The full slug => RESOLVED label map (config slugs, overrides applied) —
     * dropdowns, legends, and the Inertia share in one read.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        /** @var array<string, string> $defaults */
        $defaults = config('hierarchy.role_labels', []);

        $slugs = array_keys($defaults);

        return array_combine($slugs, array_map(self::label(...), $slugs));
    }

    /**
     * all(), in the sentence-context form. Shared to the frontend alongside
     * all() so the acronym rule lives in exactly one implementation (PHP).
     *
     * @return array<string, string>
     */
    public static function allLower(): array
    {
        return array_map(self::lowerWords(...), self::all());
    }

    /**
     * The slugs an admin may rename — every configured role except admin (D2).
     * The settings panel and its request validation both read THIS list, so
     * the offered rows and the accepted keys can never drift apart.
     *
     * @return list<string>
     */
    public static function overridableSlugs(): array
    {
        /** @var array<string, string> $defaults */
        $defaults = config('hierarchy.role_labels', []);

        return array_values(array_diff(array_keys($defaults), [RoleName::Admin->value]));
    }

    /**
     * The config DEFAULT for a slug — deliberately ignoring any override, for
     * surfaces that show "default vs override" side by side (settings panel).
     */
    public static function defaultLabel(string $roleSlug): string
    {
        /** @var array<string, string> $defaults */
        $defaults = config('hierarchy.role_labels', []);

        return $defaults[$roleSlug] ?? Str::headline($roleSlug);
    }

    /**
     * Drop the cached override map (both the request memo and the store).
     * Called by every LabelOverride write, and by tests.
     */
    public static function flush(): void
    {
        self::$overrides = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function overrides(): array
    {
        if (self::$overrides !== null) {
            return self::$overrides;
        }

        /** @var array<string, string> $rows */
        $rows = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => LabelOverride::query()->pluck('label', 'key')->all(),
        );

        // D2: the admin identity is never masked — an override row for it
        // (however it got written) simply does not apply.
        unset($rows[RoleName::Admin->value]);

        return self::$overrides = $rows;
    }

    private static function lowerWords(string $label): string
    {
        return implode(' ', array_map(
            fn (string $word): string => $word === mb_strtoupper($word) ? $word : mb_strtolower($word),
            explode(' ', $label),
        ));
    }
}
