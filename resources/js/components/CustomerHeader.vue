<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Mail, Pencil, Phone, Plus } from '@lucide/vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerStatusBadge from '@/components/CustomerStatusBadge.vue';
import OwnerBadge from '@/components/OwnerBadge.vue';
import { Button } from '@/components/ui/button';
import { relativeDays } from '@/lib/format';
import type { CustomerDetail, CustomerStats, SelectOption } from '@/types/crm';

const props = defineProps<{
    customer: CustomerDetail;
    stats: CustomerStats;
    statuses: SelectOption[];
    users: SelectOption[];
    can: { update: boolean; delete: boolean; logInteraction: boolean };
}>();

const emit = defineEmits<{ (e: 'log'): void }>();

const initial = props.customer.name.trim().charAt(0).toUpperCase();

// Quick lifecycle change: patch status only, refresh just the customer prop so
// the badge re-syncs without a full page reload.
function changeStatus(event: Event) {
    const next = (event.target as HTMLSelectElement).value;

    if (next === props.customer.status) {
        return;
    }

    router.patch(
        CustomerController.updateStatus.url(props.customer.id),
        { status: next },
        { preserveScroll: true, preserveState: true, only: ['customer'] },
    );
}

// Quick owner reassignment (attribution only, never gates access).
function changeOwner(event: Event) {
    const raw = (event.target as HTMLSelectElement).value;
    const next = raw === '' ? null : Number(raw);

    if (next === (props.customer.owner?.id ?? null)) {
        return;
    }

    router.patch(
        CustomerController.updateOwner.url(props.customer.id),
        { assigned_to: next },
        { preserveScroll: true, preserveState: true, only: ['customer'] },
    );
}

const selectClasses =
    'h-8 rounded-md border border-input bg-transparent px-2 py-0 text-xs shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-semibold text-primary"
                >
                    {{ initial }}
                </div>
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-2xl font-semibold tracking-tight text-foreground"
                        >
                            {{ customer.name }}
                        </h1>
                        <CustomerStatusBadge
                            :status="customer.status"
                            :label="customer.status_label"
                        />
                        <select
                            v-if="can.update"
                            :value="customer.status"
                            :class="selectClasses"
                            aria-label="Ubah status customer"
                            @change="changeStatus"
                        >
                            <option
                                v-for="s in statuses"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span
                            v-if="customer.phone"
                            class="inline-flex items-center gap-1.5"
                        >
                            <Phone class="size-4" aria-hidden="true" />{{
                                customer.phone
                            }}
                        </span>
                        <span
                            v-if="customer.email"
                            class="inline-flex items-center gap-1.5"
                        >
                            <Mail class="size-4" aria-hidden="true" />{{
                                customer.email
                            }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <OwnerBadge
                                :owner="customer.owner"
                                empty-text="Belum ditugaskan"
                            />
                            <select
                                v-if="can.update"
                                :value="
                                    customer.owner
                                        ? String(customer.owner.id)
                                        : ''
                                "
                                :class="selectClasses"
                                aria-label="Ubah owner customer"
                                @change="changeOwner"
                            >
                                <option value="">Belum ada owner</option>
                                <option
                                    v-for="u in users"
                                    :key="u.value"
                                    :value="u.value"
                                >
                                    {{ u.label }}
                                </option>
                            </select>
                        </span>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span>{{ stats.interactionsCount }} interaksi</span>
                        <span aria-hidden="true">·</span>
                        <span
                            >Terakhir dihubungi
                            {{ relativeDays(stats.lastContactedAt) }}</span
                        >
                        <span aria-hidden="true">·</span>
                        <span>{{ stats.transactionsCount }} transaksi</span>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <Button v-if="can.logInteraction" @click="emit('log')">
                    <Plus />
                    Catat Interaksi
                </Button>
                <Button v-if="can.update" as-child variant="outline">
                    <Link :href="CustomerController.edit(customer.id)">
                        <Pencil />
                        Edit
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
