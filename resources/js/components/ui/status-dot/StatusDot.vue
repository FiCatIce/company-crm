<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { StatusDotVariants } from "."
import { cn } from "@/lib/utils"
import { statusDotVariants } from "."

const props = defineProps<{
  state?: StatusDotVariants["state"]
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <span
    data-slot="status-dot"
    aria-hidden="true"
    :class="cn(statusDotVariants({ state }), props.class)"
  />
</template>
