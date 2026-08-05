<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Lock, Plus, ShieldCheck } from '@lucide/vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { setBreadcrumbs } from '@/composables/useBreadcrumbs';
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

setBreadcrumbs(() => breadcrumbs);
</script>

<template>
    <Head title="Role & Izin" />
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Role &amp; Izin
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola role beserta izinnya. Hanya role admin yang terkunci;
                    role sistem lain bisa diubah.
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
            <Table>
                <TableHeader>
                    <TableHead>Role</TableHead>
                    <TableHead>Tipe</TableHead>
                    <TableHead align="right">Izin</TableHead>
                    <TableHead align="right">User</TableHead>
                    <TableHead align="right">Aksi</TableHead>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="role in roles" :key="role.id">
                        <TableCell class="font-medium text-foreground">
                            {{ role.label }}
                            <span
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >{{ role.name }}</span
                            >
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="role.is_locked"
                                class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700"
                            >
                                <Lock class="size-3" />
                                Terkunci
                            </span>
                            <span
                                v-else-if="role.is_system"
                                class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"
                                >Sistem</span
                            >
                            <span
                                v-else
                                class="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground"
                                >Kustom</span
                            >
                        </TableCell>
                        <TableCell kind="money" class="text-muted-foreground">
                            {{ role.permissions_count }}
                        </TableCell>
                        <TableCell kind="money" class="text-muted-foreground">
                            {{ role.users_count }}
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-1">
                                <Button as-child variant="ghost" size="sm">
                                    <Link :href="`/roles/${role.id}/edit`">{{
                                        role.is_locked ? 'Lihat' : 'Edit'
                                    }}</Link>
                                </Button>

                                <Dialog v-if="!role.is_locked">
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
                                                >Hapus role?</DialogTitle
                                            >
                                            <DialogDescription>
                                                Role
                                                <strong>{{
                                                    role.label
                                                }}</strong>
                                                akan dihapus permanen.
                                                <template
                                                    v-if="role.users_count > 0"
                                                >
                                                    Masih dipakai
                                                    {{ role.users_count }}
                                                    user — pindahkan dulu ke
                                                    role lain.
                                                </template>
                                                <span
                                                    v-if="role.is_system"
                                                    class="mt-2 block text-amber-700"
                                                >
                                                    Ini role sistem — seeder
                                                    &amp; integrasi CTI mungkin
                                                    mengandalkan nama
                                                    <strong>{{
                                                        role.name
                                                    }}</strong
                                                    >. Yakin?
                                                </span>
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
                                                    @click="destroy(role)"
                                                    >Hapus</Button
                                                >
                                            </DialogClose>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableEmpty v-if="roles.length === 0" :colspan="5">
                        <div
                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <ShieldCheck class="size-5" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Belum ada role
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
