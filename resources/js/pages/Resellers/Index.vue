<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, CircleAlert, Network, Plus } from '@lucide/vue';
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

const props = defineProps<{
    tree: ResellerNode[];
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reseller', href: ResellerController.index() },
];

function countNodes(nodes: ResellerNode[]): number {
    return nodes.reduce((sum, n) => sum + 1 + countNodes(n.children), 0);
}

const totalResellers = computed(() => countNodes(props.tree));
</script>

<template>
    <Head title="Data Reseller" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <!-- Page header -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="space-y-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Data Reseller
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ totalResellers }} reseller dalam struktur hierarki
                        (induk &amp; anak).
                    </p>
                </div>

                <Button v-if="can.create" as-child>
                    <Link :href="ResellerController.create()">
                        <Plus />
                        Tambah Reseller
                    </Link>
                </Button>
            </div>

            <!-- Flash -->
            <div
                v-if="flashSuccess"
                class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                <Check class="size-4 shrink-0" />
                <span>{{ flashSuccess }}</span>
            </div>

            <div
                v-if="flashError"
                class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300"
            >
                <CircleAlert class="size-4 shrink-0" />
                <span>{{ flashError }}</span>
            </div>

            <!-- Content card -->
            <div
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-border px-6 py-3.5"
                >
                    <span
                        class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                    >
                        Struktur Reseller
                    </span>
                    <span class="text-xs text-muted-foreground">
                        {{ totalResellers }} reseller
                    </span>
                </div>

                <div v-if="tree.length" class="p-2">
                    <ul>
                        <ResellerTreeNode
                            v-for="node in tree"
                            :key="node.id"
                            :node="node"
                            :can="can"
                            :depth="0"
                        />
                    </ul>
                </div>

                <div v-else class="px-6 py-16 text-center">
                    <div
                        class="mx-auto flex max-w-sm flex-col items-center gap-2"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <Network class="size-5" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Belum ada data reseller
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Tambahkan reseller pertama untuk mulai.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
