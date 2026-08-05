<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, CircleAlert, Play, UserCog, Wrench, X } from '@lucide/vue';
import { computed } from 'vue';
import ServiceTicketController from '@/actions/App/Http/Controllers/ServiceTicketController';
import TicketStatusBadge from '@/components/TicketStatusBadge.vue';
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
import { setBreadcrumbs } from '@/composables/useBreadcrumbs';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/crm';

type Ticket = {
    id: number;
    customer: { id: number; name: string } | null;
    item: string | null;
    serial_no: string | null;
    status: { value: string; label: string };
    coverage: { value: string; label: string };
    priority: { value: string; label: string };
    assignee: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
    received_at: string;
    started_at: string | null;
    finished_at: string | null;
    cancelled_at: string | null;
    complaint: string;
    work_notes: string | null;
    resolution: string | null;
};

const props = defineProps<{
    ticket: Ticket;
    can: { update: boolean; claim: boolean; assign: boolean };
    assignableTechnicians: SelectOption[];
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Servis', href: ServiceTicketController.index() },
    {
        title: `#${props.ticket.id}`,
        href: ServiceTicketController.show(props.ticket.id),
    },
];

const status = computed(() => props.ticket.status.value);
const isActive = computed(
    () => status.value === 'received' || status.value === 'in_progress',
);

// Contextual actions (M3): a button the viewer may not use is NOT rendered.
// `can.*` carries the policy verdict; status narrows it to the legal moves.
const showClaim = computed(
    () => props.can.claim && status.value === 'received',
);
const showStart = computed(
    () =>
        props.can.update &&
        status.value === 'received' &&
        props.ticket.assignee !== null,
);
const showComplete = computed(
    () => props.can.update && status.value === 'in_progress',
);
const showCancel = computed(() => props.can.update && isActive.value);
const showAssign = computed(
    () =>
        props.can.assign &&
        isActive.value &&
        props.assignableTechnicians.length > 0,
);

const claimForm = useForm({});
const startForm = useForm({});
const completeForm = useForm({ resolution: '' });
const cancelForm = useForm({ reason: '' });
const assignForm = useForm({ assignee_id: '' });
const notesForm = useForm({ work_notes: props.ticket.work_notes ?? '' });

const submitClaim = () =>
    claimForm.post(ServiceTicketController.claim.url(props.ticket.id), {
        preserveScroll: true,
    });
const submitStart = () =>
    startForm.post(ServiceTicketController.start.url(props.ticket.id), {
        preserveScroll: true,
    });
const submitComplete = () =>
    completeForm.post(ServiceTicketController.complete.url(props.ticket.id), {
        preserveScroll: true,
    });
const submitCancel = () =>
    cancelForm.post(ServiceTicketController.cancel.url(props.ticket.id), {
        preserveScroll: true,
    });
const submitAssign = () =>
    assignForm.post(ServiceTicketController.assign.url(props.ticket.id), {
        preserveScroll: true,
    });
const submitNotes = () =>
    notesForm.put(ServiceTicketController.update.url(props.ticket.id), {
        preserveScroll: true,
    });

const dateFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const formatDate = (value: string | null) =>
    value ? dateFormat.format(new Date(value)) : null;

// The lifecycle rail, from the stamps — only what actually happened renders.
const timeline = computed(() => {
    const steps: { label: string; at: string | null; done: boolean }[] = [
        { label: 'Diterima', at: props.ticket.received_at, done: true },
        {
            label: 'Dikerjakan',
            at: props.ticket.started_at,
            done: props.ticket.started_at !== null,
        },
    ];

    if (status.value === 'cancelled') {
        steps.push({
            label: 'Dibatalkan',
            at: props.ticket.cancelled_at,
            done: true,
        });
    } else {
        steps.push({
            label: 'Selesai',
            at: props.ticket.finished_at,
            done: props.ticket.finished_at !== null,
        });
    }

    return steps;
});

const selectClasses =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const textareaClasses =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

setBreadcrumbs(() => breadcrumbs);
</script>

