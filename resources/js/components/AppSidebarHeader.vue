<script setup lang="ts">
import { computed } from 'vue';
import AppUserMenu from '@/components/AppUserMenu.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const pageTitle = computed(
    () => props.breadcrumbs[props.breadcrumbs.length - 1]?.title ?? '',
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-border bg-background px-4 sm:px-6"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1 text-muted-foreground" />
            <div
                class="mx-1 hidden h-5 w-px bg-border sm:block"
                aria-hidden="true"
            />
            <h1 class="truncate text-base font-semibold text-foreground">
                {{ pageTitle }}
            </h1>
        </div>

        <AppUserMenu />
    </header>
</template>
