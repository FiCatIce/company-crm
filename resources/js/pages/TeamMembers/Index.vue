<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    Check,
    KeyRound,
    Plus,
    UserCheck,
    UserMinus,
    UserX,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import TeamMemberController from '@/actions/App/Http/Controllers/TeamMemberController';
import InputError from '@/components/InputError.vue';
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
import type { TeamMemberRow } from '@/types/crm';

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

defineProps<{
    members: Paginated<TeamMemberRow>;
}>();

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Anggota Tim', href: TeamMemberController.index() },
];

const initial = (name: string) => name.trim().charAt(0).toUpperCase();

// Restrained per-type tints — visual hierarchy without leaving the corporate palette.
const typeStyles: Record<string, string> = {
    sales: 'border-blue-200 bg-blue-50 text-blue-700',
    cs: 'border-violet-200 bg-violet-50 text-violet-700',
    maintenance: 'border-amber-200 bg-amber-50 text-amber-700',
};
const typeBadge = (value?: string) =>
    (value && typeStyles[value]) || 'border-border bg-muted text-foreground';

const formatDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';
</script>

<template>
    <Head title="Anggota Tim" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Users class="size-5" />
                    </div>
                    <div class="space-y-1">
                        <h1
                            class="text-2xl font-semibold tracking-tight text-foreground"
                        >
                            Anggota Tim
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Kelola anggota yang Anda bawahi — tambah anggota
                            baru dan reset password mereka.
                        </p>
                    </div>
                </div>

                <Button as-child>
                    <Link :href="TeamMemberController.create()">
                        <Plus />
                        Tambah Anggota
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
                                    Anggota
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Tipe
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Extension
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Bergabung
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
                                v-for="m in members.data"
                                :key="m.id"
                                class="transition-colors hover:bg-accent/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                        >
                                            {{ initial(m.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div
                                                class="font-medium text-foreground"
                                            >
                                                {{ m.name }}
                                            </div>
                                            <div
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ m.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <span
                                            v-if="m.type"
                                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                            :class="typeBadge(m.type.value)"
                                            >{{ m.type.label }}</span
                                        >
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                        <span
                                            v-if="!m.is_active"
                                            class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                                            >Nonaktif</span
                                        >
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-muted-foreground tabular-nums"
                                >
                                    {{ m.extension ?? '—' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-muted-foreground tabular-nums"
                                >
                                    {{ formatDate(m.created_at) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end">
                                        <Dialog v-if="m.can_reset">
                                            <DialogTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <KeyRound />
                                                    Reset Password
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <Form
                                                    v-bind="
                                                        TeamMemberController.resetPassword.form(
                                                            m.id,
                                                        )
                                                    "
                                                    :options="{
                                                        preserveScroll: true,
                                                    }"
                                                    class="space-y-6"
                                                    v-slot="{
                                                        errors,
                                                        processing,
                                                    }"
                                                >
                                                    <DialogHeader
                                                        class="space-y-2"
                                                    >
                                                        <DialogTitle>
                                                            Reset password
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Tetapkan password
                                                            baru untuk
                                                            <strong>{{
                                                                m.name
                                                            }}</strong
                                                            >. Sampaikan ke yang
                                                            bersangkutan secara
                                                            aman.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`pw-${m.id}`"
                                                            >Password
                                                            baru</Label
                                                        >
                                                        <Input
                                                            :id="`pw-${m.id}`"
                                                            name="password"
                                                            type="password"
                                                            required
                                                            autocomplete="new-password"
                                                        />
                                                        <InputError
                                                            :message="
                                                                errors.password
                                                            "
                                                        />
                                                    </div>

                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`pwc-${m.id}`"
                                                            >Konfirmasi
                                                            password</Label
                                                        >
                                                        <Input
                                                            :id="`pwc-${m.id}`"
                                                            name="password_confirmation"
                                                            type="password"
                                                            required
                                                            autocomplete="new-password"
                                                        />
                                                    </div>

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
                                                            :disabled="
                                                                processing
                                                            "
                                                        >
                                                            Simpan password
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                        <Dialog v-if="m.can_set_status">
                                            <DialogTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    :class="
                                                        m.is_active
                                                            ? 'text-muted-foreground'
                                                            : 'text-emerald-700'
                                                    "
                                                >
                                                    <component
                                                        :is="
                                                            m.is_active
                                                                ? UserX
                                                                : UserCheck
                                                        "
                                                    />
                                                    {{
                                                        m.is_active
                                                            ? 'Nonaktifkan'
                                                            : 'Aktifkan'
                                                    }}
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <Form
                                                    v-bind="
                                                        TeamMemberController.updateStatus.form(
                                                            m.id,
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
                                                            m.is_active ? 0 : 1
                                                        "
                                                    />
                                                    <DialogHeader
                                                        class="space-y-2"
                                                    >
                                                        <DialogTitle>
                                                            {{
                                                                m.is_active
                                                                    ? 'Nonaktifkan akun?'
                                                                    : 'Aktifkan kembali akun?'
                                                            }}
                                                        </DialogTitle>
                                                        <DialogDescription
                                                            v-if="m.is_active"
                                                        >
                                                            <strong>{{
                                                                m.name
                                                            }}</strong>
                                                            tidak akan bisa
                                                            login lagi dan
                                                            sesinya langsung
                                                            berakhir.
                                                            <strong
                                                                >Customer dan
                                                                penugasannya
                                                                tetap
                                                                utuh</strong
                                                            >
                                                            — alihkan secara
                                                            terpisah bila perlu.
                                                            Bisa diaktifkan lagi
                                                            kapan saja.
                                                        </DialogDescription>
                                                        <DialogDescription
                                                            v-else
                                                        >
                                                            <strong>{{
                                                                m.name
                                                            }}</strong>
                                                            bisa login lagi dan
                                                            kembali mengakses
                                                            customer serta
                                                            penugasan yang masih
                                                            melekat padanya.
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
                                                                m.is_active
                                                                    ? 'destructive'
                                                                    : 'default'
                                                            "
                                                            :disabled="
                                                                processing
                                                            "
                                                        >
                                                            {{
                                                                m.is_active
                                                                    ? 'Nonaktifkan'
                                                                    : 'Aktifkan'
                                                            }}
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                        <Button
                                            v-if="m.can_offboard && m.is_active"
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground"
                                        >
                                            <Link
                                                :href="
                                                    TeamMemberController.showOffboard(
                                                        m.id,
                                                    )
                                                "
                                            >
                                                <UserMinus />
                                                Offboard
                                            </Link>
                                        </Button>
                                        <span
                                            v-if="
                                                !m.can_reset &&
                                                !m.can_set_status &&
                                                !m.can_offboard
                                            "
                                            class="text-xs text-muted-foreground"
                                            >—</span
                                        >
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="members.data.length === 0">
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex max-w-sm flex-col items-center gap-3"
                                    >
                                        <div
                                            class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                        >
                                            <Users class="size-6" />
                                        </div>
                                        <div class="space-y-1">
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                Belum ada anggota tim
                                            </p>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                Tambahkan anggota pertama untuk
                                                mulai membangun tim Anda.
                                            </p>
                                        </div>
                                        <Button as-child size="sm" class="mt-1">
                                            <Link
                                                :href="
                                                    TeamMemberController.create()
                                                "
                                            >
                                                <Plus />
                                                Tambah Anggota
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="members.total > 0"
                    class="flex flex-wrap items-center justify-between gap-4 border-t border-border p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Menampilkan {{ members.from ?? 0 }}–{{
                            members.to ?? 0
                        }}
                        dari {{ members.total }}
                    </p>
                    <div
                        v-if="members.links.length > 3"
                        class="flex flex-wrap items-center gap-1"
                    >
                        <template v-for="(link, i) in members.links" :key="i">
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
