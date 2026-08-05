<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

// The empty state is part of the primitive: pages provide icon + copy via
// the slot, never their own centering markup.
const props = defineProps<{
  colspan: number
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tr data-slot="table-empty">
    <td
      :colspan="colspan"
      :class="cn('px-4 py-12 text-center', props.class)"
    >
      <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
        <slot />
      </div>
    </td>
  </tr>
</template>
