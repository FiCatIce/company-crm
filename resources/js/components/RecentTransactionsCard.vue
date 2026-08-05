<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

        <Table v-else>
            <TableHeader>
                <TableHead>Customer</TableHead>
                <TableHead>Produk</TableHead>
                <TableHead>Tanggal</TableHead>
                <TableHead>Garansi</TableHead>
            </TableHeader>
            <TableBody>
                <TableRow v-for="row in rows" :key="row.id">
                    <TableCell>
                        <div class="flex items-center gap-3 whitespace-nowrap">
                            <div
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary"
                            >
                                {{ initial(row.customer) }}
                            </div>
                            <span class="font-medium text-foreground">{{
                                row.customer ?? '—'
                            }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="text-muted-foreground">
                        {{ row.product ?? '—' }}
                    </TableCell>
                    <TableCell kind="mono" class="text-muted-foreground">
                        {{ row.purchased_at ?? '—' }}
                    </TableCell>
                    <TableCell>
                        <WarrantyBadge
                            :is-under-warranty="row.is_under_warranty"
                            :expires-at="row.warranty_expires_at"
                            :warranty-months="row.warranty_months"
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
