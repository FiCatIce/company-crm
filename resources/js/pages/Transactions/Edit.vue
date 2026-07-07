<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import TransactionFormFields from '@/components/TransactionFormFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    transaction: {
        id: number;
        customer_id: number;
        product_id: number;
        reseller_id: number;
        purchased_at: string | null;
    };
    customers: { id: number; name: string }[];
    products: { id: number; name: string }[];
    resellers: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transaksi', href: TransactionController.index() },
    { title: 'Edit', href: TransactionController.edit(props.transaction.id) },
];
</script>

<template>
    <Head title="Edit Transaksi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Edit Transaksi
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Perbarui data transaksi.
                </p>
            </div>

            <Form
                v-bind="TransactionController.update.form(transaction.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <TransactionFormFields
                    :customers="customers"
                    :products="products"
                    :resellers="resellers"
                    :errors="errors"
                    :transaction="transaction"
                />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing"
                        >Perbarui</Button
                    >
                    <Button as-child variant="ghost" type="button">
                        <Link :href="TransactionController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
