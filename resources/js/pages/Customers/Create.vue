<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import CustomerFormFields from '@/components/CustomerFormFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/crm';

defineProps<{
    resellers: { id: number; name: string }[];
    statuses: SelectOption[];
    sources: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customer', href: CustomerController.index() },
    { title: 'Tambah', href: CustomerController.create() },
];
</script>

<template>
    <Head title="Tambah Customer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Tambah Customer
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Buat data customer baru.
                </p>
            </div>

            <Form
                v-bind="CustomerController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <CustomerFormFields
                    :resellers="resellers"
                    :statuses="statuses"
                    :sources="sources"
                    :errors="errors"
                />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing">Simpan</Button>
                    <Button as-child variant="ghost" type="button">
                        <Link :href="CustomerController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
