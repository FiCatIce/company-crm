<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    parentOptions: { id: number; name: string }[];
    errors: Partial<Record<string, string>>;
    reseller?: { name: string; parent_id: number | null } | null;
}>();

// Controlled so the selection survives re-renders after a validation error.
const parentId = ref<number | ''>(props.reseller?.parent_id ?? '');
</script>

<template>
    <div class="grid gap-2">
        <Label for="name">Nama Reseller</Label>
        <Input
            id="name"
            name="name"
            :default-value="reseller?.name"
            required
            autocomplete="off"
            placeholder="Nama reseller"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="parent_id">Induk</Label>
        <select
            id="parent_id"
            v-model="parentId"
            name="parent_id"
            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
            <option value="">— Tanpa induk (reseller utama) —</option>
            <option v-for="r in parentOptions" :key="r.id" :value="r.id">
                {{ r.name }}
            </option>
        </select>
        <p class="text-xs text-muted-foreground">
            Kosongkan untuk menjadikan reseller tingkat atas.
        </p>
        <InputError :message="errors.parent_id" />
    </div>
</template>
