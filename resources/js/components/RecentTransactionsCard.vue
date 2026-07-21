<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import WarrantyBadge from '@/components/WarrantyBadge.vue';
import WidgetEmptyState from '@/components/WidgetEmptyState.vue';

type RecentRow = {
    id: number;
    customer: string | null;
    product: string | null;
    purchased_at: string | null;
    warranty_expires_at: string | null;
    is_under_warranty: boolean;
    warranty_months: number;
};

defineProps<{
    rows: RecentRow[];
}>();

const initial = (name: string | null) =>
    (name ?? '?').trim().charAt(0).toUpperCase();
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
    >
        <div
            class="flex items-baseline justify-between gap-4 border-b border-border p-5"
        >
            <h2 class="text-sm font-semibold text-foreground">
                Transaksi Terbaru
            </h2>
            <Link
                :href="TransactionController.index()"
                class="text-xs font-medium text-primary hover:underline"
            >
                Lihat semua
            </Link>
        </div>

        <WidgetEmptyState
            v-if="rows.length === 0"
            :icon="Receipt"
            message="Belum ada transaksi."
        />

        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th
                            class="px-5 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Customer
                        </th>
                        <th
                            class="px-5 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Produk
                        </th>
                        <th
                            class="px-5 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Tanggal
                        </th>
                        <th
                            class="px-5 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Garansi
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="transition-colors hover:bg-accent/50"
                    >
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                >
                                    {{ initial(row.customer) }}
                                </div>
                                <span class="font-medium text-foreground">{{
                                    row.customer ?? '—'
                                }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-muted-foreground">
                            {{ row.product ?? '—' }}
                        </td>
                        <td
                            class="px-5 py-3 text-muted-foreground tabular-nums"
                        >
                            {{ row.purchased_at ?? '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <WarrantyBadge
                                :is-under-warranty="row.is_under_warranty"
                                :expires-at="row.warranty_expires_at"
                                :warranty-months="row.warranty_months"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
