<script setup lang="ts">
defineProps<{ status: string; label: string }>();

// Semantic ticket tones (DESIGN_MAINTENANCE.md M3, same system as
// CustomerStatusBadge): received=slate, in_progress=amber, done=green,
// cancelled=red.
const tones: Record<string, string> = {
    received: 'bg-slate-100 text-slate-600',
    in_progress: 'bg-amber-100 text-amber-700',
    done: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
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
