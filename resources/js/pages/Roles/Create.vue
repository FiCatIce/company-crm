<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PermissionChecklist from '@/components/PermissionChecklist.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { PermissionGroup } from '@/types/crm';

defineProps<{ permissionGroups: PermissionGroup[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Role', href: '/roles' },
    { title: 'Buat Role', href: '/roles/create' },
];

const form = useForm<{ name: string; permissions: string[] }>({
    name: '',
    permissions: [],
});

function submit(): void {
    form.post('/roles');
}
</script>

<template>
    <Head title="Buat Role" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        >
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Buat Role Kustom
                </h1>
                <p class="text-sm text-muted-foreground">
                    Tentukan nama role dan izin yang dimilikinya.
                </p>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="rounded-xl border border-border bg-card p-4">
                    <Label for="name">Nama Role</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="mis. Regional Manager"
                        class="mt-1.5"
                    />
                    <InputError :message="form.errors.name" class="mt-1.5" />
                </div>

                <div class="space-y-2">
                    <h2 class="text-sm font-semibold text-foreground">Izin</h2>
                    <PermissionChecklist
                        v-model="form.permissions"
                        :groups="permissionGroups"
                    />
                    <InputError :message="form.errors.permissions" />
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Button as-child variant="secondary" type="button">
                        <Link href="/roles">Batal</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        Simpan
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
