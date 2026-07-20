<script setup lang="ts">
import { Form, Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, CircleAlert, Plus, UserCog } from '@lucide/vue';
import { computed } from 'vue';
import SupportAssignmentController from '@/actions/App/Http/Controllers/SupportAssignmentController';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SupportAssigneeRow, SupportCandidateRow } from '@/types/crm';

const props = defineProps<{
    assignees: SupportAssigneeRow[];
    candidates: SupportCandidateRow[];
    hasTeam: boolean;
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Support Saya', href: SupportAssignmentController.index() },
];

const initial = (name: string) => name.trim().charAt(0).toUpperCase();

const typeStyles: Record<string, string> = {
    cs: 'border-violet-200 bg-violet-50 text-violet-700',
    maintenance: 'border-amber-200 bg-amber-50 text-amber-700',
};
const typeBadge = (value?: string) =>
    (value && typeStyles[value]) || 'border-border bg-muted text-foreground';

const formatDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';

// Explicit array state — a checkbox group binds straight to it, so no reliance on
// `name="x[]"` form serialisation.
const form = useForm<{ assignee_ids: number[] }>({ assignee_ids: [] });

const submit = () =>
    form.post(SupportAssignmentController.store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

const selectedCount = computed(() => form.assignee_ids.length);
</script>

<template>
    <Head title="Support Saya" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <UserCog class="size-5" />
                </div>
                <div class="space-y-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Support Saya
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        CS &amp; Maintenance yang Anda tugaskan untuk membantu
                        menangani customer Anda. Begitu di-assign, mereka
                        langsung bisa melihat customer Anda.
                    </p>
                </div>
            </div>

            <div
                v-if="flashSuccess"
                class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
            >
                <Check class="size-4 shrink-0" />
                <span>{{ flashSuccess }}</span>
            </div>
            <div
                v-if="flashError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ flashError }}
            </div>

            <!-- Current assignments -->
            <section
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="border-b border-border px-6 py-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Sedang menangani customer Anda
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ props.assignees.length }} orang punya akses ke
                        customer Anda lewat penugasan ini.
                    </p>
                </div>

                <ul
                    v-if="props.assignees.length"
                    class="divide-y divide-border"
                >
                    <li
                        v-for="a in props.assignees"
                        :key="a.id"
                        class="flex flex-wrap items-center gap-3 px-6 py-4 transition-colors hover:bg-accent/50"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                        >
                            {{ initial(a.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-foreground">
                                {{ a.name }}
                            </div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ a.email }}
                            </div>
                        </div>
                        <span
                            v-if="a.type"
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="typeBadge(a.type.value)"
                            >{{ a.type.label }}</span
                        >
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            sejak {{ formatDate(a.assigned_at) }}
                        </span>

                        <Dialog>
                            <DialogTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                >
                                    Lepas
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        SupportAssignmentController.destroy.form(
                                            a.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    class="space-y-6"
                                    v-slot="{ processing }"
                                >
                                    <DialogHeader class="space-y-3">
                                        <DialogTitle>
                                            Lepas penugasan?
                                        </DialogTitle>
                                        <DialogDescription>
                                            <strong>{{ a.name }}</strong> tidak
                                            akan bisa melihat customer Anda lagi
                                            setelah dilepas. Akun mereka tetap
                                            aktif dan penugasan dari sales lain
                                            tidak terpengaruh.
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
                                            Lepas penugasan
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </li>
                </ul>

                <div v-else class="px-6 py-12 text-center">
                    <div
                        class="mx-auto flex max-w-sm flex-col items-center gap-2"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <UserCog class="size-5" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Belum ada CS/mekanik yang Anda assign
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Pilih dari tim Anda di bawah untuk mulai berbagi
                            penanganan customer.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Candidate pool -->
            <section
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="border-b border-border px-6 py-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Tambah dari tim Anda
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Hanya CS &amp; Maintenance dari tim Anda yang bisa
                        ditugaskan.
                    </p>
                </div>

                <div
                    v-if="!props.hasTeam"
                    class="flex items-start gap-2.5 px-6 py-5 text-sm text-muted-foreground"
                >
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0 text-amber-600"
                    />
                    <span>
                        Anda belum tergabung dalam tim, jadi belum ada kandidat
                        yang bisa ditugaskan. Hubungi manager Anda.
                    </span>
                </div>

                <div
                    v-else-if="!props.candidates.length"
                    class="px-6 py-8 text-center text-sm text-muted-foreground"
                >
                    Semua CS &amp; Maintenance di tim Anda sudah ditugaskan.
                </div>

                <form v-else @submit.prevent="submit">
                    <ul class="divide-y divide-border">
                        <li
                            v-for="c in props.candidates"
                            :key="c.id"
                            class="transition-colors hover:bg-accent/50"
                        >
                            <label
                                class="flex cursor-pointer items-center gap-3 px-6 py-4"
                            >
                                <input
                                    v-model="form.assignee_ids"
                                    type="checkbox"
                                    :value="c.id"
                                    class="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring"
                                />
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold text-muted-foreground"
                                >
                                    {{ initial(c.name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-foreground">
                                        {{ c.name }}
                                    </div>
                                    <div
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ c.email }}
                                    </div>
                                </div>
                                <span
                                    v-if="c.type"
                                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                    :class="typeBadge(c.type.value)"
                                    >{{ c.type.label }}</span
                                >
                            </label>
                        </li>
                    </ul>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-muted/30 px-6 py-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ selectedCount }} dipilih
                        </p>
                        <Button
                            type="submit"
                            :disabled="!selectedCount || form.processing"
                        >
                            <Plus />
                            Assign terpilih
                        </Button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
