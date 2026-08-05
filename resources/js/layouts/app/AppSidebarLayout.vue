<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import CallDock from '@/components/CallDock.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!-- Dormant until CTI 4c; persistent-shell slot for the call bar. -->
            <CallDock />
            <!-- R-B: page padding lives here now, not in every page. -->
            <div class="flex flex-1 flex-col p-4 lg:p-6">
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
