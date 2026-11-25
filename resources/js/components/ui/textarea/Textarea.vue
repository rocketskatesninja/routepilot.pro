<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<string | null>();
</script>

<template>
    <textarea v-model="model" :class="cn('border-input bg-background rounded-md border px-3 py-2 text-sm', props.class)"></textarea>
</template>
