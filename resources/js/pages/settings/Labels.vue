<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import LabelController from '@/actions/App/Http/Controllers/Settings/LabelController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { lowerWords } from '@/composables/useLabels';
import { edit } from '@/routes/labels';

// L3-C: rename how the role TYPES read across the whole UI (nav, dashboard,
// team pages, dropdowns). Slugs and permissions never change — display only.
type LabelRow = {
    slug: string;
    default: string;
    override: string | null;
};

const props = defineProps<{
    rows: LabelRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Label tampilan',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm<{ overrides: Record<string, string> }>({
    overrides: Object.fromEntries(
        props.rows.map((row) => [row.slug, row.override ?? '']),
    ),
});

// What the label WILL resolve to if saved now — draft, else the default.
const effective = (row: LabelRow): string =>
    form.overrides[row.slug]?.trim() || row.default;

const submit = () =>
    form.put(LabelController.update.url(), { preserveScroll: true });

const reset = (row: LabelRow) => {
    form.overrides[row.slug] = '';
};
</script>

<template>
    <Head title="Label tampilan" />

    <h1 class="sr-only">Label tampilan</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Label Tampilan"
            description="Ganti sebutan tiap tipe user — seluruh UI (dashboard, tim, dropdown) langsung ikut. Kosongkan untuk kembali ke default."
        />

        <div
            v-if="flashSuccess"
            class="flex items-center gap-2 rounded-lg border border-state-ok/30 bg-state-ok-soft px-4 py-3 text-sm text-state-ok-strong"
        >
            <Check class="size-4 shrink-0" />
            <span>{{ flashSuccess }}</span>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div
                v-for="row in rows"
                :key="row.slug"
                class="space-y-2 rounded-xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="flex items-center justify-between gap-2">
                    <Label :for="`label-${row.slug}`">
                        {{ row.default }}
                        <span class="font-normal text-muted-foreground">
                            · {{ row.slug }}
                        </span>
                    </Label>
                    <Button
                        v-if="form.overrides[row.slug]"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground"
                        @click="reset(row)"
                    >
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>

                <Input
                    :id="`label-${row.slug}`"
                    v-model="form.overrides[row.slug]"
                    class="block w-full"
                    maxlength="40"
                    :placeholder="row.default"
                />
                <InputError :message="form.errors[`overrides.${row.slug}`]" />

                <!-- Preview: one title position + one mid-sentence position, so
                     the admin sees the automatic sentence-case (D4) up front. -->
                <p class="text-xs text-muted-foreground">
                    Contoh:
                    <span class="font-medium text-foreground"
                        >{{ effective(row) }} Teratas</span
                    >
                    · &laquo;dari
                    <span class="font-medium text-foreground">{{
                        lowerWords(effective(row))
                    }}</span>
                    yang menugaskan Anda&raquo;
                </p>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="form.processing">Simpan</Button>
            </div>
        </form>
    </div>
</template>
