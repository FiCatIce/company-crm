<script setup lang="ts">
import type { Component } from 'vue';

// Compact summary card for the top of the index pages: a large metric, a
// short label, a subtle sub-detail line, and a muted icon tucked into a soft
// rounded container in the top-right corner. Icons stay muted (not the
// corporate blue) so the row reads as quiet context, keeping blue accent-only.
defineProps<{
    label: string;
    value: number | string;
    icon: Component;
    detail?: string;
}>();
</script>

<template>
    <div
        class="flex items-start justify-between gap-4 rounded-xl border border-border bg-card p-5 shadow-sm"
    >
        <div class="space-y-1">
            <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
            <p
                class="text-3xl font-semibold tracking-tight text-foreground tabular-nums"
            >
                {{ value }}
            </p>
            <p v-if="detail" class="text-xs text-muted-foreground">
                {{ detail }}
            </p>
        </div>
        <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
        >
            <component :is="icon" class="size-4" />
        </div>
    </div>
</template>
