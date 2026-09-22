<script setup lang="ts">
import { computed } from 'vue';

/**
 * Horizontal bars for named categories (top techs, AR aging). Plain HTML/CSS —
 * simpler and more theme-consistent than SVG for this form. Bars share one scale
 * (max), carry a direct value label, and honour per-bar colour when given.
 */
const props = withDefaults(
    defineProps<{
        bars: { label: string; value: number; color?: string }[];
        format?: (v: number) => string;
        color?: string;
        emptyText?: string;
    }>(),
    {
        format: (v: number) => String(v),
        color: 'hsl(var(--chart-1))',
        emptyText: 'No data yet.',
    },
);

const max = computed(() => Math.max(1, ...props.bars.map((b) => b.value)));
const width = (v: number) => `${Math.max(v > 0 ? 2 : 0, (v / max.value) * 100)}%`;
</script>

<template>
    <ul v-if="bars.length" class="space-y-2.5">
        <li v-for="(b, i) in bars" :key="i" class="grid grid-cols-[minmax(0,8rem)_1fr_auto] items-center gap-3 text-sm">
            <span class="truncate text-muted-foreground" :title="b.label">{{ b.label }}</span>
            <span class="relative h-2.5 overflow-hidden rounded-full bg-muted">
                <span class="absolute inset-y-0 left-0 rounded-full" :style="{ width: width(b.value), backgroundColor: b.color ?? color }"></span>
            </span>
            <span class="tabular-nums font-medium">{{ format(b.value) }}</span>
        </li>
    </ul>
    <p v-else class="py-6 text-center text-sm text-muted-foreground">{{ emptyText }}</p>
</template>
