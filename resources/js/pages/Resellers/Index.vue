<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ResellerController from '@/actions/App/Http/Controllers/ResellerController';
import ResellerTreeNode from '@/components/ResellerTreeNode.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type ResellerNode = {
    id: number;
    name: string;
    parent_id: number | null;
    customers_count: number;
    children: ResellerNode[];
};

defineProps<{
    tree: ResellerNode[];
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reseller', href: ResellerController.index() },
];
</script>

<template>
    <Head title="Data Reseller" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Data Reseller
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Struktur hierarki reseller (induk &amp; anak).
                    </p>
                </div>

                <Button v-if="can.create" as-child>
                    <Link :href="ResellerController.create()"
                        >Tambah Reseller</Link
                    >
                </Button>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800/40 dark:bg-green-900/20 dark:text-green-300"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800/40 dark:bg-red-900/20 dark:text-red-300"
            >
                {{ flashError }}
            </div>

            <div
                class="overflow-hidden rounded-xl border border-border bg-card p-2 shadow-sm"
            >
                <ul v-if="tree.length">
                    <ResellerTreeNode
                        v-for="node in tree"
                        :key="node.id"
                        :node="node"
                        :can="can"
                        :depth="0"
                    />
                </ul>
                <p v-else class="px-3 py-12 text-center text-muted-foreground">
                    Belum ada data reseller.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
