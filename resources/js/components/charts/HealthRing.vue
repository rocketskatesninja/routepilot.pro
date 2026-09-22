<script setup lang="ts">
import { computed } from 'vue';

/**
 * Status donut for pool health. `tone` matches the LSI status colour computed by
 * ChemistryService (green / amber / red); `value` is the fraction of the ring to
 * fill (defaults to a full ring). Semantic colours are fixed (not the categorical
 * chart ramp) so status reads the same in light and dark.
 */
const props = withDefaults(
    defineProps<{
        tone?: 'green' | 'amber' | 'red';
        label: string;
        sublabel?: string;
        value?: number;
    }>(),
    { tone: 'green', value: 1 },
);

const color = computed(
    () =>
        ({
            green: 'hsl(160 84% 39%)',
            amber: 'hsl(38 92% 50%)',
            red: 'hsl(0 84% 60%)',
        })[props.tone],
);

const R = 34;
const C = 2 * Math.PI * R;
const dash = computed(() => `${Math.max(0, Math.min(1, props.value)) * C} ${C}`);
</script>

<template>
    <div class="relative inline-grid place-items-center">
        <svg viewBox="0 0 80 80" class="size-20 -rotate-90" role="img" :aria-label="`Health: ${label}${sublabel ? ', ' + sublabel : ''}`">
            <circle cx="40" cy="40" :r="R" fill="none" stroke="hsl(var(--muted))" stroke-width="8" />
            <circle cx="40" cy="40" :r="R" fill="none" :stroke="color" stroke-width="8" stroke-linecap="round" :stroke-dasharray="dash" />
        </svg>
        <div class="absolute px-2 text-center leading-tight">
            <div class="text-sm font-semibold" :style="{ color }">{{ label }}</div>
            <div v-if="sublabel" class="text-[10px] text-muted-foreground">{{ sublabel }}</div>
        </div>
    </div>
</template>
