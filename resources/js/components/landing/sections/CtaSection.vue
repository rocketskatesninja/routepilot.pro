<script setup lang="ts">
import { computed } from 'vue';
import { frame } from '../layout';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { headline?: string; button_label?: string; button_anchor?: string });
</script>

<template>
    <section id="cta" class="bg-primary text-primary-foreground scroll-mt-16">
        <div class="flex flex-col gap-8 py-16 @3xl:flex-row @3xl:items-center @3xl:justify-between @5xl:py-20" :class="frame">
            <h2 class="site-heading max-w-3xl">{{ c.headline || 'Ready for a worry-free pool?' }}</h2>
            <a
                :href="`#${c.button_anchor || 'contact'}`"
                class="inline-flex shrink-0 items-center justify-center self-start rounded-xl bg-white px-8 py-4 text-lg font-semibold text-slate-900 shadow-sm transition-[filter] hover:brightness-95 focus-visible:ring-3 focus-visible:ring-white/60 focus-visible:outline-none @3xl:self-auto"
            >
                {{ c.button_label || 'Get started' }}
            </a>
        </div>
    </section>
</template>
