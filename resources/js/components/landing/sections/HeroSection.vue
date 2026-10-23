<script setup lang="ts">
import { computed } from 'vue';
import { badgeLabels } from '../badges';
import { button, frame } from '../layout';
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
    align?: 'left' | 'center' | 'right';
    badges?: string[];
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

// Container units (cqi), so the editor's narrower preview sizes it like a site that wide.
const HEADLINE_SIZES: Record<string, string> = {
    sm: 'clamp(2.4rem, 1.6rem + 3.4cqi, 3.75rem)',
    md: 'clamp(2.7rem, 1.7rem + 4.4cqi, 4.5rem)',
    lg: 'clamp(3rem, 1.8rem + 5.4cqi, 5.5rem)',
    xl: 'clamp(3.3rem, 1.9rem + 6.4cqi, 6.5rem)',
};
const gradientStyle = computed(
    () => `background: linear-gradient(135deg, ${c.value.gradient_start || '#0f172a'}, ${c.value.gradient_end || '#0369a1'})`,
);
const headlineStyle = computed(() => `font-size: ${HEADLINE_SIZES[c.value.headline_size || 'lg'] || HEADLINE_SIZES.lg}; line-height: 1.02`);
const widthStyle = computed(() => `max-width: ${c.value.headline_max_width || 56}rem`);
const overlayStyle = computed(() => `opacity: ${(fx.value.overlay_opacity ?? 40) / 100}`);

const badges = computed(() => badgeLabels(c.value.badges));

// Where the words sit, and the shade behind them that keeps them readable on any photo.
const ALIGN = {
    left: { block: '', narrow: '', row: '', shade: 'bg-[linear-gradient(to_top_right,rgba(0,0,0,0.55),rgba(0,0,0,0.15)_55%,transparent)]' },
    center: {
        block: 'mx-auto text-center',
        narrow: 'mx-auto',
        row: 'justify-center',
        shade: 'bg-[linear-gradient(to_top,rgba(0,0,0,0.55),rgba(0,0,0,0.15)_60%,transparent)]',
    },
    right: {
        block: 'ml-auto text-right',
        narrow: 'ml-auto',
        row: 'justify-end',
        shade: 'bg-[linear-gradient(to_top_left,rgba(0,0,0,0.55),rgba(0,0,0,0.15)_55%,transparent)]',
    },
};
const align = computed(() => ALIGN[c.value.align ?? 'left'] ?? ALIGN.left);
</script>

<template>
    <section id="hero" class="relative flex min-h-[calc(100svh-4rem)] flex-col overflow-hidden bg-slate-900">
        <!-- Background: preset / uploaded image (Ken Burns optional) or a gradient -->
        <img v-if="bgImage" :src="bgImage" alt="" class="absolute inset-0 h-full w-full object-cover" :class="fx.ken_burns ? 'ken-burns' : ''" />
        <div v-else class="absolute inset-0" :style="gradientStyle"></div>
        <div v-if="fx.dark_overlay" class="absolute inset-0 bg-black" :style="overlayStyle"></div>
        <div v-if="fx.dot_matrix" class="hero-dots pointer-events-none absolute inset-0" :class="fx.ken_burns ? 'ken-burns' : ''"></div>
        <div v-if="fx.vignette" class="hero-vignette pointer-events-none absolute inset-0"></div>
        <div class="pointer-events-none absolute inset-0" :class="align.shade"></div>

        <div class="relative z-10 flex flex-1 items-end pt-28 pb-16 @5xl:pb-24" :class="frame">
            <!-- pre-line: a line break typed in the editor starts a new line here. -->
            <div :class="align.block" :style="widthStyle">
                <h1 class="site-heading hero-animate hero-d1 whitespace-pre-line text-white" :style="headlineStyle">
                    {{ c.headline || brand.name }}
                </h1>
                <p
                    v-if="c.subhead"
                    class="hero-animate hero-d2 mt-6 max-w-2xl text-xl leading-relaxed whitespace-pre-line text-white/85"
                    :class="align.narrow"
                >
                    {{ c.subhead }}
                </p>

                <ul v-if="badges.length" class="hero-animate hero-d3 mt-8 flex flex-wrap gap-2" :class="align.row">
                    <li
                        v-for="t in badges"
                        :key="t"
                        class="trust-badge inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold text-white/90"
                    >
                        <svg class="size-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        {{ t }}
                    </li>
                </ul>

                <a :href="anchor" class="hero-animate hero-d4 mt-10 px-9 py-4 text-lg" :class="[button, fx.cta_glow ? 'btn-glow' : '']">
                    {{ c.cta_label || 'Get a free quote' }}
                </a>
            </div>
        </div>

        <a
            v-if="fx.scroll_cue"
            :href="anchor"
            class="scroll-cue absolute bottom-16 left-1/2 z-10 -translate-x-1/2 text-white/50"
            aria-label="Scroll down"
        >
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </a>

        <div class="waterline relative z-10 shrink-0" aria-hidden="true"></div>
    </section>
</template>
