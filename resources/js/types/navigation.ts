import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    // When set, the item only renders if the user holds the permission. An array
    // means "any of these" (OR) — e.g. a resource with `.view.all`/`.view.own`
    // scope variants shows if the user holds either.
    permission?: string | string[];
};
