<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import ProductFormFields from '@/components/ProductFormFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    product: {
        id: number;
        name: string;
        warranty_months: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produk', href: ProductController.index() },
    { title: 'Edit', href: ProductController.edit(props.product.id) },
];
</script>

<template>
    <Head title="Edit Produk" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Edit Produk
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Perbarui data {{ product.name }}.
                </p>
            </div>

            <Form
                v-bind="ProductController.update.form(product.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <ProductFormFields :errors="errors" :product="product" />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing"
                        >Perbarui</Button
                    >
                    <Button as-child variant="ghost" type="button">
                        <Link :href="ProductController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
