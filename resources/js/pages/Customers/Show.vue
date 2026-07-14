<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { PackageCheck, Wallet } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerHeader from '@/components/CustomerHeader.vue';
import InteractionTimeline from '@/components/InteractionTimeline.vue';
import LogInteractionModal from '@/components/LogInteractionModal.vue';
import WarrantyBadge from '@/components/WarrantyBadge.vue';
import WidgetEmptyState from '@/components/WidgetEmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatIdr } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type {
    CustomerDetail,
    CustomerStats,
    CustomerTransactionRow,
    InteractionOptions,
    InteractionRow,
    Paginated,
    SelectOption,
} from '@/types/crm';

const props = defineProps<{
    customer: CustomerDetail;
    timeline: Paginated<InteractionRow>;
    transactions: CustomerTransactionRow[];
    warrantySummary: { active: number; expired: number; none: number };
    stats: CustomerStats;
    statuses: SelectOption[];
    users: SelectOption[];
    can: { update: boolean; delete: boolean; logInteraction: boolean };
    interactionOptions: InteractionOptions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customer', href: CustomerController.index() },
    {
        title: props.customer.name,
        href: CustomerController.show(props.customer.id),
    },
];

const interactions = ref<InteractionRow[]>([...props.timeline.data]);
const loadingMore = ref(false);

const hasMore = computed(
    () => props.timeline.current_page < props.timeline.last_page,
);

// Page 1 (fresh load / post-mutation refresh) resets the list; later pages
// ("Muat lagi") append. Server stays the single source of truth.
watch(
    () => props.timeline,
    (timeline) => {
        interactions.value =
            timeline.current_page <= 1
                ? [...timeline.data]
                : [...interactions.value, ...timeline.data];
    },
);

function loadMore() {
    if (!hasMore.value || loadingMore.value) {
        return;
    }

    loadingMore.value = true;

    // preserveUrl keeps the address clean so a later mutation refresh lands on
    // page 1 (the newest), not a stale ?page=N.
    router.reload({
        only: ['timeline'],
        data: { page: props.timeline.current_page + 1 },
        preserveUrl: true,
        onFinish: () => {
            loadingMore.value = false;
        },
    });
}

// Log-interaction modal (shared for create + edit).
const modalOpen = ref(false);
const editing = ref<InteractionRow | null>(null);

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(item: InteractionRow) {
    editing.value = item;
    modalOpen.value = true;
}

function onSaved() {
    modalOpen.value = false;
}

// Price label for a transaction row: null when the viewer may not see money (the
// backend omits `amount` entirely), so the caller hides the price; '—' for a
// recorded-but-empty amount; otherwise the formatted value.
function amountText(amount: string | null | undefined): string | null {
    if (amount === undefined) {
        return null;
    }

    return amount === null ? '—' : formatIdr(amount);
}
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <CustomerHeader
                :customer="customer"
                :stats="stats"
                :statuses="statuses"
                :users="users"
                :can="can"
                @log="openCreate"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Timeline -->
                <div class="lg:col-span-2">
                    <InteractionTimeline
                        :items="interactions"
                        :has-more="hasMore"
                        :loading="loadingMore"
                        :can-log="can.logInteraction"
                        @load-more="loadMore"
                        @edit="openEdit"
                        @log="openCreate"
                    />
                </div>

                <!-- Right column -->
                <div class="flex flex-col gap-6">
                    <!-- Warranty summary -->
                    <div
                        class="rounded-xl border border-border bg-card p-5 shadow-sm"
                    >
                        <h2 class="mb-4 text-sm font-semibold text-foreground">
                            Ringkasan Garansi
                        </h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <dt
                                    class="inline-flex items-center gap-2 text-muted-foreground"
                                >
                                    <span
                                        class="size-2 rounded-full bg-green-500"
                                        aria-hidden="true"
                                    />Aktif
                                </dt>
                                <dd
                                    class="font-medium text-foreground tabular-nums"
                                >
                                    {{ warrantySummary.active }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt
                                    class="inline-flex items-center gap-2 text-muted-foreground"
                                >
                                    <span
                                        class="size-2 rounded-full bg-red-500"
                                        aria-hidden="true"
                                    />Berakhir
                                </dt>
                                <dd
                                    class="font-medium text-foreground tabular-nums"
                                >
                                    {{ warrantySummary.expired }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt
                                    class="inline-flex items-center gap-2 text-muted-foreground"
                                >
                                    <span
                                        class="size-2 rounded-full bg-slate-300"
                                        aria-hidden="true"
                                    />Tanpa garansi
                                </dt>
                                <dd
                                    class="font-medium text-foreground tabular-nums"
                                >
                                    {{ warrantySummary.none }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            v-if="stats.totalSpend !== undefined"
                            class="mt-4 flex items-center justify-between border-t border-border pt-4 text-sm"
                        >
                            <span
                                class="inline-flex items-center gap-2 text-muted-foreground"
                            >
                                <Wallet
                                    class="size-4"
                                    aria-hidden="true"
                                />Total belanja
                            </span>
                            <span
                                class="font-semibold text-foreground tabular-nums"
                            >
                                {{ formatIdr(stats.totalSpend ?? 0) }}
                            </span>
                        </div>
                    </div>

                    <!-- Transactions -->
                    <div
                        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                    >
                        <div class="border-b border-border p-5">
                            <h2 class="text-sm font-semibold text-foreground">
                                Transaksi &amp; Garansi
                            </h2>
                        </div>

                        <WidgetEmptyState
                            v-if="transactions.length === 0"
                            :icon="PackageCheck"
                            message="Belum ada transaksi."
                        />

                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="t in transactions"
                                :key="t.id"
                                class="space-y-2 p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <span
                                        class="text-sm font-medium text-foreground"
                                        >{{ t.product ?? '—' }}</span
                                    >
                                    <span
                                        v-if="amountText(t.amount) !== null"
                                        class="shrink-0 text-sm font-medium text-foreground tabular-nums"
                                        >{{ amountText(t.amount) }}</span
                                    >
                                </div>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                        >{{ t.purchased_at }}</span
                                    >
                                    <WarrantyBadge
                                        :is-under-warranty="t.is_under_warranty"
                                        :expires-at="t.warranty_expires_at"
                                        :warranty-months="
                                            t.warranty_months ?? 0
                                        "
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Info -->
                    <div
                        class="rounded-xl border border-border bg-card p-5 shadow-sm"
                    >
                        <h2 class="mb-4 text-sm font-semibold text-foreground">
                            Informasi
                        </h2>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Alamat
                                </dt>
                                <dd class="text-foreground">
                                    {{ customer.address ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Sumber
                                </dt>
                                <dd class="text-foreground">
                                    {{ customer.source_label ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Owner
                                </dt>
                                <dd class="text-foreground">
                                    {{
                                        customer.owner
                                            ? customer.owner.name
                                            : 'Belum ditugaskan'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <LogInteractionModal
            v-model:open="modalOpen"
            :customer-id="customer.id"
            :options="interactionOptions"
            :interaction="editing"
            @saved="onSaved"
        />
    </AppLayout>
</template>
