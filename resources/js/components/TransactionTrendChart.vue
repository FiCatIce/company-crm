<script setup lang="ts">
import { Receipt } from '@lucide/vue';
import { computed } from 'vue';
import WidgetEmptyState from '@/components/WidgetEmptyState.vue';

type TrendPoint = { month: string; label: string; count: number };

const props = defineProps<{
    data: TrendPoint[];
}>();

const maxCount = computed(() => Math.max(1, ...props.data.map((p) => p.count)));
const totalCount = computed(() =>
    props.data.reduce((sum, p) => sum + p.count, 0),
);

function barHeight(count: number): string {
    if (count === 0) {
        return '0%';
    }

    // Keep a minimum sliver so small non-zero months stay visible.
    return `${Math.max(6, (count / maxCount.value) * 100)}%`;
}

function shortLabel(label: string): string {
    return label.split(' ')[0];
}
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <div>
                <h2 class="text-sm font-medium text-foreground">
                    Tren Transaksi
                </h2>
                <p class="text-xs text-muted-foreground">12 bulan terakhir</p>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ totalCount }} transaksi
            </p>
        </div>

        <WidgetEmptyState
            v-if="totalCount === 0"
            :icon="Receipt"
            message="Belum ada transaksi untuk ditampilkan."
        />

        <div v-else>
            <div class="flex h-48 items-end gap-1.5 sm:gap-2">
                <div
                    v-for="point in data"
                    :key="point.month"
                    class="group relative flex h-full flex-1 flex-col justify-end"
                >
                    <div
                        class="w-full rounded-t bg-primary/70 transition-colors group-hover:bg-primary"
                        :style="{ height: barHeight(point.count) }"
                        :title="`${point.label}: ${point.count} transaksi`"
                    />
                    <span
                        class="pointer-events-none absolute -top-5 left-1/2 -translate-x-1/2 text-xs font-medium text-foreground opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        {{ point.count }}
                    </span>
                </div>
            </div>
            <div class="mt-2 flex gap-1.5 sm:gap-2">
                <span
                    v-for="point in data"
                    :key="point.month"
                    class="flex-1 text-center text-[10px] text-muted-foreground"
                >
                    {{ shortLabel(point.label) }}
                </span>
            </div>
        </div>
    </div>
</template>
