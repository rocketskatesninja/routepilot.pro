<script setup lang="ts">
import { computed } from 'vue';

/**
 * Single-series trend (revenue, visit volume). Honest zero baseline by default;
 * area fill + line + point dots that carry a native tooltip (label: value) so
 * exact figures are inspectable without any client-side crosshair code — which
 * keeps it SSR-safe. Scales to container width via the viewBox.
 */
const props = withDefaults(
    defineProps<{
        values: number[];
        labels: string[];
        color?: string;
        format?: (v: number) => string;
        baseline?: 'zero' | 'auto';
        emptyText?: string;
    }>(),
    {
        color: 'hsl(var(--chart-1))',
        format: (v: number) => String(v),
        baseline: 'zero',
        emptyText: 'No data yet.',
    },
);

const W = 520;
const H = 200;
const padL = 46;
const padR = 12;
const padT = 12;
const padB = 26;
const innerW = W - padL - padR;
const innerH = H - padT - padB;

const domain = computed(() => {
    const v = props.values.length ? props.values : [0];
    let min = Math.min(...v);
    let max = Math.max(...v);
    if (props.baseline === 'zero') {
        min = Math.min(0, min);
    }
    if (min === max) {
        max = min + 1;
    }
    return { min, max };
});

const xAt = (i: number) => padL + (props.values.length > 1 ? (i / (props.values.length - 1)) * innerW : innerW / 2);
const yAt = (val: number) => padT + (1 - (val - domain.value.min) / (domain.value.max - domain.value.min)) * innerH;

const linePoints = computed(() => props.values.map((v, i) => `${xAt(i).toFixed(1)},${yAt(v).toFixed(1)}`).join(' '));
const areaPoints = computed(() => {
    if (!props.values.length) {
        return '';
    }
    const base = yAt(domain.value.min).toFixed(1);
    return `${xAt(0).toFixed(1)},${base} ${linePoints.value} ${xAt(props.values.length - 1).toFixed(1)},${base}`;
});

const yTicks = computed(() => {
    const { min, max } = domain.value;
    return [max, (min + max) / 2, min].map((val) => ({ val, y: yAt(val) }));
});
const dots = computed(() => props.values.map((v, i) => ({ x: xAt(i), y: yAt(v), v, label: props.labels[i] ?? '' })));

// First / middle / last x-labels only, de-duplicated, to avoid crowding.
const xLabelIdx = computed(() => {
    const n = props.values.length;
    if (n <= 1) {
        return n === 1 ? [0] : [];
    }
    return [...new Set([0, Math.floor((n - 1) / 2), n - 1])];
});
</script>

<template>
    <svg v-if="values.length" :viewBox="`0 0 ${W} ${H}`" class="h-auto w-full" role="img" aria-label="Trend chart">
        <g v-for="t in yTicks" :key="t.val">
            <line :x1="padL" :x2="W - padR" :y1="t.y" :y2="t.y" stroke="hsl(var(--border))" stroke-width="1" />
            <text :x="padL - 8" :y="t.y + 4" text-anchor="end" fill="hsl(var(--muted-foreground))" font-size="12">{{ format(t.val) }}</text>
        </g>
        <polygon v-if="values.length > 1" :points="areaPoints" :fill="color" fill-opacity="0.08" />
        <polyline :points="linePoints" fill="none" :stroke="color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <circle v-for="(d, i) in dots" :key="i" :cx="d.x" :cy="d.y" r="3.5" :fill="color">
            <title>{{ d.label }}: {{ format(d.v) }}</title>
        </circle>
        <text v-for="i in xLabelIdx" :key="'x' + i" :x="xAt(i)" :y="H - 8" text-anchor="middle" fill="hsl(var(--muted-foreground))" font-size="12">
            {{ labels[i] }}
        </text>
    </svg>
    <div v-else class="flex h-40 items-center justify-center text-sm text-muted-foreground">{{ emptyText }}</div>
</template>
