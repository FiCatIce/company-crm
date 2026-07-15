<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import type { ComponentPublicInstance } from 'vue';
import { computed, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { PermissionGroup, PermissionItem, RoleOption } from '@/types/crm';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        extension: string | null;
        role: string | null;
        permissions: string[];
    };
    roles: RoleOption[];
    permissionGroups: PermissionGroup[];
    rolePresets: Record<string, string[]>;
    isSelf: boolean;
    can: { assignRole: boolean; managePermissions: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengguna', href: UserController.index() },
    { title: props.user.name, href: UserController.edit(props.user.id) },
];

const selectedRole = ref(props.user.role ?? '');
// Effective direct permissions being edited (the two-way toggle, D6).
const granted = ref<string[]>([...props.user.permissions]);
// Snapshot of what was granted on load — used to detect NEW sensitive grants.
const originalGranted = new Set(props.user.permissions);

const roleLabel = computed(
    () => props.roles.find((r) => r.value === props.user.role)?.label ?? '—',
);

function isGranted(name: string): boolean {
    return granted.value.includes(name);
}

function toggle(name: string): void {
    granted.value = isGranted(name)
        ? granted.value.filter((p) => p !== name)
        : [...granted.value, name];
}

// Changing the role resets the checklist to that role's preset — "pick a role,
// permissions follow", which the admin can then fine-tune before saving.
function onRoleChange(): void {
    granted.value = [...(props.rolePresets[selectedRole.value] ?? [])];
}

// Sensitive permissions being granted that the user did NOT already have — these
// trigger the confirmation step before the form is allowed to submit.
const newSensitiveGrants = computed<PermissionItem[]>(() => {
    const items: PermissionItem[] = [];

    for (const group of props.permissionGroups) {
        for (const perm of group.permissions) {
            if (
                perm.sensitive &&
                isGranted(perm.name) &&
                !originalGranted.has(perm.name)
            ) {
                items.push(perm);
            }
        }
    }

    return items;
});

const confirmOpen = ref(false);
const formRef = ref<ComponentPublicInstance | null>(null);

function submitForm(): void {
    // requestSubmit() fires the native submit event that Inertia's <Form>
    // intercepts — same effect as clicking a real submit button.
    const el = formRef.value?.$el as HTMLFormElement | undefined;
    el?.requestSubmit();
}

// Primary save: raise the confirmation dialog first when sensitive grants are
// pending; otherwise submit straight away.
function onSave(): void {
    if (newSensitiveGrants.value.length > 0) {
        confirmOpen.value = true;

        return;
    }

    submitForm();
}

function confirmAndSubmit(): void {
    confirmOpen.value = false;
    submitForm();
}
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Edit Pengguna
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Perbarui profil, role, dan izin akses.
                </p>
            </div>

            <Form
                ref="formRef"
                v-bind="UserController.update.form(user.id)"
                class="space-y-8"
                v-slot="{ errors, processing }"
            >
                <!-- Profile -->
                <section class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            :default-value="user.email"
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
                            :default-value="user.extension ?? ''"
                            autocomplete="off"
                        />
                        <InputError :message="errors.extension" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Password baru (opsional)</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Kosongkan jika tidak diubah"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Konfirmasi password baru</Label
                        >
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                        />
                    </div>
                </section>

                <!-- Role -->
                <section class="space-y-2">
                    <Label for="role">Role</Label>
                    <template v-if="can.assignRole">
                        <select
                            id="role"
                            name="role"
                            v-model="selectedRole"
                            required
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                            @change="onRoleChange"
                        >
                            <option
                                v-for="r in roles"
                                :key="r.value"
                                :value="r.value"
                            >
                                {{ r.label }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Mengganti role akan mereset izin ke preset role
                            tersebut.
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium text-foreground">
                            {{ roleLabel }}
                        </p>
                        <input type="hidden" name="role" :value="user.role ?? ''" />
                        <p class="text-xs text-muted-foreground">
                            {{
                                isSelf
                                    ? 'Anda tidak dapat mengubah role sendiri.'
                                    : 'Anda tidak memiliki izin mengubah role.'
                            }}
                        </p>
                    </template>
                </section>

                <!-- Permissions -->
                <section class="space-y-4">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Izin Akses
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="can.managePermissions">
                                Izin efektif per pengguna. Centang untuk memberi,
                                hapus centang untuk mencabut.
                            </template>
                            <template v-else>
                                {{
                                    isSelf
                                        ? 'Anda tidak dapat mengubah izin sendiri.'
                                        : 'Anda tidak memiliki izin mengatur izin pengguna.'
                                }}
                            </template>
                        </p>
                    </div>

                    <!-- Managed submission: flag + one hidden input per granted perm. -->
                    <template v-if="can.managePermissions">
                        <input type="hidden" name="manage_permissions" value="1" />
                        <input
                            v-for="p in granted"
                            :key="p"
                            type="hidden"
                            name="permissions[]"
                            :value="p"
                        />
                    </template>

                    <div class="grid gap-4">
                        <fieldset
                            v-for="group in permissionGroups"
                            :key="group.group"
                            class="rounded-lg border border-border p-4"
                        >
                            <legend
                                class="px-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {{ group.group }}
                            </legend>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="perm in group.permissions"
                                    :key="perm.name"
                                    class="flex items-start gap-2 text-sm"
                                    :class="
                                        can.managePermissions
                                            ? 'cursor-pointer'
                                            : 'cursor-not-allowed opacity-80'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 size-4 rounded border-input"
                                        :checked="isGranted(perm.name)"
                                        :disabled="!can.managePermissions"
                                        @change="toggle(perm.name)"
                                    />
                                    <span
                                        class="inline-flex flex-wrap items-center gap-1.5 text-foreground"
                                    >
                                        {{ perm.label }}
                                        <span
                                            v-if="perm.sensitive"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700"
                                        >
                                            <ShieldAlert class="size-3" />
                                            Sensitif
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </section>

                <div class="flex items-center gap-3">
                    <Button type="button" :disabled="processing" @click="onSave"
                        >Simpan</Button
                    >
                    <Button as-child variant="ghost" type="button">
                        <Link :href="UserController.index()">Batal</Link>
                    </Button>
                </div>

                <!-- Sensitive-grant confirmation (D2 / §3.5) -->
                <Dialog v-model:open="confirmOpen">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle
                                class="inline-flex items-center gap-2"
                            >
                                <ShieldAlert class="size-5 text-amber-600" />
                                Konfirmasi izin sensitif
                            </DialogTitle>
                            <DialogDescription>
                                Anda akan memberi izin berikut yang membuka data
                                sensitif (PII customer, data finansial, atau
                                kontrol izin):
                            </DialogDescription>
                        </DialogHeader>

                        <ul
                            class="list-disc space-y-1 pl-5 text-sm text-foreground"
                        >
                            <li v-for="p in newSensitiveGrants" :key="p.name">
                                {{ p.label }}
                            </li>
                        </ul>

                        <DialogFooter class="gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                @click="confirmOpen = false"
                            >
                                Batal
                            </Button>
                            <Button type="button" @click="confirmAndSubmit">
                                Ya, beri izin & simpan
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </Form>
        </div>
    </AppLayout>
</template>
