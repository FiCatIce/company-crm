<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Check,
    ClipboardList,
    Inbox,
    MessageSquare,
    Receipt,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
    UserCheck,
    UserCog,
    Users,
    Wallet,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import ServiceTicketController from '@/actions/App/Http/Controllers/ServiceTicketController';
import ExpiringWarrantyCard from '@/components/ExpiringWarrantyCard.vue';
import MyInteractionsCard from '@/components/MyInteractionsCard.vue';
import RecentCallsCard from '@/components/RecentCallsCard.vue';
import RecentTransactionsCard from '@/components/RecentTransactionsCard.vue';
import RevenueBySalesCard from '@/components/RevenueBySalesCard.vue';
import StatCard from '@/components/StatCard.vue';
import TopSalesCard from '@/components/TopSalesCard.vue';
import TransactionTrendChart from '@/components/TransactionTrendChart.vue';
import WarrantyDonut from '@/components/WarrantyDonut.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatIdr } from '@/lib/format';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { MyInteractionRow, RecentCallRow, TeamSummary } from '@/types/crm';

type TrendPoint = { month: string; label: string; count: number };

type RecentRow = {
    id: number;
    customer: string | null;
    product: string | null;
    purchased_at: string | null;
    warranty_expires_at: string | null;
    is_under_warranty: boolean;
    warranty_months: number;
};

type ExpiringRow = {
    id: number;
    customer: string | null;
    product: string | null;
    warranty_expires_at: string | null;
    days_left: number;
};

type TopSales = { id: number; name: string; customers_count: number };

// Service-ticket band (M4) — one variant per capability tier, mirroring the
// backend's ticketStats. Which keys exist depends on `kind`.
type TicketBand =
    | { kind: 'span'; active: number; doneThisMonth: number }
    | { kind: 'worker'; mine: number; pool: number }
    | { kind: 'intake'; myActiveIntakes: number }
    | { kind: 'watch'; active: number };

type RevenueSales = { id: number; name: string; revenue: number };

