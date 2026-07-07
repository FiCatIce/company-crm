<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
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

const warrantyLabel = (months: number) =>
    months === 0 ? 'Tanpa garansi' : `${months} bulan`;
</script>

<template>
    <Head title="Data Produk" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Data Produk
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ products.total }} produk terdaftar
                    </p>
                </div>

                <Button v-if="can.create" as-child>
                    <Link :href="ProductController.create()"
                        >Tambah Produk</Link
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
                    placeholder="Cari nama produk…"
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
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Garansi</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="p in products.data"
                                :key="p.id"
                                class="transition-colors hover:bg-muted/40"
                            >
                                <td class="px-5 py-3">
                                    <span class="font-medium text-foreground">{{
                                        p.name
                                    }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        v-if="p.warranty_months > 0"
                                        class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground"
                                    >
                                        {{ warrantyLabel(p.warranty_months) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        {{ warrantyLabel(p.warranty_months) }}
                                    </span>
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
                                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
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
                                <td
                                    colspan="3"
                                    class="px-5 py-12 text-center text-muted-foreground"
                                >
                                    Belum ada data produk.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="products.links.length > 3"
                class="flex items-center justify-between gap-4"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ products.from ?? 0 }}–{{
                        products.to ?? 0
                    }}
                    dari {{ products.total }}
                </p>
                <div class="flex flex-wrap items-center gap-1">
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
