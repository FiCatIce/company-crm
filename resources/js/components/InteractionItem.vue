<script setup lang="ts">
import { ArrowDownLeft, ArrowUpRight, Clock, Zap } from '@lucide/vue';
import { computed } from 'vue';
import InteractionOutcomeBadge from '@/components/InteractionOutcomeBadge.vue';
import InteractionTypeIcon from '@/components/InteractionTypeIcon.vue';
import { formatClock, formatDuration } from '@/lib/format';
import type { InteractionRow } from '@/types/crm';

const props = defineProps<{ item: InteractionRow }>();

const duration = computed(() => formatDuration(props.item.duration_sec));
const isAuto = computed(() => props.item.source === 'cti');
</script>

<template>
    <div class="flex gap-3">
        <InteractionTypeIcon :type="item.type" />

        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-start justify-between gap-3">
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
                    <span
                        v-if="isAuto"
                        class="inline-flex items-center gap-1 rounded-full bg-accent px-2 py-0.5 text-[11px] font-medium text-accent-foreground"
                    >
                        <Zap class="size-3" aria-hidden="true" />
                        Otomatis
                    </span>
                </div>
                <span
                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                    :title="item.occurred_at"
                >
                    {{ formatClock(item.occurred_at) }}
                </span>
            </div>

            <p v-if="item.subject" class="text-sm font-medium text-foreground">
                {{ item.subject }}
            </p>
            <p
                v-if="item.body"
                class="text-sm whitespace-pre-line text-muted-foreground"
            >
                {{ item.body }}
            </p>

            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
            >
                <InteractionOutcomeBadge
                    v-if="item.outcome && item.outcome_label"
                    :outcome="item.outcome"
                    :label="item.outcome_label"
                />
                <span v-if="duration" class="inline-flex items-center gap-1">
                    <Clock class="size-3" aria-hidden="true" />
                    {{ duration }}
                </span>
                <span>{{
                    item.user ? `oleh ${item.user.name}` : 'oleh sistem'
                }}</span>
            </div>
        </div>
    </div>
</template>
