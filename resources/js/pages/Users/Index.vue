<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Check, Plus, UserCog } from '@lucide/vue';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
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
import type { UserRow } from '@/types/crm';

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

defineProps<{
    users: Paginated<UserRow>;
    can: { create: boolean; update: boolean };
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengguna', href: UserController.index() },
];

const initial = (name: string) => name.trim().charAt(0).toUpperCase();
</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="space-y-1">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        Manajemen Pengguna
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Kelola akun staff, role, dan izin akses.
                    </p>
                </div>

                <Button v-if="can.create" as-child>
                    <Link :href="UserController.create()">
                        <Plus />
                        Tambah Pengguna
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
                                    Nama
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Role
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Extension
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
                                v-for="u in users.data"
                                :key="u.id"
                                class="transition-colors hover:bg-accent/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                        >
                                            {{ initial(u.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div
                                                class="flex items-center gap-2 font-medium text-foreground"
                                            >
                                                {{ u.name }}
                                                <span
                                                    v-if="u.is_self"
                                                    class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                                    >Anda</span
                                                >
                                            </div>
                                            <div
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ u.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <span
                                            v-if="u.role"
                                            class="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground"
                                            >{{ u.role.label }}</span
                                        >
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                        <span
                                            v-if="!u.is_active"
                                            class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                                            >Nonaktif</span
                                        >
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-muted-foreground tabular-nums"
                                >
                                    {{ u.extension ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            v-if="can.update"
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link
                                                :href="
                                                    UserController.edit(u.id)
                                                "
                                                >Edit</Link
                                            >
                                        </Button>

                                        <Dialog v-if="u.can_set_status">
                                            <DialogTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    :class="
                                                        u.is_active
                                                            ? 'text-muted-foreground'
                                                            : 'text-emerald-700'
                                                    "
                                                >
                                                    {{
                                                        u.is_active
                                                            ? 'Nonaktifkan'
                                                            : 'Aktifkan'
                                                    }}
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <Form
                                                    v-bind="
                                                        UserController.updateStatus.form(
                                                            u.id,
                                                        )
                                                    "
                                                    :options="{
                                                        preserveScroll: true,
                                                    }"
                                                    class="space-y-6"
                                                    v-slot="{ processing }"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="is_active"
                                                        :value="
                                                            u.is_active ? 0 : 1
                                                        "
                                                    />
                                                    <DialogHeader
                                                        class="space-y-3"
                                                    >
                                                        <DialogTitle>{{
                                                            u.is_active
                                                                ? 'Nonaktifkan akun?'
                                                                : 'Aktifkan kembali akun?'
                                                        }}</DialogTitle>
                                                        <DialogDescription
                                                            v-if="u.is_active"
                                                        >
                                                            <strong>{{
                                                                u.name
                                                            }}</strong>
                                                            tidak akan bisa
                                                            login lagi dan
                                                            sesinya langsung
                                                            berakhir.
                                                            <strong
                                                                >Data dan
                                                                penugasannya
                                                                tetap
                                                                utuh</strong
                                                            >, dan akun bisa
                                                            diaktifkan kembali
                                                            kapan saja.
                                                        </DialogDescription>
                                                        <DialogDescription
                                                            v-else
                                                        >
                                                            <strong>{{
                                                                u.name
                                                            }}</strong>
                                                            bisa login lagi
                                                            dengan akses yang
                                                            sama seperti
                                                            sebelumnya.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <DialogFooter class="gap-2">
                                                        <DialogClose as-child>
                                                            <Button
                                                                variant="secondary"
                                                                type="button"
                                                            >
                                                                Batal
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            :variant="
                                                                u.is_active
                                                                    ? 'destructive'
                                                                    : 'default'
                                                            "
                                                            :disabled="
                                                                processing
                                                            "
                                                        >
                                                            {{
                                                                u.is_active
                                                                    ? 'Nonaktifkan'
                                                                    : 'Aktifkan'
                                                            }}
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>

                                        <Button
                                            v-if="u.can_offboard && u.is_active"
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground"
                                        >
                                            <Link
                                                :href="
                                                    UserController.showOffboard(
                                                        u.id,
                                                    )
                                                "
                                                >Offboard</Link
                                            >
                                        </Button>

                                        <Dialog v-if="u.can_delete">
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
                                                <Form
                                                    v-bind="
                                                        UserController.destroy.form(
                                                            u.id,
                                                        )
                                                    "
                                                    :options="{
                                                        preserveScroll: true,
                                                    }"
                                                    class="space-y-6"
                                                    v-slot="{ processing }"
                                                >
                                                    <DialogHeader
                                                        class="space-y-3"
                                                    >
                                                        <DialogTitle
                                                            >Hapus
                                                            pengguna?</DialogTitle
                                                        >
                                                        <DialogDescription>
                                                            Akun
                                                            <strong>{{
                                                                u.name
                                                            }}</strong>
                                                            akan dihapus
                                                            permanen.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <DialogFooter class="gap-2">
                                                        <DialogClose as-child>
                                                            <Button
                                                                variant="secondary"
                                                                type="button"
                                                            >
                                                                Batal
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            variant="destructive"
                                                            :disabled="
                                                                processing
                                                            "
                                                        >
                                                            Hapus
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="users.data.length === 0">
                                <td colspan="4" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex max-w-sm flex-col items-center gap-2"
                                    >
                                        <div
                                            class="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                        >
                                            <UserCog class="size-5" />
                                        </div>
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            Belum ada pengguna
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="users.total > 0"
                    class="flex flex-wrap items-center justify-between gap-4 border-t border-border p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Menampilkan {{ users.from ?? 0 }}–{{
                            users.to ?? 0
                        }}
                        dari {{ users.total }}
                    </p>
                    <div
                        v-if="users.links.length > 3"
                        class="flex flex-wrap items-center gap-1"
                    >
                        <template v-for="(link, i) in users.links" :key="i">
                            <span
                                v-if="!link.url"
                                class="px-3 py-1.5 text-sm text-muted-foreground"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="rounded-md px-3 py-1.5 text-sm transition-colors"
                                :class="
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-foreground hover:bg-accent'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
