<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    owner: { id: number; name: string } | null;
    emptyText?: string;
}>();

const initial = computed(
    () => props.owner?.name.trim().charAt(0).toUpperCase() ?? '',
);
</script>

<template>
    <span v-if="owner" class="inline-flex items-center gap-2">
        <span
            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-semibold text-primary"
        >
            {{ initial }}
        </span>
        <span class="text-foreground">{{ owner.name }}</span>
    </span>
    <span v-else class="text-muted-foreground">{{ emptyText ?? '—' }}</span>
</template>
