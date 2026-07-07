<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Network, Receipt, ShieldCheck, Users } from '@lucide/vue';
import StatCard from '@/components/StatCard.vue';
import TransactionTrendChart from '@/components/TransactionTrendChart.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type TrendPoint = { month: string; label: string; count: number };

defineProps<{
    stats: {
        customers: number;
        transactions: number;
        productsUnderWarranty: number;
        activeResellers: number;
    };
    trend: TrendPoint[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total Customer"
                    :value="stats.customers"
                    :icon="Users"
                    description="Customer terdaftar"
                />
                <StatCard
                    label="Total Transaksi"
                    :value="stats.transactions"
                    :icon="Receipt"
                    description="Transaksi tercatat"
                />
                <StatCard
                    label="Produk Bergaransi Aktif"
                    :value="stats.productsUnderWarranty"
                    :icon="ShieldCheck"
                    description="Masih dalam masa garansi"
                />
                <StatCard
                    label="Reseller Aktif"
                    :value="stats.activeResellers"
                    :icon="Network"
                    description="Punya customer atau transaksi"
                />
            </div>

            <TransactionTrendChart :data="trend" />
        </div>
    </AppLayout>
</template>
