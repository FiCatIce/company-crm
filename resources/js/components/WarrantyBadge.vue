<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { StatusDot } from '@/components/ui/status-dot';

defineProps<{
    isUnderWarranty: boolean;
    expiresAt: string | null;
    warrantyMonths: number;
}>();
</script>

<template>
    <Badge v-if="warrantyMonths === 0" variant="idle">Tanpa garansi</Badge>
    <Badge v-else-if="isUnderWarranty" variant="ok" class="gap-1.5">
        <StatusDot state="ok" class="size-1.5" />
        Aktif s/d {{ expiresAt }}
    </Badge>
    <Badge v-else variant="busy" class="gap-1.5">
        <StatusDot state="busy" class="size-1.5" />
        Berakhir {{ expiresAt }}
    </Badge>
</template>
