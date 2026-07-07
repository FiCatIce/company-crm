<script setup lang="ts">
defineProps<{
    isUnderWarranty: boolean;
    expiresAt: string | null;
    warrantyMonths: number;
}>();
</script>

<template>
    <span
        v-if="warrantyMonths === 0"
        class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
    >
        Tanpa garansi
    </span>
    <span
        v-else-if="isUnderWarranty"
        class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300"
    >
        <span
            class="h-1.5 w-1.5 rounded-full bg-green-500"
            aria-hidden="true"
        />
        Aktif s/d {{ expiresAt }}
    </span>
    <span
        v-else
        class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300"
    >
        <span class="h-1.5 w-1.5 rounded-full bg-red-500" aria-hidden="true" />
        Berakhir {{ expiresAt }}
    </span>
</template>
