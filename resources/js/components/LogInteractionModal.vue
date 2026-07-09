<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import InteractionController from '@/actions/App/Http/Controllers/InteractionController';
import InteractionFormFields from '@/components/InteractionFormFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { InteractionOptions, InteractionRow } from '@/types/crm';

const props = defineProps<{
    open: boolean;
    customerId: number;
    options: InteractionOptions;
    interaction?: InteractionRow | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const isEdit = computed(() => !!props.interaction);

const formAction = computed(() =>
    props.interaction
        ? InteractionController.update.form(props.interaction.id)
        : InteractionController.store.form(props.customerId),
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <!-- Remount per open so the fields re-prefill / reset cleanly. -->
            <Form
                v-if="open"
                v-bind="formAction"
                :options="{
                    preserveScroll: true,
                    preserveState: true,
                    only: ['timeline', 'stats'],
                }"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('saved')"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        isEdit ? 'Edit Interaksi' : 'Catat Interaksi'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            isEdit
                                ? 'Perbarui catatan interaksi ini.'
                                : 'Catat panggilan, pesan, atau kunjungan dengan customer.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <InteractionFormFields
                    :errors="errors"
                    :options="options"
                    :interaction="interaction"
                />

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="emit('update:open', false)"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ isEdit ? 'Simpan' : 'Catat' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
