<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ResellerController from '@/actions/App/Http/Controllers/ResellerController';
import ResellerFormFields from '@/components/ResellerFormFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    reseller: {
        id: number;
        name: string;
        parent_id: number | null;
    };
    parentOptions: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reseller', href: ResellerController.index() },
    { title: 'Edit', href: ResellerController.edit(props.reseller.id) },
];
</script>

<template>
    <Head title="Edit Reseller" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Edit Reseller
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Perbarui data {{ reseller.name }}.
                </p>
            </div>

            <Form
                v-bind="ResellerController.update.form(reseller.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <ResellerFormFields
                    :parent-options="parentOptions"
                    :errors="errors"
                    :reseller="reseller"
                />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing"
                        >Perbarui</Button
                    >
                    <Button as-child variant="ghost" type="button">
                        <Link :href="ResellerController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
