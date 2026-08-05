import { usePage } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import type { ComputedRef } from 'vue';
import type { BreadcrumbItem } from '@/types';

/*
 * R-B: the shell is a persistent layout, and Inertia's layout callback can't
 * receive props from pages — so breadcrumbs flow through this shared store
 * instead. This is the ONLY breadcrumb mechanism (no server-side share:
 * labels are client-computed from wayfinder routes and page props, and
 * middleware must not know UI navigation structure).
 *
 * The entry is keyed by the registering page's component name and the layout
 * only reads it while that same component is displayed, so a page that never
 * registers shows an empty trail instead of the previous page's leftovers.
 *
 * No SSR concern: the project has no ssr.ts entry, so this module-level ref
 * lives per browser tab only.
 */

type Registration = {
    component: string;
    items: () => BreadcrumbItem[];
};

const registration = shallowRef<Registration | null>(null);

/**
 * Register the current page's breadcrumbs — call once in page setup. Pass a
 * getter so crumbs derived from props stay reactive when Inertia swaps props
 * on a same-component visit.
 */
export function setBreadcrumbs(items: () => BreadcrumbItem[]): void {
    registration.value = { component: usePage().component, items };
}

/** Read the displayed page's breadcrumbs — layout side. */
export function useBreadcrumbs(): ComputedRef<BreadcrumbItem[]> {
    const page = usePage();

    return computed(() =>
        registration.value && registration.value.component === page.component
            ? registration.value.items()
            : [],
    );
}
