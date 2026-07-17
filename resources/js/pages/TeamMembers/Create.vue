<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ShieldCheck, Users } from '@lucide/vue';
import TeamMemberController from '@/actions/App/Http/Controllers/TeamMemberController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/crm';

defineProps<{
    types: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Anggota Tim', href: TeamMemberController.index() },
    { title: 'Tambah', href: TeamMemberController.create() },
];
</script>

<template>
    <Head title="Tambah Anggota Tim" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div class="flex items-start gap-4">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Users class="size-5" />
                </div>
                <div>
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Tambah Anggota Tim
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Buat akun untuk anggota tim Anda. Izin akses otomatis
                        mengikuti tipe yang dipilih — Anda tidak perlu mengatur
                        satu per satu.
                    </p>
                </div>
            </div>

            <Form
                v-bind="TeamMemberController.store.form()"
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                v-slot="{ errors, processing }"
            >
                <div class="space-y-6 p-6">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Identitas
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Nama dan kontak anggota.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            autocomplete="off"
                            placeholder="Nama lengkap"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autocomplete="off"
                                placeholder="nama@perusahaan.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="extension">Extension (opsional)</Label>
                            <Input
                                id="extension"
                                name="extension"
                                autocomplete="off"
                                placeholder="mis. 1007 (untuk CTI)"
                            />
                            <InputError :message="errors.extension" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">Tipe anggota</Label>
                        <select
                            id="type"
                            name="type"
                            required
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                        >
                            <option value="" disabled selected>
                                Pilih tipe
                            </option>
                            <option
                                v-for="t in types"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>

                    <div
                        class="flex items-start gap-2.5 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-3 text-sm text-blue-800"
                    >
                        <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                        <span>
                            Izin akses ditetapkan otomatis dari preset tipe.
                            Pengaturan izin per pengguna hanya tersedia untuk
                            admin.
                        </span>
                    </div>
                </div>

                <div class="border-t border-border p-6">
                    <div class="mb-6">
                        <h2 class="text-sm font-semibold text-foreground">
                            Kredensial
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Password sementara — minta anggota menggantinya saat
                            login pertama.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="password">Password sementara</Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">
                                Konfirmasi password
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center gap-3 border-t border-border bg-muted/30 px-6 py-4"
                >
                    <Button type="submit" :disabled="processing">
                        Simpan anggota
                    </Button>
                    <Button as-child variant="ghost" type="button">
                        <Link :href="TeamMemberController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
