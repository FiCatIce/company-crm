<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Reseller = { id: number; name: string };

const props = defineProps<{
    resellers: Reseller[];
    errors: Partial<Record<string, string>>;
    customer?: {
        name: string;
        phone: string | null;
        email: string | null;
        address: string | null;
        reseller_id: number;
    } | null;
}>();

// Native <select>/<textarea> are controlled so their state survives re-renders
// (e.g. when the Inertia <Form> re-renders after a validation error).
const resellerId = ref<number | ''>(props.customer?.reseller_id ?? '');
const address = ref<string>(props.customer?.address ?? '');

const controlClasses =
    'border-input w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <div class="grid gap-2">
        <Label for="reseller_id">Reseller</Label>
        <select
            id="reseller_id"
            v-model="resellerId"
            name="reseller_id"
            required
            :class="['h-9', controlClasses]"
        >
            <option value="" disabled>Pilih reseller</option>
            <option v-for="r in resellers" :key="r.id" :value="r.id">
                {{ r.name }}
            </option>
        </select>
        <InputError :message="errors.reseller_id" />
    </div>

    <div class="grid gap-2">
        <Label for="name">Nama</Label>
        <Input
            id="name"
            name="name"
            :default-value="customer?.name"
            required
            autocomplete="off"
            placeholder="Nama customer"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="phone">Telepon</Label>
        <Input
            id="phone"
            name="phone"
            :default-value="customer?.phone ?? ''"
            autocomplete="off"
            placeholder="08xxxxxxxxxx"
        />
        <InputError :message="errors.phone" />
    </div>

    <div class="grid gap-2">
        <Label for="email">Email</Label>
        <Input
            id="email"
            type="email"
            name="email"
            :default-value="customer?.email ?? ''"
            autocomplete="off"
            placeholder="email@contoh.com"
        />
        <InputError :message="errors.email" />
    </div>

    <div class="grid gap-2">
        <Label for="address">Alamat</Label>
        <textarea
            id="address"
            v-model="address"
            name="address"
            rows="3"
            placeholder="Alamat customer"
            :class="controlClasses"
        />
        <InputError :message="errors.address" />
    </div>
</template>
