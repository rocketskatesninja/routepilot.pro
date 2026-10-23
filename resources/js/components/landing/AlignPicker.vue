<script setup lang="ts">
// Left / center / right, for the hero text and the stats strip.
import { AlignCenter, AlignLeft, AlignRight } from 'lucide-vue-next';

const align = defineModel<string | undefined>({ default: 'left' });
const options = [
    { value: 'left', label: 'Left', icon: AlignLeft },
    { value: 'center', label: 'Center', icon: AlignCenter },
    { value: 'right', label: 'Right', icon: AlignRight },
];
</script>

<template>
    <div class="flex gap-1" role="group">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            :aria-pressed="(align || 'left') === o.value"
            class="flex flex-1 items-center justify-center gap-1.5 rounded-md border py-1 text-xs"
            :class="(align || 'left') === o.value ? 'border-primary bg-primary/10 text-primary' : 'border-border'"
            @click="align = o.value"
        >
            <component :is="o.icon" class="size-3.5" /> {{ o.label }}
        </button>
    </div>
</template>
