<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, MessageSquareDashed } from '@lucide/vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import InteractionTypeIcon from '@/components/InteractionTypeIcon.vue';
import WidgetEmptyState from '@/components/WidgetEmptyState.vue';
import { relativeDays } from '@/lib/format';
import type { MyInteractionRow } from '@/types/crm';

defineProps<{
    items: MyInteractionRow[];
}>();
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
    >
        <div class="border-b border-border p-5">
            <h2 class="text-sm font-semibold text-foreground">
                Interaksi Terakhir Saya
            </h2>
        </div>

        <WidgetEmptyState
            v-if="items.length === 0"
            :icon="MessageSquareDashed"
            message="Belum ada interaksi yang Anda catat."
        />

        <ul v-else class="divide-y divide-border">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-start gap-3 px-5 py-3"
            >
                <InteractionTypeIcon :type="item.type" />

                <div class="min-w-0 flex-1">
                    <div
                        class="flex items-center gap-1.5 text-sm font-medium text-foreground"
                    >
                        <span>{{ item.type_label }}</span>
                        <ArrowDownLeft
                            v-if="item.direction === 'in'"
                            class="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <ArrowUpRight
                            v-else-if="item.direction === 'out'"
                            class="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span aria-hidden="true" class="text-muted-foreground"
                            >·</span
                        >
                        <Link
                            v-if="item.customer"
                            :href="CustomerController.show(item.customer.id)"
                            class="truncate text-primary hover:underline"
                        >
                            {{ item.customer.name }}
                        </Link>
                        <span v-else class="text-muted-foreground">—</span>
                    </div>
                    <p
                        v-if="item.subject"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ item.subject }}
                    </p>
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
