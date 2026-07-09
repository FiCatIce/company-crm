<script setup lang="ts">
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toDatetimeLocal } from '@/lib/format';
import type { InteractionOptions, InteractionRow } from '@/types/crm';

const props = defineProps<{
    errors: Partial<Record<string, string>>;
    options: InteractionOptions;
    interaction?: InteractionRow | null;
}>();

// Controlled refs so the fields survive the Form's validation-error re-render.
const type = ref(props.interaction?.type ?? 'call');
const direction = ref(props.interaction?.direction ?? 'out');
const subject = ref(props.interaction?.subject ?? '');
const body = ref(props.interaction?.body ?? '');
const outcome = ref(props.interaction?.outcome ?? '');
const durationSec = ref(
    props.interaction?.duration_sec != null
        ? String(props.interaction.duration_sec)
        : '',
);
const occurredAtLocal = ref(
    toDatetimeLocal(props.interaction?.occurred_at ?? null),
);

const isCall = computed(() => type.value === 'call');

// Submit an absolute (UTC) instant so the server stores it unambiguously,
// regardless of the viewer's timezone.
const occurredAtIso = computed(() => {
    const value = occurredAtLocal.value;

    return value ? new Date(value).toISOString() : '';
});

const controlClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="type">Tipe</Label>
                <select
                    id="type"
                    v-model="type"
                    name="type"
                    :class="controlClasses"
                >
                    <option
                        v-for="o in options.types"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </select>
                <InputError :message="errors.type" />
            </div>

            <div v-if="isCall" class="grid gap-2">
                <Label for="direction">Arah</Label>
                <select
                    id="direction"
                    v-model="direction"
                    name="direction"
                    :class="controlClasses"
                >
                    <option
                        v-for="o in options.directions"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </select>
                <InputError :message="errors.direction" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="subject">Subjek</Label>
            <Input
                id="subject"
                v-model="subject"
                name="subject"
                placeholder="Ringkasan singkat…"
            />
            <InputError :message="errors.subject" />
        </div>

        <div class="grid gap-2">
            <Label for="body">Catatan</Label>
            <textarea
                id="body"
                v-model="body"
                name="body"
                rows="4"
                :class="`${controlClasses} h-auto py-2`"
                placeholder="Detail interaksi…"
            />
            <InputError :message="errors.body" />
        </div>

        <div v-if="isCall" class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="outcome">Hasil</Label>
                <select
                    id="outcome"
                    v-model="outcome"
                    name="outcome"
                    :class="controlClasses"
                >
                    <option value="">—</option>
                    <option
                        v-for="o in options.outcomes"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </select>
                <InputError :message="errors.outcome" />
            </div>
            <div class="grid gap-2">
                <Label for="duration_sec">Durasi (detik)</Label>
                <Input
                    id="duration_sec"
                    v-model="durationSec"
                    name="duration_sec"
                    type="number"
                    min="0"
                    placeholder="0"
                />
                <InputError :message="errors.duration_sec" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="occurred_at">Waktu</Label>
            <input
                id="occurred_at"
                v-model="occurredAtLocal"
                type="datetime-local"
                :class="controlClasses"
            />
            <input type="hidden" name="occurred_at" :value="occurredAtIso" />
            <InputError :message="errors.occurred_at" />
        </div>
    </div>
</template>
