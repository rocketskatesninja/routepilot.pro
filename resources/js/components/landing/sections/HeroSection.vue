<script setup lang="ts">
import { computed } from 'vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(
    () => props.content as { headline?: string; subhead?: string; cta_label?: string; cta_anchor?: string; image_url?: string | null },
);
const anchor = computed(() => `#${c.value.cta_anchor || 'contact'}`);

const trust = ['Licensed & Insured', '5-Star Rated', 'CPO Certified', 'Same-Day Service'];
</script>

<template>
    <section id="hero" class="relative flex min-h-[100svh] items-center overflow-hidden bg-slate-900 px-4 pb-24 pt-20 sm:px-6">
        <!-- Background: tenant image (Ken Burns) or a brand gradient -->
        <img v-if="c.image_url" :src="c.image_url" alt="" class="ken-burns absolute inset-0 h-full w-full object-cover" />
        <div v-else class="absolute inset-0" style="background: linear-gradient(135deg, #0f172a, hsl(var(--brand, 199 89% 48%) / 0.65))"></div>
        <div class="absolute inset-0 bg-black/40"></div>

        <!-- Ambient blobs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="blob absolute rounded-full"
                style="top: -10rem; right: -10rem; width: 24rem; height: 24rem; background: rgba(255, 255, 255, 0.1); filter: blur(48px)"
            ></div>
            <div
                class="blob-2 absolute rounded-full"
                style="bottom: -5rem; left: -5rem; width: 18rem; height: 18rem; background: rgba(255, 255, 255, 0.1); filter: blur(48px)"
            ></div>
            <div
                class="blob-3 absolute rounded-full"
                style="top: 50%; left: 50%; width: 16rem; height: 16rem; background: rgba(255, 255, 255, 0.05); filter: blur(48px)"
            ></div>
        </div>

        <!-- Content -->
        <div class="relative z-10 mx-auto max-w-3xl text-center">
            <h1
                class="hero-animate hero-d1 text-4xl font-bold leading-tight text-white sm:text-6xl"
                style="text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3)"
            >
                {{ c.headline || brand.name }}
            </h1>
            <p v-if="c.subhead" class="hero-animate hero-d2 mx-auto mt-4 max-w-2xl text-lg text-white/80">{{ c.subhead }}</p>

            <div class="hero-animate hero-d3 mt-6 flex flex-wrap justify-center gap-2">
                <span
                    v-for="t in trust"
                    :key="t"
                    class="trust-badge inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold text-white/90"
                >
                    <svg class="size-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    {{ t }}
                </span>
            </div>

            <a
                :href="anchor"
                class="btn-cta btn-glow hero-animate hero-d4 mt-10 inline-flex items-center rounded-xl bg-primary px-8 py-4 text-lg font-bold text-primary-foreground shadow-lg"
            >
                {{ c.cta_label || 'Get a free quote' }}
            </a>
        </div>

        <a :href="anchor" class="scroll-cue absolute bottom-10 left-1/2 z-10 -translate-x-1/2 text-white/50" aria-label="Scroll down">
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </a>
    </section>
</template>
