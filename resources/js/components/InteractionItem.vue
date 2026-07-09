<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Clock,
    Pencil,
    Trash2,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import InteractionController from '@/actions/App/Http/Controllers/InteractionController';
import InteractionOutcomeBadge from '@/components/InteractionOutcomeBadge.vue';
import InteractionTypeIcon from '@/components/InteractionTypeIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatClock, formatDuration } from '@/lib/format';
import type { InteractionRow } from '@/types/crm';

const props = defineProps<{ item: InteractionRow }>();

defineEmits<{ (e: 'edit', item: InteractionRow): void }>();

const duration = computed(() => formatDuration(props.item.duration_sec));
const isAuto = computed(() => props.item.source === 'cti');
</script>

<template>
    <div class="group flex gap-3">
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

                <div class="flex shrink-0 items-center gap-1">
                    <span
                        class="text-xs text-muted-foreground tabular-nums"
                        :title="item.occurred_at"
                    >
                        {{ formatClock(item.occurred_at) }}
                    </span>

                    <Button
                        v-if="item.can_edit"
                        variant="ghost"
                        size="icon"
                        class="size-7 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                        aria-label="Edit interaksi"
                        @click="$emit('edit', item)"
                    >
                        <Pencil class="size-3.5" />
                    </Button>

                    <Dialog v-if="item.can_delete">
                        <DialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive focus-visible:opacity-100"
                                aria-label="Hapus interaksi"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    InteractionController.destroy.form(item.id)
                                "
                                :options="{
                                    preserveScroll: true,
                                    preserveState: true,
                                    only: ['timeline', 'stats'],
                                }"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>Hapus interaksi?</DialogTitle>
                                    <DialogDescription>
                                        Catatan interaksi ini akan dihapus.
                                        Tindakan ini tidak dapat dibatalkan.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                        >
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                    >
                                        Hapus
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
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
