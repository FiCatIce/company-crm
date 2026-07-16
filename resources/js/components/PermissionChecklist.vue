<script setup lang="ts">
import { ShieldAlert } from '@lucide/vue';
import type { PermissionGroup } from '@/types/crm';

// Grouped permission checklist with a "sensitive" badge — shared by the role
// builder (Create/Edit) and mirrors the B5 user permission toggle. `disabled`
// renders it read-only (e.g. viewing a locked system role).
const props = defineProps<{
    groups: PermissionGroup[];
    modelValue: string[];
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [string[]] }>();

function isGranted(name: string): boolean {
    return props.modelValue.includes(name);
}

function toggle(name: string): void {
    if (props.disabled) {
        return;
    }

    emit(
        'update:modelValue',
        isGranted(name)
            ? props.modelValue.filter((permission) => permission !== name)
            : [...props.modelValue, name],
    );
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <fieldset
            v-for="group in groups"
            :key="group.group"
            class="rounded-xl border border-border bg-card p-4"
        >
            <legend class="px-1 text-sm font-semibold text-foreground">
                {{ group.group }}
            </legend>
            <div class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <label
                    v-for="perm in group.permissions"
                    :key="perm.name"
                    class="flex items-center gap-2.5 py-1.5"
                    :class="
                        disabled
                            ? 'cursor-not-allowed opacity-70'
                            : 'cursor-pointer'
                    "
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded border-border text-primary focus:ring-primary"
                        :checked="isGranted(perm.name)"
                        :disabled="disabled"
                        @change="toggle(perm.name)"
                    />
                    <span class="text-sm text-foreground">{{
                        perm.label
                    }}</span>
                    <span
                        v-if="perm.sensitive"
                        class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700"
                    >
                        <ShieldAlert class="size-3" />
                        Sensitif
                    </span>
                </label>
            </div>
        </fieldset>
    </div>
</template>
