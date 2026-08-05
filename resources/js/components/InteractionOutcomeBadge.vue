<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';

defineProps<{ outcome: string; label: string }>();

// Six call outcomes fold onto the four-state vocabulary, following the exact
// pre-R-D color grouping: answered=ok; missed & no_answer=busy (red — a call
// that demands follow-up); voicemail & the "busy line" outcome=warn (amber —
// partial contact); wrong_number=idle. NOTE: the OUTCOME named `busy` maps to
// the warn VARIANT, not the busy variant — the variant names describe agent
// state colors, not call outcomes.
const variants: Record<string, BadgeVariants['variant']> = {
    answered: 'ok',
    missed: 'busy',
    no_answer: 'busy',
    voicemail: 'warn',
    busy: 'warn',
    wrong_number: 'idle',
};
</script>

<template>
    <Badge :variant="variants[outcome] ?? 'idle'">
        {{ label }}
    </Badge>
</template>
