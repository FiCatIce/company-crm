<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, CircleAlert, Plus, Search, Wrench } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ServiceTicketController from '@/actions/App/Http/Controllers/ServiceTicketController';
import IndexStatCard from '@/components/IndexStatCard.vue';
import TicketStatusBadge from '@/components/TicketStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { setBreadcrumbs } from '@/composables/useBreadcrumbs';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/crm';

type TicketRow = {
    id: number;
    customer: { id: number; name: string } | null;
    item: string | null;
    serial_no: string | null;
    status: { value: string; label: string };
    coverage: { value: string; label: string };
    priority: { value: string; label: string };
    assignee: { id: number; name: string } | null;
    received_at: string;
    can_claim: boolean;
    customer_viewable: boolean;
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
    tickets: Paginated<TicketRow>;
    stats: { active: number; doneThisMonth: number };
    filters: {
        status?: string | null;
        priority?: string | null;
        assigned?: string | null;
        created?: string | null;
        search?: string | null;
    };
    statusOptions: SelectOption[];
    priorityOptions: SelectOption[];
    can: { create: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Servis', href: ServiceTicketController.index() },
];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const priority = ref(props.filters.priority ?? '');
// One select spans two query params: assigned=me|pool and created=me ("Intake
// Saya", the CS dashboard card's bound) — 'intake' is the select's encoding of
// the latter.
const ownership = ref(
    props.filters.created === 'me' ? 'intake' : (props.filters.assigned ?? ''),
);

function applyFilters() {
    router.get(
        ServiceTicketController.index.url(),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            priority: priority.value || undefined,
            assigned:
                ownership.value === 'intake'
                    ? undefined
                    : ownership.value || undefined,
            created: ownership.value === 'intake' ? 'me' : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, applyFilters, { debounce: 300 });
watch(status, applyFilters);
watch(priority, applyFilters);
watch(ownership, applyFilters);

const claiming = ref<number | null>(null);

// Claim straight from the pool row — the shortest path from "work exists" to
// "it is mine".
function claim(ticket: TicketRow) {
    claiming.value = ticket.id;
    router.post(
        ServiceTicketController.claim.url(ticket.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (claiming.value = null),
        },
    );
}

const dateFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const formatDate = (value: string) => dateFormat.format(new Date(value));

const selectClasses =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

setBreadcrumbs(() => breadcrumbs);
</script>

<template>
    <Head title="Servis" />
    <div class="flex flex-col gap-6">
        <!-- Page header -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Tiket Servis
                </h1>
                <p class="text-sm text-muted-foreground">
                    Proses maintenance per customer — dari barang masuk sampai
                    selesai.
                </p>
            </div>

            <Button v-if="can.create" as-child>
                <Link :href="ServiceTicketController.create()">
                    <Plus />
                    Buat Tiket
                </Link>
            </Button>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2">
            <IndexStatCard
                label="Tiket Aktif"
                :value="stats.active"
                :icon="Wrench"
                detail="Diterima atau sedang dikerjakan"
            />
            <IndexStatCard
                label="Selesai Bulan Ini"
                :value="stats.doneThisMonth"
                :icon="Check"
                detail="Tiket rampung bulan berjalan"
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
        <div
            v-if="flashError"
            class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            <CircleAlert class="size-4 shrink-0" />
            <span>{{ flashError }}</span>
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
                        placeholder="Cari nama customer…"
                        class="pl-9"
                    />
                </div>
                <select v-model="status" :class="selectClasses">
                    <option value="">Semua status</option>
                    <!-- Virtual bucket (M4): diterima + dikerjakan — the
                             dashboard's "aktif" cards land here. -->
                    <option value="active">Aktif</option>
                    <option
                        v-for="s in statusOptions"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </option>
                </select>
                <select v-model="priority" :class="selectClasses">
                    <option value="">Semua prioritas</option>
                    <option
                        v-for="p in priorityOptions"
                        :key="p.value"
                        :value="p.value"
                    >
                        {{ p.label }}
                    </option>
                </select>
                <select v-model="ownership" :class="selectClasses">
                    <option value="">Semua tiket</option>
                    <option value="me">Kerjaan Saya</option>
                    <option value="pool">Pool (belum dipegang)</option>
                    <option value="intake">Intake Saya</option>
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
                                Tiket
                            </th>
                            <th
                                class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Customer
                            </th>
                            <th
                                class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Barang
                            </th>
                            <th
                                class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Prioritas
                            </th>
                            <th
                                class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Teknisi
                            </th>
                            <th
                                class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Masuk
                            </th>
                            <th class="px-6 py-3.5">
                                <span class="sr-only">Aksi</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                        >
                            <td class="px-6 py-4">
                                <Link
                                    :href="
                                        ServiceTicketController.show(ticket.id)
                                    "
                                    class="font-medium text-primary hover:underline"
                                >
                                    #{{ ticket.id }}
                                </Link>
                            </td>
                            <td class="px-6 py-4">
                                <!-- Link only when the viewer can OPEN the 360
                                    behind it (D3: an assigned ticket may sit on an
                                    out-of-scope customer). -->
                                <Link
                                    v-if="
                                        ticket.customer &&
                                        ticket.customer_viewable
                                    "
                                    :href="`/customers/${ticket.customer.id}`"
                                    class="hover:underline"
                                >
                                    {{ ticket.customer.name }}
                                </Link>
                                <span v-else>{{
                                    ticket.customer?.name ?? '—'
                                }}</span>
                            </td>
                            <td
                                class="max-w-48 truncate px-6 py-4 text-muted-foreground"
                            >
                                {{ ticket.item ?? '—' }}
                            </td>
                            <td class="px-6 py-4">
                                <TicketStatusBadge
                                    :status="ticket.status.value"
                                    :label="ticket.status.label"
                                />
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ ticket.priority.label }}
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="ticket.assignee">{{
                                    ticket.assignee.name
                                }}</span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"
                                    >Pool</span
                                >
                            </td>
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ formatDate(ticket.received_at) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Button
                                    v-if="ticket.can_claim"
                                    size="sm"
                                    variant="outline"
                                    :disabled="claiming === ticket.id"
                                    @click="claim(ticket)"
                                >
                                    Klaim
                                </Button>
                            </td>
                        </tr>

                        <tr v-if="tickets.data.length === 0">
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div
                                    class="mx-auto flex max-w-sm flex-col items-center gap-2"
                                >
                                    <div
                                        class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                    >
                                        <Wrench class="size-6" />
                                    </div>
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        Belum ada tiket servis
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        Tiket yang masuk akan tampil di sini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="tickets.data.length > 0"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-6 py-4"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ tickets.from }}–{{ tickets.to }} dari
                    {{ tickets.total }}
                </p>
                <nav class="flex items-center gap-1">
                    <template v-for="(link, i) in tickets.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded-md px-3 py-1.5 text-sm transition-colors"
                            :class="
                                link.active
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
