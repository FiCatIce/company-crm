<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Mail, Network, Pencil, Phone, UserCircle } from '@lucide/vue';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerStatusBadge from '@/components/CustomerStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { relativeDays } from '@/lib/format';
import type { CustomerDetail, CustomerStats } from '@/types/crm';

const props = defineProps<{
    customer: CustomerDetail;
    stats: CustomerStats;
    can: { update: boolean; delete: boolean; logInteraction: boolean };
}>();

const initial = props.customer.name.trim().charAt(0).toUpperCase();
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
                        <span
                            v-if="customer.reseller"
                            class="inline-flex items-center gap-1.5"
                        >
                            <Network class="size-4" aria-hidden="true" />{{
                                customer.reseller.name
                            }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <UserCircle class="size-4" aria-hidden="true" />
                            {{
                                customer.owner
                                    ? customer.owner.name
                                    : 'Belum ditugaskan'
                            }}
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

            <Button v-if="can.update" as-child variant="outline">
                <Link :href="CustomerController.edit(customer.id)">
                    <Pencil />
                    Edit
                </Link>
            </Button>
        </div>
    </div>
</template>
