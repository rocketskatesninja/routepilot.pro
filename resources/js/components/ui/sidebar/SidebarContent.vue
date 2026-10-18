<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        data-sidebar="content"
        :class="cn('flex min-h-0 flex-1 flex-col gap-1 overflow-x-hidden overflow-y-auto group-data-[collapsible=icon]:[scrollbar-width:none]', props.class)"
    >
        <slot />
    </div>
</template>
