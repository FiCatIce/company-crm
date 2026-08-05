<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

// priority="secondary" hides the column below lg so the action column
// stays reachable on tablets without horizontal scrolling.
const props = defineProps<{
  align?: "left" | "right"
  priority?: "secondary"
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
        priority === 'secondary' && 'hidden lg:table-cell',
        props.class,
      )
    "
  >
    <slot />
  </th>
</template>
