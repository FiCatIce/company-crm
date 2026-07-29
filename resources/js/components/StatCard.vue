<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

// With `href` the card becomes a drill-down link to the list its number was
// counted from (M4: dashboard → pre-filtered /service-tickets). Without it the
// card renders exactly as before.
defineProps<{
    label: string;
    value: number | string;
    icon: Component;
    description?: string;
    href?: string;
}>();
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="flex items-center justify-between gap-4 rounded-xl border border-border bg-card p-6 shadow-sm"
        :class="
            href
                ? 'transition-shadow hover:border-primary/40 hover:shadow-md'
                : undefined
        "
    >
        <div class="space-y-1">
            <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
            <p
                class="text-3xl font-semibold tracking-tight text-foreground tabular-nums"
            >
                {{ value }}
            </p>
            <p v-if="description" class="text-xs text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
        >
            <component :is="icon" class="h-5 w-5" />
        </div>
    </component>
</template>
