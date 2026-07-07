<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: number | string;
    icon: Component;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex items-center justify-between gap-4 rounded-xl border border-border bg-card p-6 shadow-sm"
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
    </div>
</template>
