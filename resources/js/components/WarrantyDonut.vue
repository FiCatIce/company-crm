<script setup lang="ts">
import { computed } from 'vue';

// Dependency-free warranty split as a CSS conic-gradient ring (no charting lib,
// matching the house style). The hole is punched with an inner bg-card circle
// rather than a mask so it blends with the card surface everywhere.
const props = defineProps<{
    active: number;
    expired: number;
    none: number;
}>();

// Semantic status colours — same green/red as WarrantyBadge, slate for "no warranty".
const COLORS = {
    active: '#22c55e',
    expired: '#ef4444',
    none: '#cbd5e1',
} as const;

const total = computed(() => props.active + props.expired + props.none);

const pct = (n: number) => (total.value === 0 ? 0 : (n / total.value) * 100);

const ringStyle = computed(() => {
    const a = pct(props.active);
    const e = a + pct(props.expired);

    return {
        background: `conic-gradient(${COLORS.active} 0 ${a}%, ${COLORS.expired} ${a}% ${e}%, ${COLORS.none} ${e}% 100%)`,
    };
});

const activeLabel = computed(() =>
    total.value === 0 ? '0%' : `${Math.round(pct(props.active))}%`,
);

const segments = computed(() => [
    {
        key: 'active',
        label: 'Aktif',
        value: props.active,
        color: COLORS.active,
    },
    {
        key: 'expired',
        label: 'Berakhir',
        value: props.expired,
        color: COLORS.expired,
    },
    {
        key: 'none',
        label: 'Tanpa garansi',
        value: props.none,
        color: COLORS.none,
    },
]);
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="mb-5 flex items-baseline justify-between gap-4">
            <h2 class="text-sm font-semibold text-foreground">
                Status Garansi
            </h2>
            <p class="text-xs text-muted-foreground">semua transaksi</p>
        </div>

        <div
            v-if="total === 0"
            class="py-12 text-center text-sm text-muted-foreground"
        >
            Belum ada transaksi untuk ditampilkan.
        </div>

        <div v-else class="flex flex-col items-center gap-6">
            <div class="relative h-36 w-36">
                <div class="h-36 w-36 rounded-full" :style="ringStyle" />
                <div
                    class="absolute rounded-full bg-card"
                    style="inset: 30px"
                />
                <div
                    class="absolute inset-0 grid place-items-center text-center"
                >
                    <div>
                        <span
                            class="block text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                        >
                            {{ activeLabel }}
                        </span>
                        <span class="text-xs text-muted-foreground">aktif</span>
                    </div>
                </div>
            </div>

            <ul class="w-full space-y-2.5">
                <li
                    v-for="s in segments"
                    :key="s.key"
                    class="flex items-center gap-2.5 text-sm"
                >
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-sm"
                        :style="{ backgroundColor: s.color }"
                        aria-hidden="true"
                    />
                    <span class="text-foreground">{{ s.label }}</span>
                    <span
                        class="ml-auto font-semibold text-foreground tabular-nums"
                    >
                        {{ s.value }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
