<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * R-C shared data table — the ONLY table markup in the app (TableGuardTest).
 * Corporate density: 12px uppercase headers, 13px body, ~36px effective rows,
 * 16px cell gutters. Numeric/code columns must use <TableCell kind="mono">
 * (phone, duration, timestamp, ID) or kind="money" (right-aligned amounts).
 */
const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div data-slot="table-container" class="w-full overflow-x-auto">
    <table
      data-slot="table"
      :class="cn('w-full text-left text-[13px]', props.class)"
    >
      <slot />
    </table>
  </div>
</template>
