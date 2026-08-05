<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    CircleAlert,
    Plus,
    Receipt,
    Search,
    ShieldCheck,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import IndexStatCard from '@/components/IndexStatCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import WarrantyBadge from '@/components/WarrantyBadge.vue';
import { setBreadcrumbs } from '@/composables/useBreadcrumbs';
import { formatIdr } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';

type TransactionRow = {
    id: number;
    customer: string | null;
    product: string | null;
    purchased_at: string | null;
    warranty_months: number;
    warranty_expires_at: string | null;
    is_under_warranty: boolean;
    amount: string | null;
    // Per-row verdict (H7) — the transaction policy is scope-checked per record.
    can_edit: boolean;
    can_delete: boolean;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    transactions: Paginated<TransactionRow>;
    stats: { total: number; underWarranty: number; expired: number };
    filters: { search: string };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transaksi', href: TransactionController.index() },
];

const search = ref(props.filters.search ?? '');

function applyFilters() {
    router.get(
        TransactionController.index.url(),
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, applyFilters, { debounce: 300 });

const initial = (name: string | null) =>
    (name ?? '?').trim().charAt(0).toUpperCase();

setBreadcrumbs(() => breadcrumbs);
</script>

<template>
    <Head title="Data Transaksi" />
    <div class="flex flex-col gap-6">
        <!-- Page header -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Data Transaksi
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola transaksi penjualan dan status garansinya.
                </p>
            </div>

            <Button v-if="can.create" as-child>
                <Link :href="TransactionController.create()">
                    <Plus />
                    Tambah Transaksi
                </Link>
            </Button>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <IndexStatCard
                label="Total Transaksi"
                :value="stats.total"
                :icon="Receipt"
                detail="Transaksi tercatat"
            />
            <IndexStatCard
                label="Garansi Aktif"
                :value="stats.underWarranty"
                :icon="ShieldCheck"
                detail="Masih dalam masa garansi"
            />
            <IndexStatCard
                label="Garansi Berakhir"
                :value="stats.expired"
                :icon="CircleAlert"
                detail="Masa garansi telah lewat"
            />
        </div>

        <!-- Flash -->
        <div
            v-if="flashSuccess"
            class="flex items-center gap-2 rounded-lg border border-state-ok/30 bg-state-ok-soft px-4 py-3 text-sm text-state-ok-strong"
        >
            <Check class="size-4 shrink-0" />
            <span>{{ flashSuccess }}</span>
        </div>

        <!-- Content card -->
        <div
            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
        >
            <!-- Toolbar -->
            <div class="border-b border-border p-4">
                <div class="relative w-full max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Cari customer atau produk…"
                        class="pl-9"
                    />
                </div>
            </div>

            <!-- Table -->
            <Table>
                <TableHeader>
                    <TableHead>Customer</TableHead>
                    <TableHead>Produk</TableHead>
                    <TableHead>Tanggal Beli</TableHead>
                    <TableHead align="right">Nilai</TableHead>
                    <TableHead>Garansi</TableHead>
                    <TableHead align="right">Aksi</TableHead>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="t in transactions.data" :key="t.id">
                        <TableCell>
                            <div
                                class="flex items-center gap-3 whitespace-nowrap"
                            >
                                <div
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary"
                                >
                                    {{ initial(t.customer) }}
                                </div>
                                <span class="font-medium text-foreground">{{
                                    t.customer ?? '—'
                                }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ t.product ?? '—' }}
                        </TableCell>
                        <TableCell kind="mono" class="text-muted-foreground">
                            {{ t.purchased_at ?? '—' }}
                        </TableCell>
                        <TableCell
                            kind="money"
                            class="font-medium text-foreground"
                        >
                            {{ t.amount === null ? '—' : formatIdr(t.amount) }}
                        </TableCell>
                        <TableCell>
                            <WarrantyBadge
                                :is-under-warranty="t.is_under_warranty"
                                :expires-at="t.warranty_expires_at"
                                :warranty-months="t.warranty_months"
                            />
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="t.can_edit"
                                    as-child
                                    variant="ghost"
                                    size="sm"
                                >
                                    <Link
                                        :href="TransactionController.edit(t.id)"
                                        >Edit</Link
                                    >
                                </Button>

                                <Dialog v-if="t.can_delete">
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            Hapus
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <Form
                                            v-bind="
                                                TransactionController.destroy.form(
                                                    t.id,
                                                )
                                            "
                                            :options="{
                                                preserveScroll: true,
                                            }"
                                            class="space-y-6"
                                            v-slot="{ processing }"
                                        >
                                            <DialogHeader class="space-y-3">
                                                <DialogTitle
                                                    >Hapus
                                                    transaksi?</DialogTitle
                                                >
                                                <DialogDescription>
                                                    Transaksi
                                                    <strong>{{
                                                        t.customer
                                                    }}</strong>
                                                    —
                                                    <strong>{{
                                                        t.product
                                                    }}</strong>
                                                    akan dihapus permanen.
                                                    Tindakan ini tidak dapat
                                                    dibatalkan.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <DialogFooter class="gap-2">
                                                <DialogClose as-child>
                                                    <Button
                                                        variant="secondary"
                                                        type="button"
                                                    >
                                                        Batal
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    :disabled="processing"
                                                >
                                                    Hapus
                                                </Button>
                                            </DialogFooter>
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableEmpty
                        v-if="transactions.data.length === 0"
                        :colspan="6"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <Receipt class="size-5" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Belum ada data transaksi
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Catat transaksi pertama untuk mulai.
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>

            <!-- Footer / pagination -->
            <div
                v-if="transactions.total > 0"
                class="flex flex-wrap items-center justify-between gap-4 border-t border-border p-4"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ transactions.from ?? 0 }}–{{
                        transactions.to ?? 0
                    }}
                    dari {{ transactions.total }}
                </p>
                <div
                    v-if="transactions.links.length > 3"
                    class="flex flex-wrap items-center gap-1"
                >
                    <template v-for="(link, i) in transactions.links" :key="i">
                        <span
                            v-if="!link.url"
                            class="px-3 py-1.5 text-sm text-muted-foreground"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                            class="rounded-md px-3 py-1.5 text-sm transition-colors"
                            :class="
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-foreground hover:bg-accent'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
