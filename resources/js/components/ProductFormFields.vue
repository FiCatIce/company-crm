<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    errors: Partial<Record<string, string>>;
    product?: {
        name: string;
        warranty_months: number;
    } | null;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="name">Nama Produk</Label>
        <Input
            id="name"
            name="name"
            :default-value="product?.name"
            required
            autocomplete="off"
            placeholder="Nama produk"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="warranty_months">Garansi (bulan)</Label>
        <Input
            id="warranty_months"
            name="warranty_months"
            type="number"
            min="0"
            max="600"
            :default-value="product?.warranty_months ?? 0"
            required
        />
        <p class="text-xs text-muted-foreground">
            Isi 0 jika produk tanpa garansi.
        </p>
        <InputError :message="errors.warranty_months" />
    </div>
</template>
