<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Company CRM mark: a simple office-building glyph (windows and door cut out) -->
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M4 3a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v6h5a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3Zm2 2h2v2H6V5Zm4 0h2v2h-2V5ZM6 9h2v2H6V9Zm4 0h2v2h-2V9Zm-4 4h2v2H6v-2Zm4 0h2v2h-2v-2Zm6-2h2v2h-2v-2Zm0 4h2v2h-2v-2Zm-7 2h2v4H9v-4Z"
        />
    </svg>
</template>
