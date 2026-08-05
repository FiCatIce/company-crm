<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * kind="mono"  — code-like values: phone numbers, durations, timestamps, IDs.
 * kind="money" — amounts: mono digits AND right-aligned.
 * Pages must use these variants instead of hand-rolling font classes.
 */
const props = defineProps<{
  kind?: "mono" | "money"
  priority?: "secondary"
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <td
    data-slot="table-cell"
    :class="
      cn(
        'px-4 py-2',
        kind === 'mono' && 'font-mono tabular-nums whitespace-nowrap',
        kind === 'money' && 'text-right font-mono tabular-nums whitespace-nowrap',
        priority === 'secondary' && 'hidden lg:table-cell',
        props.class,
      )
    "
  >
    <slot />
  </td>
</template>
