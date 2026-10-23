<script setup lang="ts">
import { computed, type Component } from 'vue';
import BookingSection from './sections/BookingSection.vue';
import ContactSection from './sections/ContactSection.vue';
import CtaSection from './sections/CtaSection.vue';
import FaqSection from './sections/FaqSection.vue';
import GallerySection from './sections/GallerySection.vue';
import HeroSection from './sections/HeroSection.vue';
import QuoteSection from './sections/QuoteSection.vue';
import ServiceAreaSection from './sections/ServiceAreaSection.vue';
import ServicesSection from './sections/ServicesSection.vue';
import StatsSection from './sections/StatsSection.vue';
import TeamSection from './sections/TeamSection.vue';
import TestimonialsSection from './sections/TestimonialsSection.vue';
import type { BrandContext, LiveData, SectionConfig } from './types';

const props = defineProps<{
    sections: SectionConfig[];
    live: LiveData;
    brand: BrandContext;
    editing?: boolean;
}>();

const onPage = computed(() => props.sections.map((s) => s.key));

// key → component. Unknown keys are skipped by the renderer.
const registry: Record<string, Component> = {
    hero: HeroSection,
    stats: StatsSection,
    services: ServicesSection,
    quote: QuoteSection,
    gallery: GallerySection,
    team: TeamSection,
    service_area: ServiceAreaSection,
    booking: BookingSection,
    testimonials: TestimonialsSection,
    faq: FaqSection,
    cta: CtaSection,
    contact: ContactSection,
};
</script>

<template>
    <template v-for="s in sections" :key="s.key">
        <component :is="registry[s.key]" v-if="registry[s.key]" :content="s" :live="live" :brand="brand" :on-page="onPage" :editing="editing" />
    </template>
</template>
