<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerFormFields from '@/components/CustomerFormFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/crm';

const props = defineProps<{
    customer: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
        address: string | null;
        assigned_to: number | null;
        status: string;
        source: string | null;
    };
    statuses: SelectOption[];
    sources: SelectOption[];
    users: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customer', href: CustomerController.index() },
    { title: 'Edit', href: CustomerController.edit(props.customer.id) },
];
</script>

<template>
    <Head title="Edit Customer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Edit Customer
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Perbarui data {{ customer.name }}.
                </p>
            </div>

            <Form
                v-bind="CustomerController.update.form(customer.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <CustomerFormFields
                    :statuses="statuses"
                    :sources="sources"
                    :users="users"
                    :errors="errors"
                    :customer="customer"
                />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing"
                        >Perbarui</Button
                    >
                    <Button as-child variant="ghost" type="button">
                        <Link :href="CustomerController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
