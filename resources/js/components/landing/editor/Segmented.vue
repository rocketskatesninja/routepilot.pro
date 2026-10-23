<script setup lang="ts">
// Pick one of a few options, side by side (alignment, size, background, ...).
import type { Option } from '../meta';
import { choice } from './choice';

const value = defineModel<string | null | undefined>({ required: true });
defineProps<{ options: readonly Option[] }>();
</script>

<template>
    <div class="flex gap-1" role="group">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            :aria-pressed="value === o.value"
            class="flex flex-1 items-center justify-center gap-1.5 rounded-md border py-1.5 text-xs"
            :class="choice(value === o.value)"
            @click="value = o.value"
        >
            <component :is="o.icon" v-if="o.icon" class="size-3.5" /> {{ o.label }}
        </button>
    </div>
</template>
