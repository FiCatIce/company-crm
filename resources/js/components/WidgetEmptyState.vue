<script setup lang="ts">
import type { Component } from 'vue';

// Calm zero-data placeholder for the dashboard widgets: a muted icon in a soft
// circle above a single line — the same restrained pattern the index pages use,
// so an empty install reads as "nothing yet", never "broken".
defineProps<{
    icon: Component;
    message: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center gap-2 px-6 py-10 text-center">
        <div
            class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <component :is="icon" class="size-5" />
        </div>
        <p class="text-sm text-muted-foreground">{{ message }}</p>
        <slot />
    </div>
</template>
