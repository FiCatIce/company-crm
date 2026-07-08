<script setup lang="ts">
defineProps<{ outcome: string; label: string }>();

const tones: Record<string, string> = {
    answered: 'bg-green-100 text-green-700',
    missed: 'bg-red-100 text-red-700',
    no_answer: 'bg-red-100 text-red-700',
    voicemail: 'bg-amber-100 text-amber-700',
    busy: 'bg-amber-100 text-amber-700',
    wrong_number: 'bg-slate-100 text-slate-600',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="tones[outcome] ?? 'bg-muted text-muted-foreground'"
    >
        {{ label }}
    </span>
</template>
