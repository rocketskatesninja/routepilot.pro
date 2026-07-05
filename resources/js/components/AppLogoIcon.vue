<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- RoutePilot mark: the lucide "route" glyph (winding road between two stops).
         Stroke-based with fill="none" on each shape so it stays an outline even when a
         caller adds `fill-current`; `stroke="currentColor"` lets each site tint it. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="className"
        v-bind="$attrs"
    >
        <circle cx="6" cy="19" r="3" fill="none" />
        <path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15" fill="none" />
        <circle cx="18" cy="5" r="3" fill="none" />
    </svg>
</template>
