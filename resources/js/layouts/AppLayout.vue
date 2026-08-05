<script setup lang="ts">
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

// R-B: mounted once as a persistent layout (app.ts callback), so breadcrumbs
// arrive through the shared store — pages register via setBreadcrumbs().
const breadcrumbs = useBreadcrumbs();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
