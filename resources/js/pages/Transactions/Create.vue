<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import TransactionFormFields from '@/components/TransactionFormFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    customers: { id: number; name: string }[];
    products: { id: number; name: string }[];
    resellers: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transaksi', href: TransactionController.index() },
    { title: 'Tambah', href: TransactionController.create() },
];
</script>

<template>
    <Head title="Tambah Transaksi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Tambah Transaksi
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Catat transaksi pembelian baru.
                </p>
            </div>

            <Form
                v-bind="TransactionController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <TransactionFormFields
                    :customers="customers"
                    :products="products"
                    :resellers="resellers"
                    :errors="errors"
                />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing">Simpan</Button>
                    <Button as-child variant="ghost" type="button">
                        <Link :href="TransactionController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
