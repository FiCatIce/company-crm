<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, TriangleAlert, UserMinus } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { OffboardHoldings, OffboardUser } from '@/types/crm';

const props = defineProps<{
    user: OffboardUser;
    holdings: OffboardHoldings;
    successors: OffboardUser[];
    submitUrl: string;
    cancelUrl: string;
}>();

const form = useForm({ successor_id: null as number | null });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Offboard', href: props.cancelUrl },
];

// Only the non-zero holdings are worth showing — a wall of zeroes buries the
// one number that actually matters.
const heldItems = computed(() =>
    [
        { label: 'Customer yang ditangani', value: props.holdings.customers },
        { label: 'Support yang ditugaskan', value: props.holdings.assignees },
        { label: 'Sales yang dilayani', value: props.holdings.reps },
        { label: 'Tim yang dipimpin', value: props.holdings.teams_led },
    ].filter((item) => item.value > 0),
);

const holdsNothing = computed(() => heldItems.value.length === 0);

const chosen = computed(
    () => props.successors.find((s) => s.id === form.successor_id) ?? null,
);

const submit = () => form.post(props.submitUrl);
</script>

<template>
    <Head :title="`Offboard ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"
                >
                    <UserMinus class="size-5" />
                </div>
                <div class="space-y-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Offboard {{ user.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Semua tanggung jawab dialihkan ke satu pengganti, lalu
                        akses ditutup. Akun tidak dihapus — riwayatnya tetap
                        terbaca.
                    </p>
                </div>
            </div>

            <!-- 1. What they hold -->
            <section
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="border-b border-border px-6 py-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Yang masih dipegang
                    </h2>
                </div>

                <ul v-if="!holdsNothing" class="divide-y divide-border">
                    <li
                        v-for="item in heldItems"
                        :key="item.label"
                        class="flex items-center justify-between px-6 py-3 text-sm"
                    >
                        <span class="text-muted-foreground">{{
                            item.label
                        }}</span>
                        <span
                            class="font-semibold text-foreground tabular-nums"
                            >{{ item.value }}</span
                        >
                    </li>
                </ul>

                <p v-else class="px-6 py-6 text-sm text-muted-foreground">
                    Tidak memegang apa pun. Offboard tetap bisa dijalankan untuk
                    menutup akses.
                </p>
            </section>

            <!-- 2. Pick the successor -->
            <form
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                @submit.prevent="submit"
            >
                <div class="border-b border-border px-6 py-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Pengganti
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Harus aktif, berperan sama, dan satu tim (atau belum
                        punya tim).
                    </p>
                </div>

                <div v-if="successors.length" class="space-y-4 p-6">
                    <div class="grid gap-2">
                        <Label for="successor">Pilih pengganti</Label>
                        <select
                            id="successor"
                            v-model="form.successor_id"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            required
                        >
                            <option :value="null" disabled>
                                — pilih pengganti —
                            </option>
                            <option
                                v-for="s in successors"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }} ({{ s.email }})
                            </option>
                        </select>
                        <InputError :message="form.errors.successor_id" />
                    </div>

                    <!-- 3. Confirm: spell out the move before it happens -->
                    <div
                        v-if="chosen"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        <div class="mb-2 flex items-center gap-2 font-medium">
                            <TriangleAlert class="size-4 shrink-0" />
                            Konfirmasi pengalihan
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ user.name }}</span>
                            <ArrowRight class="size-4" />
                            <span class="font-medium">{{ chosen.name }}</span>
                        </div>
                        <ul class="mt-2 list-inside list-disc space-y-0.5">
                            <li v-for="item in heldItems" :key="item.label">
                                {{ item.value }} {{ item.label.toLowerCase() }}
                                dialihkan
                            </li>
                            <li>
                                Kolom <strong>dibuat oleh</strong> tidak berubah
                                — atribusi historis tetap
                            </li>
                            <li>
                                Tim tetap hidup; anggota lain tidak dipindahkan
                            </li>
                            <li>
                                Akun {{ user.name }} dinonaktifkan (tidak
                                dihapus)
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-else class="p-6">
                    <div
                        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        Belum ada kandidat pengganti yang memenuhi syarat.
                        Tambahkan atau aktifkan anggota dengan peran yang sama
                        di tim ini terlebih dahulu.
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-6 py-4"
                >
                    <Button as-child variant="secondary" type="button">
                        <Link :href="cancelUrl">Batal</Link>
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="
                            form.processing ||
                            !successors.length ||
                            !form.successor_id
                        "
                    >
                        Alihkan &amp; offboard
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
