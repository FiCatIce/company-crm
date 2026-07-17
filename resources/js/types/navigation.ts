import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import type { AuthCapabilities } from '@/types/auth';

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
    // When set, the item only renders if the derived capability flag is true
    // (auth.can[...]). Used where no single permission expresses the gate — e.g.
    // "delegated creator, not admin" for the team-members area (hierarchy H4).
    capability?: keyof AuthCapabilities;
};
