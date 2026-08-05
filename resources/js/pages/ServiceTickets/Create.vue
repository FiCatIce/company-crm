<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { computed, watch } from 'vue';
import ServiceTicketController from '@/actions/App/Http/Controllers/ServiceTicketController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { setBreadcrumbs } from '@/composables/useBreadcrumbs';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/crm';

type TransactionOption = SelectOption & { under_warranty: boolean };

const props = defineProps<{
    customers: SelectOption[];
    transactions: TransactionOption[];
    pickedCustomerId: string;
    priorities: SelectOption[];
    coverages: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Servis', href: ServiceTicketController.index() },
    { title: 'Buat Tiket', href: ServiceTicketController.create() },
];

const form = useForm({
    customer_id: props.pickedCustomerId,
    transaction_id: '',
    item_description: '',
    serial_no: '',
    complaint: '',
    priority: 'normal',
    // '' = let the server suggest from the warranty window (D4).
    coverage: '',
});

// Picking a customer reloads ONLY the anchor options (partial reload) — the
// server never offers another book's purchases.
watch(
    () => form.customer_id,
    (customerId) => {
        form.transaction_id = '';
        router.get(
            ServiceTicketController.create.url(),
            customerId ? { customer_id: customerId } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['transactions', 'pickedCustomerId'],
            },
        );
    },
);

const pickedTransaction = computed(
    () =>
        props.transactions.find((t) => t.value === form.transaction_id) ?? null,
);

const submit = () =>
    form
        .transform((data) => ({
            ...data,
            transaction_id: data.transaction_id || null,
            item_description: data.item_description || null,
            serial_no: data.serial_no || null,
            coverage: data.coverage || null,
        }))
        .post(ServiceTicketController.store.url());

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const textareaClasses =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

setBreadcrumbs(() => breadcrumbs);
</script>

<template>
    <Head title="Buat Tiket Servis" />
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                Buat Tiket Servis
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Catat barang masuk servis. Tiket masuk ke pool sampai diklaim
                atau ditugaskan.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Customer -->
            <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground"
                    >Customer</label
                >
                <select
                    v-model="form.customer_id"
                    :class="selectClasses"
                    required
                >
                    <option value="" disabled>Pilih customer…</option>
                    <option
                        v-for="c in customers"
                        :key="c.value"
                        :value="c.value"
                    >
                        {{ c.label }}
                    </option>
                </select>
                <p v-if="form.errors.customer_id" class="text-sm text-red-600">
                    {{ form.errors.customer_id }}
                </p>
            </div>

            <!-- Unit under repair -->
            <div class="space-y-4 rounded-lg border border-border p-4">
                <p class="text-sm font-medium text-foreground">
                    Barang yang diservis
                </p>

                <div class="grid gap-2">
                    <label class="text-sm text-muted-foreground"
                        >Dari pembelian tercatat</label
                    >
                    <select
                        v-model="form.transaction_id"
                        :class="selectClasses"
                        :disabled="!form.customer_id"
                    >
                        <option value="">
                            — tanpa transaksi (isi manual di bawah) —
                        </option>
                        <option
                            v-for="t in transactions"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                    <p
                        v-if="pickedTransaction"
                        class="flex items-center gap-1.5 text-xs"
                        :class="
                            pickedTransaction.under_warranty
                                ? 'text-green-700'
                                : 'text-amber-700'
                        "
                    >
                        <ShieldCheck class="size-3.5" />
                        {{
                            pickedTransaction.under_warranty
                                ? 'Masih dalam garansi — coverage otomatis: Garansi'
                                : 'Garansi sudah habis — coverage otomatis: Berbayar'
                        }}
                    </p>
                    <p
                        v-if="form.errors.transaction_id"
                        class="text-sm text-red-600"
                    >
                        {{ form.errors.transaction_id }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm text-muted-foreground"
                            >Deskripsi barang (manual)</label
                        >
                        <Input
                            v-model="form.item_description"
                            placeholder="cth. TV LED 43 inci merk X"
                        />
                        <p
                            v-if="form.errors.item_description"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.item_description }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm text-muted-foreground"
                            >Nomor seri</label
                        >
                        <Input
                            v-model="form.serial_no"
                            placeholder="opsional"
                        />
                    </div>
                </div>
            </div>

            <!-- Complaint -->
            <div class="grid gap-2">
                <label class="text-sm font-medium text-foreground"
                    >Keluhan</label
                >
                <textarea
                    v-model="form.complaint"
                    rows="4"
                    :class="textareaClasses"
                    placeholder="Apa yang dilaporkan customer…"
                    required
                ></textarea>
                <p v-if="form.errors.complaint" class="text-sm text-red-600">
                    {{ form.errors.complaint }}
                </p>
            </div>

            <!-- Priority + coverage -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-foreground"
                        >Prioritas</label
                    >
                    <select v-model="form.priority" :class="selectClasses">
                        <option
                            v-for="p in priorities"
                            :key="p.value"
                            :value="p.value"
                        >
                            {{ p.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-foreground"
                        >Coverage</label
                    >
                    <select v-model="form.coverage" :class="selectClasses">
                        <option value="">
                            Otomatis dari garansi transaksi
                        </option>
                        <option
                            v-for="c in coverages"
                            :key="c.value"
                            :value="c.value"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Kosongkan untuk mengikuti jendela garansi anchor
                        transaksi.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Buat Tiket</Button
                >
                <Button as-child variant="ghost" type="button">
                    <Link :href="ServiceTicketController.index()">Batal</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
