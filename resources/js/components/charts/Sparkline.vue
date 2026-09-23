<script setup lang="ts">
import { computed } from 'vue';

/**
 * Bare inline trend line — no axes, no interaction. For stat tiles and the
 * portal chlorine/pH mini-trends. Optionally shades an ideal-range band behind
 * the line (the "healthy zone") so out-of-range drift reads at a glance. Scales
 * to its container width; the stroke stays crisp via non-scaling-stroke.
 */
const props = withDefaults(
    defineProps<{
        values: number[];
        color?: string;
        band?: [number, number];
    }>(),
    { color: 'hsl(var(--chart-1))' },
);

const W = 100;
const H = 32;
const PAD = 3;

const domain = computed(() => {
    let min = Math.min(...props.values);
    let max = Math.max(...props.values);
    if (props.band) {
        min = Math.min(min, props.band[0]);
        max = Math.max(max, props.band[1]);
    }
    const span = max - min || 1;
    return { min: min - span * 0.05, max: max + span * 0.05 };
});

const yAt = (n: number) => {
    const { min, max } = domain.value;
    return PAD + (1 - (n - min) / (max - min)) * (H - PAD * 2);
};

const points = computed(() =>
    props.values
        .map((n, i) => `${(props.values.length > 1 ? (i / (props.values.length - 1)) * W : 0).toFixed(2)},${yAt(n).toFixed(2)}`)
        .join(' '),
);

const bandRect = computed(() => {
    if (!props.band) {
        return null;
    }
    const top = yAt(props.band[1]);
    return { y: top, h: Math.max(0, yAt(props.band[0]) - top) };
});
</script>

<template>
    <svg v-if="values.length > 1" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="block h-8 w-full" role="img" aria-hidden="true">
        <rect v-if="bandRect" x="0" :y="bandRect.y" :width="W" :height="bandRect.h" fill="hsl(160 84% 39%)" fill-opacity="0.12" />
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
