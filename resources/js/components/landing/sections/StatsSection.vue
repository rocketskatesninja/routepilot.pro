<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { contentItem, contentRow } from '../gridLayout';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { metrics?: string[] });

const LABELS: Record<string, string> = {
    pools_serviced: 'Pools serviced',
    visits_completed: 'Visits completed',
    years_active: 'Years in business',
    happy_customers: 'Happy customers',
    gallons_maintained: 'Gallons maintained',
    water_tests: 'Water tests',
    technicians: 'Expert technicians',
};

const items = computed(() => {
    const s = props.live.stats;
    if (!s) {
        return [];
    }
    const metrics = c.value.metrics?.length ? c.value.metrics : ['pools_serviced', 'visits_completed', 'years_active'];
    return metrics.filter((m) => m in s).map((m) => ({ key: m, label: LABELS[m] ?? m, value: (s as Record<string, number>)[m] }));
});

// Render final values for SSR/SEO; animate from 0 on scroll-in (client only).
const displayed = ref<number[]>([]);
const root = ref<HTMLElement | null>(null);
const show = (i: number, fallback: number): number => displayed.value[i] ?? fallback;

onMounted(() => {
    displayed.value = items.value.map((it) => it.value);
    if (typeof window === 'undefined' || !('IntersectionObserver' in window) || !root.value) {
        return;
    }
    const io = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                io.disconnect();
                animate();
            }
        },
        { threshold: 0.4 },
    );
    io.observe(root.value);
});

function animate() {
    const targets = items.value.map((it) => it.value);
    const dur = 1400;
    const start = performance.now();
    const tick = (now: number) => {
        const p = Math.min((now - start) / dur, 1);
        const ease = 1 - Math.pow(1 - p, 3);
        displayed.value = targets.map((t) => Math.round(ease * t));
        if (p < 1) {
            requestAnimationFrame(tick);
        } else {
            displayed.value = targets;
        }
    };
    requestAnimationFrame(tick);
}
</script>

<template>
    <section v-if="items.length || editing" id="stats" class="border-y border-border bg-muted/40 px-4 py-16 sm:px-6">
        <div ref="root" class="mx-auto max-w-4xl text-center" :class="contentRow">
            <div v-for="(it, i) in items" :key="it.key" :class="contentItem(items.length)">
                <p class="stat-value text-4xl font-extrabold text-primary sm:text-5xl">
                    {{ show(i, it.value).toLocaleString() }}<span v-if="it.key !== 'years_active'">+</span>
                </p>
                <p class="mt-1 text-sm font-medium text-muted-foreground">{{ it.label }}</p>
            </div>
        </div>
    </section>
</template>
