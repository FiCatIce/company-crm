<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';

defineProps<{ status: string; label: string }>();

// Customer lifecycle on the locked state vocabulary (kept separate from the
// themeable accent): lead=warn, active=ok, inactive=idle, churned=busy.
const variants: Record<string, BadgeVariants['variant']> = {
    lead: 'warn',
    active: 'ok',
    inactive: 'idle',
    churned: 'busy',
};
</script>

<template>
    <Badge :variant="variants[status] ?? 'idle'">
        {{ label }}
    </Badge>
</template>
