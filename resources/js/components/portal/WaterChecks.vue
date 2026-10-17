<script setup lang="ts">
import { reads, type WaterCheck } from '@/lib/portal';
import { ArrowDown, ArrowUp, Check } from 'lucide-vue-next';

/**
 * A water test, one row per thing tested: its plain name and why it matters,
 * the reading with a worded status (never colour alone), and a small range
 * bar — the healthy range shaded, a dot where this reading landed.
 */
withDefaults(defineProps<{ checks: WaterCheck[]; explain?: boolean }>(), { explain: true });

// Where a value sits on the bar: the healthy range in the middle third, with room either side.
function place(c: WaterCheck) {
    const span = Math.max(c.max - c.min, 0.0001);
    const lo = c.min - span;
    const hi = c.max + span;
    const pct = (v: number) => Math.min(100, Math.max(0, ((v - lo) / (hi - lo)) * 100));
    return { band: [pct(c.min), pct(c.max)] as const, dot: pct(c.value) };
}

const rangeText = (c: WaterCheck) => `${c.min}–${c.max}${c.unit ? ` ${c.unit}` : ''}`;
const ariaFor = (c: WaterCheck) => `${c.name} ${reads(c)}, healthy range ${rangeText(c)}: ${c.label.toLowerCase()}`;
</script>

<template>
    <ul class="divide-border divide-y">
        <li
            v-for="c in checks"
            :key="c.key"
            class="grid grid-cols-[1fr_auto] items-center gap-x-6 gap-y-2 py-3 sm:grid-cols-[minmax(0,1fr)_10rem_7.5rem]"
        >
            <div class="min-w-0">
                <p class="font-medium">{{ c.name }}</p>
                <p v-if="explain" class="text-muted-foreground text-sm">{{ c.why }}</p>
            </div>

            <div class="order-3 col-span-2 sm:order-none sm:col-span-1" role="img" :aria-label="ariaFor(c)">
                <div class="bg-muted relative h-2 rounded-full">
                    <div
                        class="absolute inset-y-0 rounded-full bg-[hsl(var(--brand)/0.3)]"
                        :style="{ left: `${place(c).band[0]}%`, width: `${place(c).band[1] - place(c).band[0]}%` }"
                    ></div>
                    <div
                        class="ring-background absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2"
                        :class="c.status === 'good' ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-amber-500'"
                        :style="{ left: `${place(c).dot}%` }"
                    ></div>
                </div>
                <p class="text-muted-foreground mt-1 text-xs">Healthy: {{ rangeText(c) }}</p>
            </div>

            <div class="text-right">
                <p class="font-medium tabular-nums">{{ reads(c) }}</p>
                <p
                    class="inline-flex items-center gap-1 text-sm"
                    :class="c.status === 'good' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'"
                >
                    <Check v-if="c.status === 'good'" class="size-3.5" />
                    <ArrowDown v-else-if="c.status === 'low'" class="size-3.5" />
                    <ArrowUp v-else class="size-3.5" />
                    {{ c.label }}
                </p>
            </div>
        </li>
    </ul>
</template>
