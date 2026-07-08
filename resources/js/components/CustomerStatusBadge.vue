<script setup lang="ts">
defineProps<{ status: string; label: string }>();

// Semantic status tones (kept separate from the blue accent): lead=amber,
// active=green, inactive=slate, churned=red.
const tones: Record<string, string> = {
    lead: 'bg-amber-100 text-amber-700',
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-slate-100 text-slate-600',
    churned: 'bg-red-100 text-red-700',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="tones[status] ?? 'bg-muted text-muted-foreground'"
    >
        {{ label }}
    </span>
</template>
