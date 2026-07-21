<script setup lang="ts">
import { Users } from '@lucide/vue';
import { computed } from 'vue';
import WidgetEmptyState from '@/components/WidgetEmptyState.vue';

type TopSales = {
    id: number;
    name: string;
    customers_count: number;
};

const props = defineProps<{
    items: TopSales[];
    // Whose books are ranked — 'team' for a manager, 'org' for a global viewer.
    // The backend has already bounded the numbers; this only labels the card.
    scope: 'org' | 'team';
}>();

const subtitle = computed(() =>
    props.scope === 'team'
        ? 'tim Anda · per customer'
        : 'organisasi · per customer',
);

const maxCount = computed(() =>
    Math.max(1, ...props.items.map((item) => item.customers_count)),
);

// Keep a minimum sliver so a rep with few customers still reads as a bar.
const barWidth = (count: number) =>
    `${Math.max(6, (count / maxCount.value) * 100)}%`;
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
    >
        <div
            class="flex items-baseline justify-between gap-4 border-b border-border p-5"
        >
            <h2 class="text-sm font-semibold text-foreground">Sales Teratas</h2>
            <p class="text-xs text-muted-foreground">{{ subtitle }}</p>
        </div>

        <WidgetEmptyState
            v-if="items.length === 0"
            :icon="Users"
            message="Belum ada sales dengan customer."
        />

        <ul v-else class="divide-y divide-border">
            <li
                v-for="(item, i) in items"
                :key="item.id"
                class="flex items-center gap-3 px-5 py-3"
            >
                <span
                    class="grid size-6 shrink-0 place-items-center rounded-md bg-accent text-xs font-bold text-accent-foreground tabular-nums"
                >
                    {{ i + 1 }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-foreground">
                        {{ item.name }}
                    </p>
                    <div
                        class="mt-1.5 h-1.5 w-full max-w-[8rem] overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: barWidth(item.customers_count) }"
                        />
                    </div>
                </div>
                <span
                    class="shrink-0 text-sm font-semibold text-foreground tabular-nums"
                >
                    {{ item.customers_count }}
                </span>
            </li>
        </ul>
    </div>
</template>
