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
import { Badge } from '@/components/ui/badge';
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

const formatDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';

setBreadcrumbs(() => breadcrumbs);
</script>

<template>
    <Head title="Anggota Tim" />
    <div class="flex flex-col gap-6">
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
                        Kelola anggota yang Anda bawahi — tambah anggota baru
                        dan reset password mereka.
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
            class="flex items-center gap-2 rounded-lg border border-state-ok/30 bg-state-ok-soft px-4 py-3 text-sm text-state-ok-strong"
        >
            <Check class="size-4 shrink-0" />
            <span>{{ flashSuccess }}</span>
        </div>
        <div
            v-if="flashError"
            class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
        >
            {{ flashError }}
        </div>

        <div
            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
        >
            <Table>
                <TableHeader>
                    <TableHead>Anggota</TableHead>
                    <TableHead>Tipe</TableHead>
                    <TableHead>Extension</TableHead>
                    <TableHead>Bergabung</TableHead>
                    <TableHead align="right">Aksi</TableHead>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="m in members.data" :key="m.id">
                        <TableCell>
                            <div
                                class="flex items-center gap-3 whitespace-nowrap"
                            >
                                <div
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary"
                                >
                                    {{ initial(m.name) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium text-foreground">
                                        {{ m.name }}
                                    </div>
                                    <div
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ m.email }}
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <!-- Kategori, bukan state: netral monokrom, teks
                                    yang membedakan — role kustom tenant otomatis
                                    tampil sama tanpa utang warna per-role. -->
                                <Badge v-if="m.type" variant="outline">{{
                                    m.type.label
                                }}</Badge>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                                <Badge v-if="!m.is_active" variant="idle"
                                    >Nonaktif</Badge
                                >
                            </div>
                        </TableCell>
                        <TableCell kind="mono" class="text-muted-foreground">
                            {{ m.extension ?? '—' }}
                        </TableCell>
                        <TableCell kind="mono" class="text-muted-foreground">
                            {{ formatDate(m.created_at) }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end">
                                <Dialog v-if="m.can_reset">
                                    <DialogTrigger as-child>
                                        <Button variant="ghost" size="sm">
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
                                            v-slot="{ errors, processing }"
                                        >
                                            <DialogHeader class="space-y-2">
                                                <DialogTitle>
                                                    Reset password
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Tetapkan password baru untuk
                                                    <strong>{{ m.name }}</strong
                                                    >. Sampaikan ke yang
                                                    bersangkutan secara aman.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <div class="grid gap-2">
                                                <Label :for="`pw-${m.id}`"
                                                    >Password baru</Label
                                                >
                                                <Input
                                                    :id="`pw-${m.id}`"
                                                    name="password"
                                                    type="password"
                                                    required
                                                    autocomplete="new-password"
                                                />
                                                <InputError
                                                    :message="errors.password"
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label :for="`pwc-${m.id}`"
                                                    >Konfirmasi password</Label
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
                                                    :disabled="processing"
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
                                                    : 'text-state-ok-strong'
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
                                                :value="m.is_active ? 0 : 1"
                                            />
                                            <DialogHeader class="space-y-2">
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
                                                    tidak akan bisa login lagi
                                                    dan sesinya langsung
                                                    berakhir.
                                                    <strong
                                                        >Customer dan
                                                        penugasannya tetap
                                                        utuh</strong
                                                    >
                                                    — alihkan secara terpisah
                                                    bila perlu. Bisa diaktifkan
                                                    lagi kapan saja.
                                                </DialogDescription>
                                                <DialogDescription v-else>
                                                    <strong>{{
                                                        m.name
                                                    }}</strong>
                                                    bisa login lagi dan kembali
                                                    mengakses customer serta
                                                    penugasan yang masih melekat
                                                    padanya.
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
                                                    :disabled="processing"
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
                                <!-- Same as /users: never gate offboard on
                                        is_active — a deactivated member may still
                                        hold work that only offboard can hand over. -->
                                <Button
                                    v-if="m.can_offboard"
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
                        </TableCell>
                    </TableRow>

                    <TableEmpty v-if="members.data.length === 0" :colspan="5">
                        <div
                            class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <Users class="size-6" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-foreground">
                                Belum ada anggota tim
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Tambahkan anggota pertama untuk mulai membangun
                                tim Anda.
                            </p>
                        </div>
                        <Button as-child size="sm" class="mt-1">
                            <Link :href="TeamMemberController.create()">
                                <Plus />
                                Tambah Anggota
                            </Link>
                        </Button>
                    </TableEmpty>
                </TableBody>
            </Table>

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
</template>
