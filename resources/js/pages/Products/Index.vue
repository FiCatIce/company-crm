<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Clock, Package, Plus, Search, ShieldCheck } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type ProductRow = {
    id: number;
    name: string;
    warranty_months: number;
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
    products: Paginated<ProductRow>;
    stats: { total: number; withWarranty: number; avgWarrantyMonths: number };
    filters: { search: string };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produk', href: ProductController.index() },
];

const search = ref(props.filters.search ?? '');

function applyFilters() {
    router.get(
        ProductController.index.url(),
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, applyFilters, { debounce: 300 });

const initial = (name: string) => name.trim().charAt(0).toUpperCase();

const warrantyLabel = (months: number) =>
    months === 0 ? 'Tanpa garansi' : `${months} bulan`;
</script>

<template>
    <Head title="Data Produk" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <!-- Page header -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="space-y-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Data Produk
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Kelola katalog produk dan masa garansinya.
                    </p>
                </div>

                <Button v-if="can.create" as-child>
                    <Link :href="ProductController.create()">
                        <Plus />
                        Tambah Produk
                    </Link>
                </Button>
            </div>

            <!-- Summary cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <IndexStatCard
                    label="Total Produk"
                    :value="stats.total"
                    :icon="Package"
                    detail="Produk terdaftar"
                />
                <IndexStatCard
                    label="Produk Bergaransi"
                    :value="stats.withWarranty"
                    :icon="ShieldCheck"
                    detail="Memiliki masa garansi"
                />
                <IndexStatCard
                    label="Rata-rata Garansi"
                    :value="stats.avgWarrantyMonths"
                    :icon="Clock"
                    detail="Bulan per produk"
                />
            </div>

            <!-- Flash -->
            <div
                v-if="flashSuccess"
                class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300"
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
                            placeholder="Cari nama produk…"
                            class="pl-9"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-border">
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Nama
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Garansi
                                </th>
                                <th
                                    class="px-6 py-3.5 text-right text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="p in products.data"
                                :key="p.id"
                                class="transition-colors hover:bg-accent/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                        >
                                            {{ initial(p.name) }}
                                        </div>
                                        <span
                                            class="font-medium text-foreground"
                                            >{{ p.name }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        v-if="p.warranty_months > 0"
                                        class="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground"
                                    >
                                        {{ warrantyLabel(p.warranty_months) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        {{ warrantyLabel(p.warranty_months) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
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
                                                    ProductController.edit(p.id)
                                                "
                                                >Edit</Link
                                            >
                                        </Button>

                                        <Dialog v-if="can.delete">
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
                                                        ProductController.destroy.form(
                                                            p.id,
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
                                                            produk?</DialogTitle
                                                        >
                                                        <DialogDescription>
                                                            Data
                                                            <strong>{{
                                                                p.name
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

                            <tr v-if="products.data.length === 0">
                                <td colspan="3" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex max-w-sm flex-col items-center gap-2"
                                    >
                                        <div
                                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                        >
                                            <Package class="size-5" />
                                        </div>
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            Belum ada data produk
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            Tambahkan produk pertama untuk
                                            mulai.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer / pagination -->
                <div
                    v-if="products.total > 0"
                    class="flex flex-wrap items-center justify-between gap-4 border-t border-border p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Menampilkan {{ products.from ?? 0 }}–{{
                            products.to ?? 0
                        }}
                        dari {{ products.total }}
                    </p>
                    <div
                        v-if="products.links.length > 3"
                        class="flex flex-wrap items-center gap-1"
                    >
                        <template v-for="(link, i) in products.links" :key="i">
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
    </AppLayout>
</template>
