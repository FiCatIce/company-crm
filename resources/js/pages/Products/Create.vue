<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import ProductFormFields from '@/components/ProductFormFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produk', href: ProductController.index() },
    { title: 'Tambah', href: ProductController.create() },
];
</script>

<template>
    <Head title="Tambah Produk" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1
                    class="text-xl font-semibold tracking-tight text-foreground"
                >
                    Tambah Produk
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Buat data produk baru.
                </p>
            </div>

            <Form
                v-bind="ProductController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <ProductFormFields :errors="errors" />

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing">Simpan</Button>
                    <Button as-child variant="ghost" type="button">
                        <Link :href="ProductController.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
