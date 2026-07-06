<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
  customers: Array<{
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    reseller: string | null;
  }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customer', href: '/customers' },
];

const initial = (name: string) => name.trim().charAt(0).toUpperCase();
</script>

<template>
  <Head title="Data Customer" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-foreground">Data Customer</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.customers.length }} customer terdaftar
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/50 text-muted-foreground">
                        <tr>
                          <th class="px-5 py-3 font-medium">Nama</th>
                          <th class="px-5 py-3 font-medium">Telepon</th>
                          <th class="px-5 py-3 font-medium">Email</th>
                          <th class="px-5 py-3 font-medium">Alamat</th>
                          <th class="px-5 py-3 font-medium">reseller</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr class="transition-colors hover:bg-muted/40" v-for="c in props.customers" :key="c.id">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {{ initial(c.name) }}
                                    </div>
                                    <span class="font-medium text-foreground">{{ c.name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">{{ c.phone ?? '-' }}</td>
                            <td class="px-5 py-3 text-muted-foreground">{{ c.email ?? '-' }}</td>
                            <td class="px-5 py-3 text-muted-foreground">{{ c.address ?? '-' }}</td>
                            <td class="px-5 py-3">
                                <span v-if="c.reseller" class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground">
                                    {{ c.reseller }}
                                </span>
                                <span v-else="" class="text-muted-foreground">—</span>
                            </td>
                        </tr>

                        <tr v-if="props.customers.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center text-muted-foreground">
                                Belum ada data customer.
                            </td>
                        </tr>
                    </tbody>
                </table>    
            </div>
        </div>
    </div>
  </AppLayout>

  
</template>