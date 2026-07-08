<script setup lang="ts">
type ExpiringRow = {
    id: number;
    customer: string | null;
    product: string | null;
    warranty_expires_at: string | null;
    days_left: number;
};

defineProps<{
    items: ExpiringRow[];
}>();

const daysText = (days: number) =>
    days <= 0 ? 'Hari ini' : `${days} hari lagi`;
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
    >
        <div
            class="flex items-baseline justify-between gap-4 border-b border-border p-5"
        >
            <h2 class="text-sm font-semibold text-foreground">
                Garansi Segera Berakhir
            </h2>
            <p class="text-xs text-muted-foreground">≤ 30 hari</p>
        </div>

        <div
            v-if="items.length === 0"
            class="px-6 py-10 text-center text-sm text-muted-foreground"
        >
            Tidak ada garansi yang akan berakhir.
        </div>

        <ul v-else class="divide-y divide-border">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-3 px-5 py-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-foreground">
                        {{ item.customer ?? '—' }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ item.product ?? '—' }}
                    </p>
                </div>
                <span
                    class="shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 tabular-nums"
                >
                    {{ daysText(item.days_left) }}
                </span>
            </li>
        </ul>
    </div>
</template>
