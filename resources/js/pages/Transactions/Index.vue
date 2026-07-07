<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
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
import WarrantyBadge from '@/components/WarrantyBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type TransactionRow = {
    id: number;
    customer: string | null;
    product: string | null;
    reseller: string | null;
    purchased_at: string | null;
    warranty_months: number;
    warranty_expires_at: string | null;
    is_under_warranty: boolean;
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
</script>

<template>
    <Head title="Data Transaksi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Data Transaksi
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ transactions.total }} transaksi tercatat
                    </p>
                </div>

                <Button v-if="can.create" as-child>
                    <Link :href="TransactionController.create()"
                        >Tambah Transaksi</Link
                    >
                </Button>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800/40 dark:bg-green-900/20 dark:text-green-300"
            >
                {{ flashSuccess }}
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Cari customer atau produk…"
                    class="max-w-xs"
                />
            </div>

            <div
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border bg-muted/50 text-muted-foreground"
                        >
                            <tr>
                                <th class="px-5 py-3 font-medium">Customer</th>
                                <th class="px-5 py-3 font-medium">Produk</th>
                                <th class="px-5 py-3 font-medium">Reseller</th>
                                <th class="px-5 py-3 font-medium">
                                    Tanggal Beli
                                </th>
                                <th class="px-5 py-3 font-medium">Garansi</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="t in transactions.data"
                                :key="t.id"
                                class="transition-colors hover:bg-muted/40"
                            >
                                <td class="px-5 py-3">
                                    <span class="font-medium text-foreground">{{
                                        t.customer ?? '-'
                                    }}</span>
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ t.product ?? '-' }}
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ t.reseller ?? '-' }}
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ t.purchased_at ?? '-' }}
                                </td>
                                <td class="px-5 py-3">
                                    <WarrantyBadge
                                        :is-under-warranty="t.is_under_warranty"
                                        :expires-at="t.warranty_expires_at"
                                        :warranty-months="t.warranty_months"
                                    />
                                </td>
                                <td class="px-5 py-3">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            v-if="can.update"
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link
                                                :href="
                                                    TransactionController.edit(
                                                        t.id,
                                                    )
                                                "
                                                >Edit</Link
                                            >
                                        </Button>

                                        <Dialog v-if="can.delete">
                                            <DialogTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
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
                                                    <DialogHeader
                                                        class="space-y-3"
                                                    >
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
                                                            akan dihapus
                                                            permanen. Tindakan
                                                            ini tidak dapat
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
                                                            :disabled="
                                                                processing
                                                            "
                                                        >
                                                            Hapus
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="transactions.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-5 py-12 text-center text-muted-foreground"
                                >
                                    Belum ada data transaksi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="transactions.links.length > 3"
                class="flex items-center justify-between gap-4"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ transactions.from ?? 0 }}–{{
                        transactions.to ?? 0
                    }}
                    dari {{ transactions.total }}
                </p>
                <div class="flex flex-wrap items-center gap-1">
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
                                    : 'text-foreground hover:bg-muted'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
