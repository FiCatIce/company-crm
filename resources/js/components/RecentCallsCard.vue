<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, PhoneOff } from '@lucide/vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import InteractionOutcomeBadge from '@/components/InteractionOutcomeBadge.vue';
import InteractionTypeIcon from '@/components/InteractionTypeIcon.vue';
import WidgetEmptyState from '@/components/WidgetEmptyState.vue';
import { formatDuration, relativeDays } from '@/lib/format';
import type { RecentCallRow } from '@/types/crm';

defineProps<{
    items: RecentCallRow[];
}>();
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
    >
        <div class="border-b border-border p-5">
            <h2 class="text-sm font-semibold text-foreground">
                Panggilan Terbaru
            </h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Semua panggilan lintas agen (CTI &amp; manual).
            </p>
        </div>

        <WidgetEmptyState
            v-if="items.length === 0"
            :icon="PhoneOff"
            message="Belum ada panggilan."
        />

        <ul v-else class="divide-y divide-border">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-start gap-3 px-5 py-3"
                :class="
                    item.is_cti_lead
                        ? 'border-l-2 border-amber-400 bg-amber-50'
                        : ''
                "
            >
                <InteractionTypeIcon type="call" />

                <div class="min-w-0 flex-1">
                    <div
                        class="flex flex-wrap items-center gap-1.5 text-sm font-medium text-foreground"
                    >
                        <ArrowDownLeft
                            v-if="item.direction === 'in'"
                            class="size-3.5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <ArrowUpRight
                            v-else-if="item.direction === 'out'"
                            class="size-3.5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Link
                            v-if="item.customer"
                            :href="CustomerController.show(item.customer.id)"
                            class="truncate text-primary hover:underline"
                        >
                            {{ item.customer.name }}
                        </Link>
                        <span v-else class="text-muted-foreground">—</span>

                        <span
                            v-if="item.source === 'cti'"
                            class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                        >
                            Otomatis
                        </span>
                        <span
                            v-if="item.is_cti_lead"
                            class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                        >
                            Prospek baru
                        </span>
                    </div>

                    <div
                        class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                    >
                        <InteractionOutcomeBadge
                            v-if="item.outcome"
                            :outcome="item.outcome"
                            :label="item.outcome_label ?? item.outcome"
                        />
                        <span v-if="formatDuration(item.duration_sec)">
                            {{ formatDuration(item.duration_sec) }}
                        </span>
                        <span aria-hidden="true">·</span>
                        <span>
                            oleh {{ item.user ? item.user.name : 'sistem' }}
                        </span>
                    </div>
                </div>

                <span
                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                    :title="item.occurred_at"
                >
                    {{ relativeDays(item.occurred_at) }}
                </span>
            </li>
        </ul>
    </div>
</template>
