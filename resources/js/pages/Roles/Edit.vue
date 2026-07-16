<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PermissionChecklist from '@/components/PermissionChecklist.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { PermissionGroup } from '@/types/crm';

const props = defineProps<{
    role: {
        id: number;
        name: string;
        label: string;
        is_system: boolean;
        permissions: string[];
    };
    permissionGroups: PermissionGroup[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Role', href: '/roles' },
    { title: props.role.label, href: `/roles/${props.role.id}/edit` },
];

const form = useForm<{ name: string; permissions: string[] }>({
    name: props.role.name,
    permissions: [...props.role.permissions],
});

function submit(): void {
    if (props.role.is_system) {
        return;
    }

    form.put(`/roles/${props.role.id}`);
}
</script>

<template>
    <Head :title="`Role — ${role.label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        >
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    {{ role.is_system ? role.label : 'Edit Role' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        role.is_system
                            ? 'Role sistem — izinnya baku dan hanya bisa dilihat.'
                            : 'Ubah nama role dan izinnya.'
                    }}
                </p>
            </div>

            <!-- System roles are locked: their preset is code-defined (RolePresets). -->
            <div
                v-if="role.is_system"
                class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700"
            >
                <ShieldCheck class="size-4 shrink-0" />
                <span
                    >Role sistem terkunci. Buat role kustom untuk izin yang bisa
                    diatur sendiri.</span
                >
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="rounded-xl border border-border bg-card p-4">
                    <Label for="name">Nama Role</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1.5"
                        :disabled="role.is_system"
                    />
                    <InputError :message="form.errors.name" class="mt-1.5" />
                </div>

                <div class="space-y-2">
                    <h2 class="text-sm font-semibold text-foreground">Izin</h2>
                    <PermissionChecklist
                        v-model="form.permissions"
                        :groups="permissionGroups"
                        :disabled="role.is_system"
                    />
                    <InputError :message="form.errors.permissions" />
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Button as-child variant="secondary" type="button">
                        <Link href="/roles">{{
                            role.is_system ? 'Kembali' : 'Batal'
                        }}</Link>
                    </Button>
                    <Button
                        v-if="!role.is_system"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Simpan
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
