import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// L3 white-label: role display labels, resolved SERVER-side (override → config
// default) and shared by HandleInertiaRequests as `labels.roles` /
// `labels.rolesLower`. Vue copy must read labels through this composable — never
// hardcode 'Sales'/'CS'/'Manager' in templates (the L3-B migration target).
//
// Both maps come pre-resolved so the acronym-aware lowercasing rule lives in
// exactly one implementation (TeamRoleLabels::labelLower). The fallbacks below
// only ever fire for a slug outside the shared map (e.g. a custom role), which
// the backend would resolve to the same headline form.

/** 'regional-manager' → 'Regional Manager' (mirror of Str::headline for slugs). */
function headline(slug: string): string {
    return slug
        .split(/[-_\s]+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * Lowercase each word unless it is an all-caps acronym ('CS' stays 'CS').
 * Exported for previewing a DRAFT label (settings/Labels) — the shared maps
 * only carry the saved state.
 */
export function lowerWords(label: string): string {
    return label
        .split(' ')
        .map((word) =>
            word === word.toUpperCase() ? word : word.toLowerCase(),
        )
        .join(' ');
}

export function useLabels() {
    const page = usePage();

    const roles = computed(
        () => (page.props.labels?.roles ?? {}) as Record<string, string>,
    );
    const rolesLower = computed(
        () => (page.props.labels?.rolesLower ?? {}) as Record<string, string>,
    );

    /** Title Case label for headings/labels: label('sales') → 'Sales'. */
    function label(slug: string): string {
        return roles.value[slug] ?? headline(slug);
    }

    /** Sentence-context label: labelLower('sales') → 'sales', 'cs' → 'CS'. */
    function labelLower(slug: string): string {
        return rolesLower.value[slug] ?? lowerWords(label(slug));
    }

    return { label, labelLower };
}
