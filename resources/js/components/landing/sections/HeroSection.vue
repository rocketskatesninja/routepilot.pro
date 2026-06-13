<script setup lang="ts">
import { computed } from 'vue';
import type { SectionProps } from '../types';

interface Effects {
    dark_overlay?: boolean;
    overlay_opacity?: number;
    cta_glow?: boolean;
    scroll_cue?: boolean;
    ken_burns?: boolean;
    dot_matrix?: boolean;
    vignette?: boolean;
}
interface HeroContent {
    headline?: string;
    subhead?: string;
    cta_label?: string;
    cta_anchor?: string;
    bg_type?: string;
    preset?: string;
    image_url?: string | null;
    gradient_start?: string;
    gradient_end?: string;
    headline_size?: string;
    headline_max_width?: number;
    effects?: Effects;
}

const props = defineProps<SectionProps>();
const c = computed(() => props.content as HeroContent);
const fx = computed<Effects>(() => c.value.effects ?? {});
const anchor = computed(() => `#${c.value.cta_anchor || 'contact'}`);

const bgImage = computed<string | null>(() => {
    if (c.value.bg_type === 'image') {
        return c.value.image_url || null;
    }
    if (c.value.bg_type === 'preset' && c.value.preset) {
        return `/assets/images/hero-presets/${c.value.preset}.jpg`;
    }
    return null; // gradient
});

const HEADLINE_SIZES: Record<string, string> = {
    sm: 'clamp(2.5rem, 5.5vw, 3.5rem)',
    md: 'clamp(3rem, 7vw, 4.5rem)',
    lg: 'clamp(3.6rem, 8.4vw, 5.4rem)',
    xl: 'clamp(4.2rem, 9.5vw, 6rem)',
};
const gradientStyle = computed(
    () => `background: linear-gradient(135deg, ${c.value.gradient_start || '#0f172a'}, ${c.value.gradient_end || '#0369a1'})`,
);
const headlineStyle = computed(
    () => `font-size: ${HEADLINE_SIZES[c.value.headline_size || 'lg'] || HEADLINE_SIZES.lg}; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3)`,
);
const widthStyle = computed(() => `max-width: ${c.value.headline_max_width || 56}rem`);
const overlayStyle = computed(() => `opacity: ${(fx.value.overlay_opacity ?? 40) / 100}`);

const trust = ['Licensed & Insured', '5-Star Rated', 'CPO Certified', 'Same-Day Service'];
</script>

<template>
    <section id="hero" class="relative flex min-h-[100svh] items-center overflow-hidden bg-slate-900 px-4 pb-24 pt-20 sm:px-6">
        <!-- Background: preset / uploaded image (Ken Burns optional) or a gradient -->
        <img v-if="bgImage" :src="bgImage" alt="" class="absolute inset-0 h-full w-full object-cover" :class="fx.ken_burns ? 'ken-burns' : ''" />
        <div v-else class="absolute inset-0" :style="gradientStyle"></div>
        <div v-if="fx.dark_overlay" class="absolute inset-0 bg-black" :style="overlayStyle"></div>
        <div v-if="fx.dot_matrix" class="hero-dots pointer-events-none absolute inset-0" :class="fx.ken_burns ? 'ken-burns' : ''"></div>
        <div v-if="fx.vignette" class="hero-vignette pointer-events-none absolute inset-0"></div>

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
        <div class="relative z-10 mx-auto text-center" :style="widthStyle">
            <h1 class="hero-animate hero-d1 font-bold leading-tight text-white" :style="headlineStyle">{{ c.headline || brand.name }}</h1>
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
                class="btn-cta hero-animate hero-d4 mt-10 inline-flex items-center rounded-xl bg-primary px-8 py-4 text-lg font-bold text-primary-foreground shadow-lg"
                :class="fx.cta_glow ? 'btn-glow' : ''"
            >
                {{ c.cta_label || 'Get a free quote' }}
            </a>
        </div>

        <a
            v-if="fx.scroll_cue"
            :href="anchor"
            class="scroll-cue absolute bottom-10 left-1/2 z-10 -translate-x-1/2 text-white/50"
            aria-label="Scroll down"
        >
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </a>
    </section>
</template>
