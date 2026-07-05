<script setup lang="ts">
import { Droplet, Filter, FlaskConical, ShieldCheck, Sparkles, Sun, Thermometer, Waves, Wrench } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; items?: { title?: string; body?: string; icon?: string }[] });
const items = computed(() => c.value.items ?? []);

const icons: Record<string, Component> = {
    droplet: Droplet,
    wrench: Wrench,
    sparkles: Sparkles,
    shield: ShieldCheck,
    waves: Waves,
    sun: Sun,
    flask: FlaskConical,
    thermometer: Thermometer,
    filter: Filter,
};
</script>

<template>
    <SectionShell
        v-if="items.length || editing"
        id="services"
        tinted
        :heading="c.heading || 'Our services'"
        subheading="Professional care tailored to your pool"
    >
        <div class="stagger-children reveal grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="(it, i) in items" :key="i" class="card-hover rounded-xl border border-border bg-background p-6 shadow-sm">
                <div
                    class="mb-3 inline-flex size-11 items-center justify-center rounded-lg"
                    style="background: color-mix(in srgb, hsl(var(--brand, 199 89% 48%)) 14%, transparent)"
                >
                    <component :is="icons[it.icon || 'droplet'] || Droplet" class="size-5 text-primary" />
                </div>
                <h3 class="mb-1 font-bold text-foreground">{{ it.title }}</h3>
                <p class="text-sm text-muted-foreground">{{ it.body }}</p>
            </div>
        </div>
    </SectionShell>
</template>
