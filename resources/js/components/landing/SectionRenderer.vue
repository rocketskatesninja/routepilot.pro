<script setup lang="ts">
import type { Component } from 'vue';
import ContactSection from './sections/ContactSection.vue';
import CtaSection from './sections/CtaSection.vue';
import FaqSection from './sections/FaqSection.vue';
import HeroSection from './sections/HeroSection.vue';
import ServicesSection from './sections/ServicesSection.vue';
import TestimonialsSection from './sections/TestimonialsSection.vue';
import type { BrandContext, LiveData, SectionConfig } from './types';

defineProps<{
    sections: SectionConfig[];
    live: LiveData;
    brand: BrandContext;
    editing?: boolean;
}>();

// key → component. Live-data sections (stats, gallery, team, service_area) are
// added in P3; until then they're simply skipped (no entry here).
const registry: Record<string, Component> = {
    hero: HeroSection,
    services: ServicesSection,
    testimonials: TestimonialsSection,
    faq: FaqSection,
    cta: CtaSection,
    contact: ContactSection,
};
</script>

<template>
    <template v-for="s in sections" :key="s.key">
        <component :is="registry[s.key]" v-if="registry[s.key]" :content="s" :live="live" :brand="brand" :editing="editing" />
    </template>
</template>
