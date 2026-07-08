<script setup lang="ts">
import { MessagesSquare } from '@lucide/vue';
import { computed } from 'vue';
import InteractionItem from '@/components/InteractionItem.vue';
import WidgetEmptyState from '@/components/WidgetEmptyState.vue';
import { dayGroupLabel } from '@/lib/format';
import type { InteractionRow } from '@/types/crm';

const props = defineProps<{
    items: InteractionRow[];
    hasMore: boolean;
    loading: boolean;
}>();

defineEmits<{ (e: 'load-more'): void }>();

// Items arrive newest-first; group consecutive runs by day label.
const groups = computed(() => {
    const out: { label: string; items: InteractionRow[] }[] = [];

    for (const item of props.items) {
        const label = dayGroupLabel(item.occurred_at);
        const last = out[out.length - 1];

        if (last && last.label === label) {
            last.items.push(item);
        } else {
            out.push({ label, items: [item] });
        }
    }

    return out;
});
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
    >
        <div class="border-b border-border p-5">
            <h2 class="text-sm font-semibold text-foreground">
                Riwayat Interaksi
            </h2>
        </div>

        <WidgetEmptyState
            v-if="items.length === 0"
            :icon="MessagesSquare"
            message="Belum ada interaksi dengan customer ini."
        />

        <div v-else class="divide-y divide-border">
            <div v-for="group in groups" :key="group.label" class="p-5">
                <p
                    class="mb-4 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    {{ group.label }}
                </p>
                <div class="space-y-5">
                    <InteractionItem
                        v-for="item in group.items"
                        :key="item.id"
                        :item="item"
                    />
                </div>
            </div>
        </div>

        <div v-if="hasMore" class="border-t border-border p-4 text-center">
            <button
                type="button"
                class="text-sm font-medium text-primary hover:underline disabled:opacity-50"
                :disabled="loading"
                @click="$emit('load-more')"
            >
                {{ loading ? 'Memuat…' : 'Muat lagi' }}
            </button>
        </div>
    </div>
</template>
