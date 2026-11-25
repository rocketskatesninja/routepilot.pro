<script setup lang="ts" generic="T">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

/** The browser's own select, styled like the other inputs. Options go in the slot. */
const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<T>();
</script>

<template>
    <select v-model="model" :class="cn('border-input bg-background h-9 rounded-md border px-2 text-sm', props.class)">
        <slot />
    </select>
</template>
