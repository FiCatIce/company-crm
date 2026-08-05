<script setup lang="ts">
import { ref } from 'vue';

/*
 * Dormant call bar — the reason the shell is persistent (R-B). CTI 4c will
 * drive `activeCall` from the Reverb screen-pop channel; until then this
 * renders nothing. It lives in AppSidebarLayout (above the page content,
 * full-width) so an active call survives Inertia navigations. Same dormant
 * pattern as setting.labels in L3-A: wiring first, feature later.
 */
type ActiveCall = {
    callerNumber: string;
    startedAt: number;
};

const activeCall = ref<ActiveCall | null>(null);
</script>

<template>
    <div
        v-if="activeCall"
        data-slot="call-dock"
        class="flex w-full items-center gap-3 border-b border-border bg-card px-4 py-2 text-[13px] sm:px-6"
    >
        <span class="font-mono">{{ activeCall.callerNumber }}</span>
    </div>
</template>
