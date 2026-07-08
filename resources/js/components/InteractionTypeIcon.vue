<script setup lang="ts">
import {
    Circle,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    StickyNote,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';

const props = defineProps<{ type: string }>();

const icons: Record<string, Component> = {
    call: Phone,
    whatsapp: MessageCircle,
    email: Mail,
    note: StickyNote,
    visit: MapPin,
};

const icon = computed<Component>(() => icons[props.type] ?? Circle);
</script>

<template>
    <div
        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
    >
        <component :is="icon" class="size-4" />
    </div>
</template>