<template>
    <Head :title="`Tiket #${ticket.id}`" />
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Tiket #{{ ticket.id }}
                    </h1>
                    <TicketStatusBadge
                        :status="ticket.status.value"
                        :label="ticket.status.label"
                    />
                </div>
                <p class="text-sm text-muted-foreground">
                    <template v-if="ticket.customer">
                        <Link
                            :href="`/customers/${ticket.customer.id}`"
                            class="font-medium text-foreground hover:underline"
                            >{{ ticket.customer.name }}</Link
                        >
                        ·
                    </template>
                    {{ ticket.item ?? 'Barang tanpa katalog' }}
                    <template v-if="ticket.serial_no">
                        · SN {{ ticket.serial_no }}</template
                    >
                </p>
                <div
                    class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <span
                        class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 font-medium"
                        >{{ ticket.coverage.label }}</span
                    >
                    <span
                        class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 font-medium"
                        >Prioritas {{ ticket.priority.label }}</span
                    >
                    <span v-if="ticket.creator"
                        >Diinput {{ ticket.creator.name }}</span
                    >
                </div>
            </div>

            <!-- Contextual transitions -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="showClaim"
                    :disabled="claimForm.processing"
                    @click="submitClaim"
                >
                    <Wrench />
                    Klaim
                </Button>

                <Button
                    v-if="showStart"
                    :disabled="startForm.processing"
                    @click="submitStart"
                >
                    <Play />
                    Mulai Kerjakan
                </Button>

                <Dialog v-if="showComplete">
                    <DialogTrigger as-child>
                        <Button>
                            <Check />
                            Selesaikan
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Selesaikan tiket?</DialogTitle>
                            <DialogDescription>
                                Ringkasan penyelesaian wajib diisi — inilah
                                catatan yang dibaca semua orang setelah tiket
                                ditutup.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-2">
                            <textarea
                                v-model="completeForm.resolution"
                                rows="4"
                                :class="textareaClasses"
                                placeholder="Apa yang dikerjakan dan hasilnya…"
                            ></textarea>
                            <p
                                v-if="completeForm.errors.resolution"
                                class="text-sm text-red-600"
                            >
                                {{ completeForm.errors.resolution }}
                            </p>
                        </div>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" type="button"
                                    >Batal</Button
                                >
                            </DialogClose>
                            <Button
                                :disabled="completeForm.processing"
                                @click="submitComplete"
                            >
                                Tandai Selesai
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog v-if="showCancel">
                    <DialogTrigger as-child>
                        <Button variant="outline">
                            <X />
                            Batalkan
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Batalkan tiket?</DialogTitle>
                            <DialogDescription>
                                Tiket batal bersifat final — barang balik lagi
                                berarti tiket baru.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-2">
                            <textarea
                                v-model="cancelForm.reason"
                                rows="3"
                                :class="textareaClasses"
                                placeholder="Alasan pembatalan…"
                            ></textarea>
                            <p
                                v-if="cancelForm.errors.reason"
                                class="text-sm text-red-600"
                            >
                                {{ cancelForm.errors.reason }}
                            </p>
                        </div>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" type="button"
                                    >Kembali</Button
                                >
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="cancelForm.processing"
                                @click="submitCancel"
                            >
                                Batalkan Tiket
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Flash -->
        <div
            v-if="flashSuccess"
            class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            <Check class="size-4 shrink-0" />
            <span>{{ flashSuccess }}</span>
        </div>
        <div
            v-if="flashError"
            class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            <CircleAlert class="size-4 shrink-0" />
            <span>{{ flashError }}</span>
        </div>

        <!-- Lifecycle rail -->
        <div
            class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5"
        >
            <ol class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <li
                    v-for="(step, i) in timeline"
                    :key="i"
                    class="flex items-center gap-2 text-sm"
                >
                    <span
                        class="flex size-5 items-center justify-center rounded-full"
                        :class="
                            step.done
                                ? step.label === 'Dibatalkan'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-green-100 text-green-700'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <X v-if="step.label === 'Dibatalkan'" class="size-3" />
                        <Check v-else class="size-3" />
                    </span>
                    <span
                        :class="
                            step.done
                                ? 'font-medium text-foreground'
                                : 'text-muted-foreground'
                        "
                        >{{ step.label }}</span
                    >
                    <span
                        v-if="step.at && step.done"
                        class="text-xs text-muted-foreground"
                        >{{ formatDate(step.at) }}</span
                    >
                </li>
            </ol>
            <p class="mt-3 text-sm text-muted-foreground">
                Teknisi:
                <span
                    v-if="ticket.assignee"
                    class="font-medium text-foreground"
                    >{{ ticket.assignee.name }}</span
                >
                <span
                    v-else
                    class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"
                    >Pool — belum dipegang</span
                >
            </p>

            <!-- Routing (supervisor) -->
            <form
                v-if="showAssign"
                class="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4"
                @submit.prevent="submitAssign"
            >
                <UserCog class="size-4 text-muted-foreground" />
                <select
                    v-model="assignForm.assignee_id"
                    :class="selectClasses"
                    required
                >
                    <option value="" disabled>Tugaskan ke…</option>
                    <option
                        v-for="t in assignableTechnicians"
                        :key="t.value"
                        :value="t.value"
                    >
                        {{ t.label }}
                    </option>
                </select>
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    :disabled="assignForm.processing || !assignForm.assignee_id"
                >
                    Tugaskan
                </Button>
                <p
                    v-if="assignForm.errors.assignee_id"
                    class="w-full text-sm text-red-600"
                >
                    {{ assignForm.errors.assignee_id }}
                </p>
            </form>
        </div>

        <!-- Complaint -->
        <div
            class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5"
        >
            <h2 class="text-sm font-semibold text-foreground">Keluhan</h2>
            <p class="mt-2 text-sm whitespace-pre-line text-foreground">
                {{ ticket.complaint }}
            </p>
        </div>

        <!-- Resolution (done only) -->
        <div
            v-if="ticket.resolution"
            class="rounded-xl border border-green-200 bg-green-50/50 p-4 shadow-sm sm:p-5"
        >
            <h2 class="text-sm font-semibold text-green-800">Penyelesaian</h2>
            <p class="mt-2 text-sm whitespace-pre-line text-green-900">
                {{ ticket.resolution }}
            </p>
        </div>

        <!-- Work notes -->
        <div
            class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5"
        >
            <h2 class="text-sm font-semibold text-foreground">
                Catatan Pengerjaan
            </h2>

            <form
                v-if="can.update"
                class="mt-2 space-y-2"
                @submit.prevent="submitNotes"
            >
                <textarea
                    v-model="notesForm.work_notes"
                    rows="4"
                    :class="textareaClasses"
                    placeholder="Progres, temuan, sparepart…"
                ></textarea>
                <p
                    v-if="notesForm.errors.work_notes"
                    class="text-sm text-red-600"
                >
                    {{ notesForm.errors.work_notes }}
                </p>
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    :disabled="notesForm.processing"
                >
                    Simpan Catatan
                </Button>
            </form>

            <p
                v-else
                class="mt-2 text-sm whitespace-pre-line"
                :class="
                    ticket.work_notes
                        ? 'text-foreground'
                        : 'text-muted-foreground'
                "
            >
                {{ ticket.work_notes ?? 'Belum ada catatan.' }}
            </p>
        </div>
    </div>
</template>
