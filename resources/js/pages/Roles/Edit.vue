<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PermissionChecklist from '@/components/PermissionChecklist.vue';
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
        is_locked: boolean;
        permissions: string[];
    };
    permissionGroups: PermissionGroup[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Role', href: '/roles' },
    { title: props.role.label, href: `/roles/${props.role.id}/edit` },
];

// Only the admin role is fully locked (read-only). The other system roles are
// editable, but saving them is confirmed first because seeders & integrations
// reference their default slug.
const needsConfirm = computed(
    () => props.role.is_system && !props.role.is_locked,
);

const subtitle = computed(() => {
    if (props.role.is_locked) {
        return 'Role admin terkunci — izinnya baku dan hanya bisa dilihat.';
    }

    if (props.role.is_system) {
        return 'Role sistem — bisa diubah, tapi namanya dipakai seeder & integrasi CTI.';
    }

    return 'Ubah nama role dan izinnya.';
});

const form = useForm<{ name: string; permissions: string[] }>({
    name: props.role.name,
    permissions: [...props.role.permissions],
});

function doSubmit(): void {
    if (props.role.is_locked) {
        return;
    }

    form.put(`/roles/${props.role.id}`);
}

// Native/Enter submit: a custom role saves directly; a system role is routed
// through the confirm dialog instead; the locked admin role never submits.
function onFormSubmit(): void {
    if (needsConfirm.value) {
        return;
    }

    doSubmit();
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
                    {{ role.is_locked ? role.label : 'Edit Role' }}
                </h1>
                <p class="text-sm text-muted-foreground">{{ subtitle }}</p>
            </div>

            <!-- Admin is locked: its preset is code-defined (RolePresets). -->
            <div
                v-if="role.is_locked"
                class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700"
            >
                <ShieldCheck class="size-4 shrink-0" />
                <span
                    >Role admin terkunci total demi mencegah eskalasi hak akses
                    dan lockout. Izinnya hanya bisa dilihat.</span
                >
            </div>

            <!-- Non-admin system role: editable, but warn before saving. -->
            <div
                v-else-if="role.is_system"
                class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                <span>
                    Ini role sistem (<strong>{{ role.name }}</strong
                    >). Mengubah nama atau izinnya bisa memengaruhi seeder &amp;
                    integrasi CTI, dan perubahan izin langsung diterapkan ke
                    semua anggota role ini.
                </span>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="onFormSubmit">
                <div class="rounded-xl border border-border bg-card p-4">
                    <Label for="name">Nama Role</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1.5"
                        :disabled="role.is_locked"
                    />
                    <InputError :message="form.errors.name" class="mt-1.5" />
                </div>

                <div class="space-y-2">
                    <h2 class="text-sm font-semibold text-foreground">Izin</h2>
                    <PermissionChecklist
                        v-model="form.permissions"
                        :groups="permissionGroups"
                        :disabled="role.is_locked"
                    />
                    <InputError :message="form.errors.permissions" />
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Button as-child variant="secondary" type="button">
                        <Link href="/roles">{{
                            role.is_locked ? 'Kembali' : 'Batal'
                        }}</Link>
                    </Button>

                    <!-- Custom role: save directly. -->
                    <Button
                        v-if="!role.is_locked && !role.is_system"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Simpan
                    </Button>

                    <!-- System role (non-admin): confirm before saving. -->
                    <Dialog v-else-if="needsConfirm">
                        <DialogTrigger as-child>
                            <Button type="button" :disabled="form.processing">
                                Simpan
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader class="space-y-3">
                                <DialogTitle
                                    >Simpan perubahan role sistem?</DialogTitle
                                >
                                <DialogDescription>
                                    <strong>{{ role.label }}</strong> adalah
                                    role sistem — seeder &amp; integrasi CTI
                                    mungkin mengandalkan nama
                                    <strong>{{ role.name }}</strong
                                    >. Perubahan izin langsung diterapkan ke
                                    semua anggota role ini. Yakin?
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary" type="button"
                                        >Batal</Button
                                    >
                                </DialogClose>
                                <DialogClose as-child>
                                    <Button type="button" @click="doSubmit"
                                        >Ya, simpan</Button
                                    >
                                </DialogClose>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