const props = defineProps<{
    // The org-wide aggregate band is composed per permission (DESIGN_RBAC.md §4.4):
    // `stats` is absent entirely for a scoped viewer (Sales), and each field within
    // it is present only if the viewer holds the matching data permission — so admin
    // gets `customers` alone, never `transactions`.
    stats?: {
        customers?: number;
        customersThisMonth?: number;
        transactions?: number;
        transactionsThisMonth?: number;
        activeWarranties?: number;
        activeSales?: number;
    };
    // Revenue band (H7d) — its own prop, NOT part of `stats`, because a scoped
    // viewer (manager/rep) gets no `stats` yet must still see their own money.
    // Absent entirely for anyone without the money gate (CS/maintenance/admin).
    // The figure is already bounded by Transaction::visibleTo; `scope` only picks
    // the heading.
    revenue?: {
        total: number;
        thisMonth: number;
        lastMonth: number;
        scope: 'org' | 'team' | 'own';
    };
    // Trend (transaction data) and warranty donut are each gated on their domain
    // permission, so both are optional.
    trend?: TrendPoint[];
    warrantyBreakdown?: { active: number; expired: number; none: number };
    // Detail widgets are omitted (absent) for roles without customer/call access —
    // e.g. admin, which sees only aggregates + calls. See DESIGN_RBAC.md §4.4.
    recentTransactions?: RecentRow[];
    expiringSoon?: ExpiringRow[];
    // Per-Sales ranking band (L2-B, replaces the reseller breakdowns). Present for a
    // viewer who spans multiple reps' books — a global role (org) or a manager
    // (team); `salesScope` labels the cards. Revenue variant is money-gated on top.
    topSales?: TopSales[];
    revenueBySales?: RevenueSales[];
    salesScope?: 'org' | 'team';
    recentCalls?: RecentCallRow[];
    // Ticket band (M4): `tickets` for ticket.view holders (every number is
    // ServiceTicket::visibleTo-scoped and each card links to the pre-filtered
    // list it was counted from); `ticketsAggregate` only for the aggregate-only
    // viewer (admin) — bare org counts, deliberately NOT clickable because
    // /service-tickets is 403 for them. At most one of the two is present.
    tickets?: TicketBand;
    ticketsAggregate?: { active: number; doneThisMonth: number };
    me: {
        myCustomers: number;
        myInteractionsToday: number;
        myExpiringWarranties: number;
        myRecentInteractions: MyInteractionRow[];
    };
    // Hierarchy band (H6) — the PEOPLE around the viewer. Absent for a viewer
    // without team.view (admin). The customer figure is NOT duplicated here: it is
    // `me.myCustomers`, whose MEANING shifts per tier, so we relabel it instead.
    team?: TeamSummary;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

// Narrowed views of the union, so the template never touches a field the current
// variant lacks.
const managerTeam = computed(() =>
    props.team?.kind === 'manager' ? props.team : null,
);
const salesTeam = computed(() =>
    props.team?.kind === 'sales' ? props.team : null,
);
const supportTeam = computed(() =>
    props.team?.kind === 'support' ? props.team : null,
);

// Narrowed ticket-band variants (same idiom as the team band above).
const spanTickets = computed(() =>
    props.tickets?.kind === 'span' ? props.tickets : null,
);
const workerTickets = computed(() =>
    props.tickets?.kind === 'worker' ? props.tickets : null,
);
const intakeTickets = computed(() =>
    props.tickets?.kind === 'intake' ? props.tickets : null,
);
const watchTickets = computed(() =>
    props.tickets?.kind === 'watch' ? props.tickets : null,
);

// Card click-throughs carry the SAME filters the backend counted with, so the
// number on a card equals the total of the list it opens (H7d anti-mismatch).
const ticketLinks = {
    active: ServiceTicketController.index.url({
        query: { status: 'active' },
    }),
    done: ServiceTicketController.index.url({ query: { status: 'done' } }),
    mine: ServiceTicketController.index.url({
        query: { assigned: 'me', status: 'active' },
    }),
    pool: ServiceTicketController.index.url({
        query: { assigned: 'pool', status: 'received' },
    }),
    intake: ServiceTicketController.index.url({
        query: { created: 'me', status: 'active' },
    }),
};

// `me.myCustomers` IS the hierarchy-scoped Customer::visibleTo count — the very
// number /customers lists — but what it MEANS differs per tier: a rep's own book,
// a manager's whole team, the books a support agent was assigned to. One number,
// one source, relabelled — never a second card showing the same figure.
const customerCard = computed(() => {
    if (managerTeam.value) {
        return {
            label: 'Customer Tim',
            description: 'seluruh customer tim Anda',
        };
    }

    if (supportTeam.value) {
        return {
            label: 'Customer Dapat Diakses',
            description: 'yang Anda input atau dari sales yang menugaskan Anda',
        };
    }

    return {
        label: 'Customer Saya',
        description: 'yang Anda input atau tangani',
    };
});

// Revenue widgets are present only for users the backend gave a money band to.
const hasRevenue = computed(() => props.revenue !== undefined);

// The band is titled by the scope it actually covers, so a manager is never left
// wondering whether "Pendapatan" means their team or the whole company.
const revenueLabels = computed(() => {
    switch (props.revenue?.scope) {
        case 'team':
            return {
                total: 'Pendapatan Tim',
                month: 'Pendapatan Tim Bulan Ini',
                description: 'dari seluruh transaksi tim Anda',
            };
        case 'own':
            return {
                total: 'Pendapatan Saya',
                month: 'Pendapatan Saya Bulan Ini',
                description: 'dari customer yang Anda tangani',
            };
        default:
            return {
                total: 'Total Pendapatan',
                month: 'Pendapatan Bulan Ini',
                description: 'seluruh organisasi',
            };
    }
});

// The org-wide KPI band shows only when the viewer receives at least one
// aggregate (absent entirely for a scoped Sales viewer).
const hasOrgStats = computed(() => props.stats !== undefined);

// Customer-detail widgets (recent transactions, expiring warranties) and the
// call feed are omitted for roles without the access — the bands hide entirely
// rather than render empty.
const hasCustomerWidgets = computed(
    () => props.recentTransactions !== undefined,
);
const hasRecentCalls = computed(() => props.recentCalls !== undefined);

// Month-over-month revenue movement, shown as the "this month" card's subtext.
const revenueDelta = computed(() => {
    const current = props.revenue?.thisMonth ?? 0;
    const previous = props.revenue?.lastMonth ?? 0;

    if (previous <= 0) {
        return current > 0
            ? 'pendapatan pertama bulan ini'
            : 'belum ada bulan ini';
    }

    const pct = Math.round(((current - previous) / previous) * 100);
    const arrow = pct >= 0 ? '▲' : '▼';

    return `${arrow} ${Math.abs(pct)}% dari bulan lalu`;
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <!-- Page header -->
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Dashboard
                </h1>
                <p class="text-sm text-muted-foreground">
                    Ringkasan operasional — customer, transaksi, dan status
                    garansi.
                </p>
            </div>

            <!-- Band 0 — personal (scoped to the signed-in agent) -->
            <section class="flex flex-col gap-4">
                <h2 class="text-sm font-semibold text-foreground">
                    Ringkasan Saya
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        :label="customerCard.label"
                        :value="me.myCustomers"
                        :icon="UserCheck"
                        :description="customerCard.description"
                    />
                    <StatCard
                        label="Interaksi Saya Hari Ini"
                        :value="me.myInteractionsToday"
                        :icon="MessageSquare"
                        description="dicatat hari ini"
                    />
                    <StatCard
                        label="Garansi Akan Habis (Saya)"
                        :value="me.myExpiringWarranties"
                        :icon="ShieldAlert"
                        description="customer Anda, ≤ 30 hari"
                    />

                    <!-- Hierarchy band (H6) — the people around the viewer. -->
                    <StatCard
                        v-if="managerTeam"
                        label="Sales di Tim"
                        :value="managerTeam?.repCount ?? 0"
                        :icon="Users"
                        description="anggota tim Anda"
                    />
                    <StatCard
                        v-if="managerTeam"
                        label="CS & Maintenance"
                        :value="managerTeam?.supportCount ?? 0"
                        :icon="UserCog"
                        description="support di tim Anda"
                    />
                    <StatCard
                        v-if="salesTeam"
                        label="Support Saya"
                        :value="salesTeam?.supportCount ?? 0"
                        :icon="UserCog"
                        description="CS/Maintenance yang Anda tugaskan"
                    />
                    <StatCard
                        v-if="supportTeam"
                        label="Sales yang Dilayani"
                        :value="supportTeam?.repCount ?? 0"
                        :icon="Users"
                        description="yang menugaskan Anda"
                    />
                </div>
                <MyInteractionsCard :items="me.myRecentInteractions" />
            </section>

            <!-- Band 0b — service tickets (M4). One variant per capability tier;
                 every number is ServiceTicket::visibleTo-scoped and each card
                 opens the /service-tickets list pre-filtered with the exact
                 bounds it was counted with. -->
            <section v-if="tickets" class="flex flex-col gap-4">
                <h2 class="text-sm font-semibold text-foreground">Servis</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Supervisor: the team's workload -->
                    <StatCard
                        v-if="spanTickets"
                        label="Tiket Aktif"
                        :value="spanTickets.active"
                        :icon="Wrench"
                        description="diterima / dikerjakan, seluruh cakupan Anda"
                        :href="ticketLinks.active"
                    />
                    <StatCard
                        v-if="spanTickets"
                        label="Servis Selesai Bulan Ini"
                        :value="spanTickets.doneThisMonth"
                        :icon="Check"
                        description="tiket rampung bulan berjalan"
                        :href="ticketLinks.done"
                    />

                    <!-- Technician: my bench + the pool -->
                    <StatCard
                        v-if="workerTickets"
                        label="Kerjaan Saya"
                        :value="workerTickets.mine"
                        :icon="Wrench"
                        description="tiket aktif di tangan Anda"
                        :href="ticketLinks.mine"
                    />
                    <StatCard
                        v-if="workerTickets"
                        label="Pool"
                        :value="workerTickets.pool"
                        :icon="Inbox"
                        description="belum diklaim — siap diambil"
                        :href="ticketLinks.pool"
                    />

                    <!-- CS: follow-up on my intakes -->
                    <StatCard
                        v-if="intakeTickets"
                        label="Intake Saya Masih Aktif"
                        :value="intakeTickets.myActiveIntakes"
                        :icon="ClipboardList"
                        description="tiket yang Anda buat, belum selesai"
                        :href="ticketLinks.intake"
                    />

                    <!-- Sales (read-only): repairs across their book -->
                    <StatCard
                        v-if="watchTickets"
                        label="Servis Aktif"
                        :value="watchTickets.active"
                        :icon="Wrench"
                        description="unit customer Anda sedang diservis"
                        :href="ticketLinks.active"
                    />
                </div>
            </section>

            <!-- Band 0b (aggregate variant) — admin (B4): bare org counts, no
                 drill-down (/service-tickets is 403 for a viewer without
                 ticket.view), so the cards are deliberately not links. -->
            <section v-if="ticketsAggregate" class="flex flex-col gap-4">
                <h2 class="text-sm font-semibold text-foreground">
                    Servis (Organisasi)
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        label="Tiket Aktif"
                        :value="ticketsAggregate.active"
                        :icon="Wrench"
                        description="seluruh organisasi"
                    />
                    <StatCard
                        label="Selesai Bulan Ini"
                        :value="ticketsAggregate.doneThisMonth"
                        :icon="Check"
                        description="seluruh organisasi"
                    />
                </div>
            </section>

            <!-- Band 1 — org-wide KPI row. Absent for scoped (Sales) viewers; each
                 card is gated on its own data permission (e.g. admin gets only
                 Total Customer, never Total Transaksi). -->
            <div
                v-if="hasOrgStats"
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    v-if="stats?.customers !== undefined"
                    label="Total Customer"
                    :value="stats.customers"
                    :icon="Users"
                    :description="`${stats.customersThisMonth ?? 0} baru bulan ini`"
                />
                <StatCard
                    v-if="stats?.transactions !== undefined"
                    label="Total Transaksi"
                    :value="stats.transactions"
                    :icon="Receipt"
                    :description="`${stats.transactionsThisMonth ?? 0} transaksi bulan ini`"
                />
                <StatCard
                    v-if="stats?.activeWarranties !== undefined"
                    label="Garansi Aktif"
                    :value="stats.activeWarranties"
                    :icon="ShieldCheck"
                    description="unit masih bergaransi"
                />
                <StatCard
                    v-if="stats?.activeSales !== undefined"
                    label="Sales Aktif"
                    :value="stats.activeSales"
                    :icon="Users"
                    description="akun sales yang aktif"
                />
            </div>

            <!-- Band 1b — revenue (only for users who may see money) -->
            <div v-if="hasRevenue" class="grid gap-6 lg:grid-cols-3">
                <!-- Without the per-Sales breakdown (a plain rep) the two money cards
                     sit side by side instead of leaving an empty column. -->
                <div
                    class="flex flex-col gap-4"
                    :class="
                        revenueBySales
                            ? ''
                            : 'lg:col-span-3 lg:flex-row lg:*:flex-1'
                    "
                >
                    <StatCard
                        :label="revenueLabels.total"
                        :value="formatIdr(revenue?.total ?? 0)"
                        :icon="Wallet"
                        :description="revenueLabels.description"
                    />
                    <StatCard
                        :label="revenueLabels.month"
                        :value="formatIdr(revenue?.thisMonth ?? 0)"
                        :icon="TrendingUp"
                        :description="revenueDelta"
                    />
                </div>
                <div v-if="revenueBySales" class="lg:col-span-2">
                    <RevenueBySalesCard
                        :items="revenueBySales"
                        :scope="salesScope ?? 'org'"
                    />
                </div>
            </div>

            <!-- Band 1c — top sales ranking (org or team per salesScope). Its own
                 band, not tied to the customer-detail widgets, so a manager (who has
                 no org-detail band) still receives it. -->
            <div v-if="topSales" class="grid gap-6 lg:grid-cols-3">
                <TopSalesCard :items="topSales" :scope="salesScope ?? 'org'" />
            </div>

            <!-- Band 2 — trend + warranty donut. Each is gated on its domain
                 permission (trend → transaction.view.all, donut → customer.view.all),
                 so the band hides when the viewer receives neither. -->
            <div
                v-if="trend || warrantyBreakdown"
                class="grid gap-6 lg:grid-cols-3"
            >
                <div v-if="trend" class="lg:col-span-2">
                    <TransactionTrendChart :data="trend" />
                </div>
                <WarrantyDonut
                    v-if="warrantyBreakdown"
                    :active="warrantyBreakdown.active"
                    :expired="warrantyBreakdown.expired"
                    :none="warrantyBreakdown.none"
                />
            </div>

            <!-- Band 3 — recent activity + watchlist -->
            <div v-if="hasCustomerWidgets" class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <RecentTransactionsCard :rows="recentTransactions ?? []" />
                </div>
                <div class="flex flex-col gap-6">
                    <ExpiringWarrantyCard :items="expiringSoon ?? []" />
                </div>
            </div>

            <!-- Band 4 — recent calls across the org (CTI + manual) -->
            <RecentCallsCard v-if="hasRecentCalls" :items="recentCalls ?? []" />
        </div>
    </AppLayout>
</template>
