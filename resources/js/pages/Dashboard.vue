<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    MessageSquare,
    Network,
    Receipt,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
    UserCheck,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import ExpiringWarrantyCard from '@/components/ExpiringWarrantyCard.vue';
import MyInteractionsCard from '@/components/MyInteractionsCard.vue';
import RecentTransactionsCard from '@/components/RecentTransactionsCard.vue';
import RevenueByResellerCard from '@/components/RevenueByResellerCard.vue';
import StatCard from '@/components/StatCard.vue';
import TopResellersCard from '@/components/TopResellersCard.vue';
import TransactionTrendChart from '@/components/TransactionTrendChart.vue';
import WarrantyDonut from '@/components/WarrantyDonut.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatIdr } from '@/lib/format';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { MyInteractionRow } from '@/types/crm';

type TrendPoint = { month: string; label: string; count: number };

type RecentRow = {
    id: number;
    customer: string | null;
    product: string | null;
    reseller: string | null;
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

type TopReseller = { id: number; name: string; customers_count: number };

type RevenueReseller = { id: number; name: string; revenue: number };

const props = defineProps<{
    stats: {
        customers: number;
        customersThisMonth: number;
        transactions: number;
        transactionsThisMonth: number;
        activeWarranties: number;
        activeResellers: number;
        revenue: number;
        revenueThisMonth: number;
        revenueLastMonth: number;
    };
    trend: TrendPoint[];
    warrantyBreakdown: { active: number; expired: number; none: number };
    recentTransactions: RecentRow[];
    expiringSoon: ExpiringRow[];
    topResellers: TopReseller[];
    topResellersByRevenue: RevenueReseller[];
    me: {
        myCustomers: number;
        myInteractionsToday: number;
        myExpiringWarranties: number;
        myRecentInteractions: MyInteractionRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

// Month-over-month revenue movement, shown as the "this month" card's subtext.
const revenueDelta = computed(() => {
    const current = props.stats.revenueThisMonth;
    const previous = props.stats.revenueLastMonth;

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
                        label="Customer Saya"
                        :value="me.myCustomers"
                        :icon="UserCheck"
                        description="ditugaskan kepada Anda"
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
                </div>
                <MyInteractionsCard :items="me.myRecentInteractions" />
            </section>

            <!-- Band 1 — KPI row -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total Customer"
                    :value="stats.customers"
                    :icon="Users"
                    :description="`${stats.customersThisMonth} baru bulan ini`"
                />
                <StatCard
                    label="Total Transaksi"
                    :value="stats.transactions"
                    :icon="Receipt"
                    :description="`${stats.transactionsThisMonth} transaksi bulan ini`"
                />
                <StatCard
                    label="Garansi Aktif"
                    :value="stats.activeWarranties"
                    :icon="ShieldCheck"
                    description="unit masih bergaransi"
                />
                <StatCard
                    label="Reseller Aktif"
                    :value="stats.activeResellers"
                    :icon="Network"
                    description="punya customer atau transaksi"
                />
            </div>

            <!-- Band 1b — revenue -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="flex flex-col gap-4">
                    <StatCard
                        label="Total Pendapatan"
                        :value="formatIdr(stats.revenue)"
                        :icon="Wallet"
                        description="akumulasi semua transaksi"
                    />
                    <StatCard
                        label="Pendapatan Bulan Ini"
                        :value="formatIdr(stats.revenueThisMonth)"
                        :icon="TrendingUp"
                        :description="revenueDelta"
                    />
                </div>
                <div class="lg:col-span-2">
                    <RevenueByResellerCard :items="topResellersByRevenue" />
                </div>
            </div>

            <!-- Band 2 — trend + warranty donut -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <TransactionTrendChart :data="trend" />
                </div>
                <WarrantyDonut
                    :active="warrantyBreakdown.active"
                    :expired="warrantyBreakdown.expired"
                    :none="warrantyBreakdown.none"
                />
            </div>

            <!-- Band 3 — recent activity + watchlist -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <RecentTransactionsCard :rows="recentTransactions" />
                </div>
                <div class="flex flex-col gap-6">
                    <ExpiringWarrantyCard :items="expiringSoon" />
                    <TopResellersCard :items="topResellers" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
