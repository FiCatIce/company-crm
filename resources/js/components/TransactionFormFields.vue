<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = { id: number; name: string };

const props = defineProps<{
    customers: Option[];
    products: Option[];
    resellers: Option[];
    errors: Partial<Record<string, string>>;
    transaction?: {
        customer_id: number;
        product_id: number;
        reseller_id: number;
        purchased_at: string | null;
    } | null;
}>();

// Controlled selects so choices survive re-renders after a validation error.
const customerId = ref<number | ''>(props.transaction?.customer_id ?? '');
const productId = ref<number | ''>(props.transaction?.product_id ?? '');
const resellerId = ref<number | ''>(props.transaction?.reseller_id ?? '');

const selectClass =
    'border-input h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <div class="grid gap-2">
        <Label for="customer_id">Customer</Label>
        <select
            id="customer_id"
            v-model="customerId"
            name="customer_id"
            required
            :class="selectClass"
        >
            <option value="" disabled>Pilih customer</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">
                {{ c.name }}
            </option>
        </select>
        <InputError :message="errors.customer_id" />
    </div>

    <div class="grid gap-2">
        <Label for="product_id">Produk</Label>
        <select
            id="product_id"
            v-model="productId"
            name="product_id"
            required
            :class="selectClass"
        >
            <option value="" disabled>Pilih produk</option>
            <option v-for="p in products" :key="p.id" :value="p.id">
                {{ p.name }}
            </option>
        </select>
        <InputError :message="errors.product_id" />
    </div>

    <div class="grid gap-2">
        <Label for="reseller_id">Reseller</Label>
        <select
            id="reseller_id"
            v-model="resellerId"
            name="reseller_id"
            required
            :class="selectClass"
        >
            <option value="" disabled>Pilih reseller</option>
            <option v-for="r in resellers" :key="r.id" :value="r.id">
                {{ r.name }}
            </option>
        </select>
        <InputError :message="errors.reseller_id" />
    </div>

    <div class="grid gap-2">
        <Label for="purchased_at">Tanggal Beli</Label>
        <Input
            id="purchased_at"
            type="date"
            name="purchased_at"
            :default-value="transaction?.purchased_at ?? ''"
            required
        />
        <InputError :message="errors.purchased_at" />
    </div>
</template>
