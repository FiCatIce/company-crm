<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Plus, Search, ShieldCheck, Users } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerStatusBadge from '@/components/CustomerStatusBadge.vue';
import IndexStatCard from '@/components/IndexStatCard.vue';
import OwnerBadge from '@/components/OwnerBadge.vue';
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
import { setBreadcrumbs } from '@/composables/useBreadcrumbs';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/crm';

type CustomerRow = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    status: string;
    status_label: string;
    owner: { id: number; name: string } | null;
    // Per-row verdict (H7): ownership/hierarchy policies differ per record, so the
    // page-level `can` is class-level capability only — never a row's answer.
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
    customers: Paginated<CustomerRow>;
    statuses: SelectOption[];
    users: SelectOption[];
    stats: { total: number; underWarranty: number };
    filters: {
        search: string;
        status: string | null;
        owner: string | null;
    };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customer', href: CustomerController.index() },
];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const owner = ref(props.filters.owner ?? '');

function applyFilters() {
    router.get(
        CustomerController.index.url(),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            owner: owner.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, applyFilters, { debounce: 300 });
watch(status, applyFilters);
watch(owner, applyFilters);

const initial = (name: string) => name.trim().charAt(0).toUpperCase();

const selectClasses =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

setBreadcrumbs(() => breadcrumbs);
</script>

<template>
    <Head title="Data Customer" />
    <div class="flex flex-col gap-6">
        <!-- Page header -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Data Customer
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola customer beserta status garansinya.
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
        <div class="grid gap-4 sm:grid-cols-2">
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
                <select v-model="owner" :class="selectClasses">
                    <option value="">Semua owner</option>
                    <option value="me">Punya Saya</option>
                    <option value="unassigned">Belum ada owner</option>
                    <optgroup label="Agen">
                        <option
                            v-for="u in users"
                            :key="u.value"
                            :value="u.value"
                        >
                            {{ u.label }}
                        </option>
                    </optgroup>
                </select>
            </div>

            <!-- Table -->
            <Table>
                <TableHeader>
                    <TableHead>Nama</TableHead>
                    <TableHead>Telepon</TableHead>
                    <TableHead priority="secondary">Email</TableHead>
                    <TableHead priority="secondary">Alamat</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead align="right">Aksi</TableHead>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="c in customers.data" :key="c.id">
                        <TableCell>
                            <div
                                class="flex items-center gap-3 whitespace-nowrap"
                            >
                                <div
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary"
                                >
                                    {{ initial(c.name) }}
                                </div>
                                <Link
                                    :href="CustomerController.show(c.id)"
                                    class="font-medium text-foreground hover:text-primary hover:underline"
                                    >{{ c.name }}</Link
                                >
                            </div>
                        </TableCell>
                        <TableCell kind="mono" class="text-muted-foreground">
                            {{ c.phone ?? '—' }}
                        </TableCell>
                        <TableCell
                            priority="secondary"
                            class="max-w-[12rem] truncate text-muted-foreground"
                            :title="c.email ?? ''"
                        >
                            {{ c.email ?? '—' }}
                        </TableCell>
                        <TableCell
                            priority="secondary"
                            class="max-w-[12rem] truncate text-muted-foreground"
                            :title="c.address ?? ''"
                        >
                            {{ c.address ?? '—' }}
                        </TableCell>
                        <TableCell>
                            <CustomerStatusBadge
                                :status="c.status"
                                :label="c.status_label"
                            />
                        </TableCell>
                        <TableCell>
                            <OwnerBadge :owner="c.owner" />
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="c.can_edit"
                                    as-child
                                    variant="ghost"
                                    size="sm"
                                >
                                    <Link :href="CustomerController.edit(c.id)"
                                        >Edit</Link
                                    >
                                </Button>

                                <Dialog v-if="c.can_delete">
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
                                            <DialogHeader class="space-y-3">
                                                <DialogTitle
                                                    >Hapus
                                                    customer?</DialogTitle
                                                >
                                                <DialogDescription>
                                                    Data
                                                    <strong>{{
                                                        c.name
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

                    <TableEmpty v-if="customers.data.length === 0" :colspan="7">
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <Users class="size-5" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Belum ada data customer
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Tambahkan customer pertama untuk mulai.
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>

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
</template>
