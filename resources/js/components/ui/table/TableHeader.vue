<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

// One header row per table, so the <tr> lives here — pages only list
// <TableHead> cells.
const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <thead data-slot="table-header">
    <tr :class="cn('border-b border-border', props.class)">
      <slot />
    </tr>
  </thead>
</template>
