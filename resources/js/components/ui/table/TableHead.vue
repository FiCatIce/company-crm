<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  align?: "left" | "right"
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <th
    data-slot="table-head"
    :class="
      cn(
        'px-4 py-2.5 text-xs font-medium tracking-wider text-muted-foreground uppercase',
        align === 'right' && 'text-right',
        props.class,
      )
    "
  >
    <slot />
  </th>
</template>
