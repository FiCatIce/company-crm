<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { RoleOption } from '@/types/crm';

defineProps<{
    roles: RoleOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengguna', href: UserController.index() },
    { title: 'Tambah', href: UserController.create() },
];
</script>

<template>
    <Head title="Tambah Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Tambah Pengguna
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Buat akun staff dan tetapkan rolenya. Izin mengikuti preset
                    role — atur per pengguna nanti lewat Edit.
                </p>
            </div>

            <Form
                v-bind="UserController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nama</Label>
                    <Input id="name" name="name" required autocomplete="off" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autocomplete="off"
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

                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <select
                        id="role"
                        name="role"
                        required
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="" disabled selected>Pilih role</option>
                        <option v-for="r in roles" :key="r.value" :value="r.value">
                            {{ r.label }}
                        </option>
                    </select>
                    <InputError :message="errors.role" />
                </div>

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
                    <Label for="password_confirmation">Konfirmasi password</Label>
                    <Input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing">Simpan</Button>
                    <Button as-child variant="ghost" type="button">
                        <Link :href="UserController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
