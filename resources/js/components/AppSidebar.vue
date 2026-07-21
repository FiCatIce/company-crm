<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Package,
    Receipt,
    ShieldCheck,
    UserCheck,
    UserCog,
    Users,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const allNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        permission: 'dashboard.view',
    },
    {
        title: 'Customers',
        href: '/customers',
        icon: Users,
        // Scope variants (any one reveals it): view.all (global), view.team
        // (manager roll-up), view.own (Sales), view.assigned (CS/maintenance).
        permission: [
            'customer.view.all',
            'customer.view.team',
            'customer.view.own',
            'customer.view.assigned',
        ],
    },
    {
        title: 'Products',
        href: '/products',
        icon: Package,
        permission: 'product.view',
    },
    {
        title: 'Transactions',
        href: '/transactions',
        icon: Receipt,
        permission: ['transaction.view.all', 'transaction.view.own'],
    },
    {
        title: 'Tim Saya',
        href: '/team',
        icon: UsersRound,
        // Read-only hierarchy overview — every role that HAS a team position holds
        // team.view (manager, sales, cs, maintenance); admin has no team, so no menu.
        permission: 'team.view',
    },
    {
        title: 'Anggota Tim',
        href: '/team/members',
        icon: UserCheck,
        // Delegated-creation area: shown to a manager (has creatable types), never
        // the admin (whose path is Users). No single permission expresses that, so
        // it gates on the derived capability rather than a permission string.
        capability: 'manageTeamMembers',
    },
    {
        title: 'Support Saya',
        href: '/team/assignments',
        icon: UserCog,
        // Held only by sales (never admin/manager), so a plain permission gate is
        // unambiguous here — unlike the team-members area's shared user.create.
        permission: 'user.assign',
    },
    {
        title: 'Roles',
        href: '/roles',
        icon: ShieldCheck,
        permission: 'role.manage',
    },
    {
        title: 'Users',
        href: '/users',
        icon: UserCog,
        permission: 'user.view',
    },
];

const page = usePage();

// Permission-gated nav: an item with a `permission` only shows when the user
// holds it (an array means "any of" — OR). The server still enforces access;
// this is UI only.
const mainNavItems = computed(() => {
    const held = page.props.auth?.permissions ?? [];
    const can = page.props.auth?.can;

    return allNavItems.filter((item) => {
        // A derived-capability gate (e.g. team-members) wins when present — no
        // single permission expresses it.
        if (item.capability) {
            return can?.[item.capability] ?? false;
        }

        if (!item.permission) {
            return true;
        }

        const required = Array.isArray(item.permission)
            ? item.permission
            : [item.permission];

        return required.some((permission) => held.includes(permission));
    });
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
