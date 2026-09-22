<script setup lang="ts">
import { computed } from 'vue';

/**
 * Bare inline trend line — no axes, no interaction. For stat tiles and the
 * portal chlorine/pH mini-trends. Scales to its container width; the stroke
 * stays crisp via non-scaling-stroke.
 */
const props = withDefaults(
    defineProps<{
        values: number[];
        color?: string;
    }>(),
    { color: 'hsl(var(--chart-1))' },
);

const W = 100;
const H = 32;
const PAD = 3;

const points = computed(() => {
    const v = props.values;
    if (v.length === 0) {
        return '';
    }
    const min = Math.min(...v);
    const max = Math.max(...v);
    const span = max - min || 1;
    const stepX = v.length > 1 ? W / (v.length - 1) : 0;
    return v
        .map((n, i) => `${(i * stepX).toFixed(2)},${(PAD + (1 - (n - min) / span) * (H - PAD * 2)).toFixed(2)}`)
        .join(' ');
});
</script>

<template>
    <svg v-if="values.length > 1" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="block h-8 w-full" role="img" aria-hidden="true">
        <polyline
            :points="points"
            fill="none"
            :stroke="color"
            stroke-width="2"
            vector-effect="non-scaling-stroke"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
    <div v-else class="h-8" aria-hidden="true"></div>
</template>
