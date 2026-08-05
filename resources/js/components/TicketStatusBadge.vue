<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';

defineProps<{ status: string; label: string }>();

// Semantic ticket states (DESIGN_MAINTENANCE.md M3) on the locked state
// vocabulary: received=idle, in_progress=warn, done=ok, cancelled=busy.
const variants: Record<string, BadgeVariants['variant']> = {
    received: 'idle',
    in_progress: 'warn',
    done: 'ok',
    cancelled: 'busy',
};
</script>

<template>
    <Badge :variant="variants[status] ?? 'idle'">
        {{ label }}
    </Badge>
</template>
