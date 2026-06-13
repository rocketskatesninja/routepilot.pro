<script setup lang="ts">
import { computed } from 'vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { headline?: string; button_label?: string; button_anchor?: string });
</script>

<template>
    <section
        id="cta"
        class="relative overflow-hidden px-4 py-24 text-center sm:px-6"
        style="background: linear-gradient(135deg, hsl(var(--brand, 199 89% 48%)), color-mix(in srgb, hsl(var(--brand, 199 89% 48%)) 60%, #0f172a))"
    >
        <div class="relative z-10 mx-auto max-w-2xl">
            <h2 class="reveal text-3xl font-bold text-white sm:text-4xl">{{ c.headline || 'Ready for a worry-free pool?' }}</h2>
            <a
                :href="`#${c.button_anchor || 'contact'}`"
                class="btn-cta reveal mt-8 inline-flex items-center rounded-xl bg-white px-8 py-4 text-lg font-bold text-slate-900 shadow-lg"
            >
                {{ c.button_label || 'Get started' }}
            </a>
        </div>
    </section>
</template>
