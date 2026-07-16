<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Plus, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
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
import type { RoleRow } from '@/types/crm';

defineProps<{ roles: RoleRow[] }>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Role', href: '/roles' }];

function destroy(role: RoleRow): void {
    router.delete(`/roles/${role.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Role & Izin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="space-y-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Role &amp; Izin
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Buat role kustom dan atur izinnya. Role sistem terkunci.
                    </p>
                </div>

                <Button as-child>
                    <Link href="/roles/create">
                        <Plus />
                        Buat Role
                    </Link>
                </Button>
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

            <div
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-border">
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Role
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Tipe
                                </th>
                                <th
                                    class="px-6 py-3.5 text-right text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Izin
                                </th>
                                <th
                                    class="px-6 py-3.5 text-right text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    User
                                </th>
                                <th
                                    class="px-6 py-3.5 text-right text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="role in roles"
                                :key="role.id"
                                class="transition-colors hover:bg-accent/50"
                            >
                                <td
                                    class="px-6 py-4 font-medium text-foreground"
                                >
                                    {{ role.label }}
                                    <span
                                        class="ml-1 text-xs font-normal text-muted-foreground"
                                        >{{ role.name }}</span
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        v-if="role.is_system"
                                        class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"
                                        >Sistem</span
                                    >
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground"
                                        >Kustom</span
                                    >
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-muted-foreground tabular-nums"
                                >
                                    {{ role.permissions_count }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-muted-foreground tabular-nums"
                                >
                                    {{ role.users_count }}
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link
                                                :href="`/roles/${role.id}/edit`"
                                                >{{
                                                    role.is_system
                                                        ? 'Lihat'
                                                        : 'Edit'
                                                }}</Link
                                            >
                                        </Button>

                                        <Dialog v-if="!role.is_system">
                                            <DialogTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    Hapus
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogHeader class="space-y-3">
                                                    <DialogTitle
                                                        >Hapus
                                                        role?</DialogTitle
                                                    >
                                                    <DialogDescription>
                                                        Role
                                                        <strong>{{
                                                            role.label
                                                        }}</strong>
                                                        akan dihapus permanen.
                                                        <template
                                                            v-if="
                                                                role.users_count >
                                                                0
                                                            "
                                                        >
                                                            Masih dipakai
                                                            {{
                                                                role.users_count
                                                            }}
                                                            user — pindahkan
                                                            dulu.
                                                        </template>
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-2">
                                                    <DialogClose as-child>
                                                        <Button
                                                            variant="secondary"
                                                            type="button"
                                                            >Batal</Button
                                                        >
                                                    </DialogClose>
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            @click="
                                                                destroy(role)
                                                            "
                                                            >Hapus</Button
                                                        >
                                                    </DialogClose>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="roles.length === 0">
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex max-w-sm flex-col items-center gap-2"
                                    >
                                        <div
                                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                        >
                                            <ShieldCheck class="size-5" />
                                        </div>
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            Belum ada role
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
