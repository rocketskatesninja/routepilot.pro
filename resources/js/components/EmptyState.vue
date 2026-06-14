<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: string }>();
</script>

<template>
    <div :class="cn('flex h-full flex-col items-center justify-center gap-2 p-4 text-center text-sm text-muted-foreground', props.class)">
        <slot name="icon" />
        <slot />
    </div>
</template>
