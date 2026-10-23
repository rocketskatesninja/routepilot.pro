<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { frame } from '../layout';
import { statItems } from '../stats';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const items = computed(() => statItems(props.content, props.live));

// How each number sits in its column (the company's pick; left by default).
const ALIGN: Record<string, string> = {
    left: '@5xl:first:pl-0',
    center: 'items-center text-center',
    right: 'items-end text-right @5xl:last:pr-0',
};
const align = computed(() => ALIGN[String(props.content.align ?? 'left')] ?? ALIGN.left);

// Count up once as the strip scrolls into view. One 0→1 progress drives every
// number, so a number edited in the editor's preview (which never animates)
// shows straight away. SSR renders the final numbers.
const progress = ref(1);
const root = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;
const shown = (value: number) => Math.round(progress.value * value).toLocaleString('en-US');

onMounted(() => {
    if (props.editing || !root.value || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                observer?.disconnect();
                countUp();
            }
        },
        { threshold: 0.4 },
    );
    observer.observe(root.value);
});
onBeforeUnmount(() => observer?.disconnect());

function countUp() {
    const start = performance.now();
    const tick = (now: number) => {
        const t = Math.min((now - start) / 1400, 1);
        progress.value = 1 - Math.pow(1 - t, 3);
        if (t < 1) requestAnimationFrame(tick);
    };
    progress.value = 0;
    requestAnimationFrame(tick);
}
</script>

<template>
    <!-- Sits on the waterline: one strip of numbers, live from the company's records unless they typed their own. -->
    <section id="stats" class="border-border border-b">
        <div ref="root" :class="frame">
            <dl
                v-if="items.length"
                class="grid grid-cols-2 gap-x-8 @3xl:grid-cols-3 @5xl:grid-cols-[repeat(var(--n),minmax(0,1fr))] @5xl:gap-x-0"
                :style="{ '--n': items.length }"
            >
                <div
                    v-for="it in items"
                    :key="it.key"
                    class="border-border flex flex-col-reverse py-8 @5xl:border-l @5xl:px-8 @5xl:py-12 @5xl:first:border-l-0"
                    :class="align"
                >
                    <dt class="text-muted-foreground mt-1 text-base">{{ it.label }}</dt>
                    <dd class="site-font text-primary text-[2.5rem] leading-none font-bold tracking-tight tabular-nums @5xl:text-5xl">
                        {{ shown(it.value) }}<span v-if="it.plus">+</span>
                    </dd>
                </div>
            </dl>
            <p v-else class="text-muted-foreground py-10 text-sm">No numbers to show. Pick some in the editor, or type your own.</p>
        </div>
    </section>
</template>
