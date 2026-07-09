<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Network, Plus, Search, ShieldCheck, Users } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerStatusBadge from '@/components/CustomerStatusBadge.vue';
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
import type { SelectOption } from '@/types/crm';

type CustomerRow = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    reseller: string | null;
    status: string;
    status_label: string;
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
    customers: Paginated<CustomerRow>;
    resellers: { id: number; name: string }[];
    statuses: SelectOption[];
    stats: { total: number; underWarranty: number; resellers: number };
    filters: { search: string; reseller: number | null; status: string | null };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customer', href: CustomerController.index() },
];

const search = ref(props.filters.search ?? '');
const reseller = ref(
    props.filters.reseller ? String(props.filters.reseller) : '',
);
const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(
        CustomerController.index.url(),
        {
            search: search.value || undefined,
            reseller: reseller.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, applyFilters, { debounce: 300 });
watch(reseller, applyFilters);
watch(status, applyFilters);

const initial = (name: string) => name.trim().charAt(0).toUpperCase();

const selectClasses =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head title="Data Customer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <!-- Page header -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="space-y-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Data Customer
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Kelola customer beserta reseller dan status garansinya.
                    </p>
                </div>

                <Button v-if="can.create" as-child>
                    <Link :href="CustomerController.create()">
                        <Plus />
                        Tambah Customer
                    </Link>
                </Button>
            </div>

            <!-- Summary cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <IndexStatCard
                    label="Total Customer"
                    :value="stats.total"
                    :icon="Users"
                    detail="Customer terdaftar"
                />
                <IndexStatCard
                    label="Garansi Aktif"
                    :value="stats.underWarranty"
                    :icon="ShieldCheck"
                    detail="Customer dengan garansi berjalan"
                />
                <IndexStatCard
                    label="Total Reseller"
                    :value="stats.resellers"
                    :icon="Network"
                    detail="Reseller terdaftar"
                />
            </div>

            <!-- Flash -->
            <div
                v-if="flashSuccess"
                class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
            >
                <Check class="size-4 shrink-0" />
                <span>{{ flashSuccess }}</span>
            </div>

            <!-- Content card -->
            <div
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <!-- Toolbar -->
                <div
                    class="flex flex-wrap items-center gap-3 border-b border-border p-4"
                >
                    <div class="relative w-full max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Cari nama, email, atau telepon…"
                            class="pl-9"
                        />
                    </div>
                    <select v-model="reseller" :class="selectClasses">
                        <option value="">Semua reseller</option>
                        <option
                            v-for="r in resellers"
                            :key="r.id"
                            :value="String(r.id)"
                        >
                            {{ r.name }}
                        </option>
                    </select>
                    <select v-model="status" :class="selectClasses">
                        <option value="">Semua status</option>
                        <option
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </option>
                    </select>
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
                                    Telepon
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Email
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Alamat
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Reseller
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Status
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
                                v-for="c in customers.data"
                                :key="c.id"
                                class="transition-colors hover:bg-accent/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                        >
                                            {{ initial(c.name) }}
                                        </div>
                                        <Link
                                            :href="
                                                CustomerController.show(c.id)
                                            "
                                            class="font-medium text-foreground hover:text-primary hover:underline"
                                            >{{ c.name }}</Link
                                        >
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ c.phone ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ c.email ?? '—' }}
                                </td>
                                <td
                                    class="max-w-[16rem] truncate px-6 py-4 text-muted-foreground"
                                    :title="c.address ?? ''"
                                >
                                    {{ c.address ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        v-if="c.reseller"
                                        class="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground"
                                    >
                                        {{ c.reseller }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <CustomerStatusBadge
                                        :status="c.status"
                                        :label="c.status_label"
                                    />
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
                                                    CustomerController.edit(
                                                        c.id,
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
                                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    Hapus
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <Form
                                                    v-bind="
                                                        CustomerController.destroy.form(
                                                            c.id,
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
                                                            customer?</DialogTitle
                                                        >
                                                        <DialogDescription>
                                                            Data
                                                            <strong>{{
                                                                c.name
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

                            <tr v-if="customers.data.length === 0">
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex max-w-sm flex-col items-center gap-2"
                                    >
                                        <div
                                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                        >
                                            <Users class="size-5" />
                                        </div>
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            Belum ada data customer
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            Tambahkan customer pertama untuk
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
                    v-if="customers.total > 0"
                    class="flex flex-wrap items-center justify-between gap-4 border-t border-border p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Menampilkan {{ customers.from ?? 0 }}–{{
                            customers.to ?? 0
                        }}
                        dari {{ customers.total }}
                    </p>
                    <div
                        v-if="customers.links.length > 3"
                        class="flex flex-wrap items-center gap-1"
                    >
                        <template v-for="(link, i) in customers.links" :key="i">
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
