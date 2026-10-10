<script setup lang="ts">
import { Droplet, Filter, FlaskConical, ShieldCheck, Sparkles, Sun, Thermometer, Waves, Wrench } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { contentItem, contentRow } from '../gridLayout';
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
        <div class="stagger-children reveal" :class="contentRow">
            <div
                v-for="(it, i) in items"
                :key="i"
                class="card-hover border-border bg-background rounded-xl border p-6 shadow-sm"
                :class="contentItem(items.length)"
            >
                <div class="mb-3 flex items-center gap-3">
                    <div
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg"
                        style="background: color-mix(in srgb, hsl(var(--brand, 199 89% 48%)) 14%, transparent)"
                    >
                        <component :is="icons[it.icon || 'droplet'] || Droplet" class="text-primary size-5" />
                    </div>
                    <h3 class="text-foreground font-bold">{{ it.title }}</h3>
                </div>
                <p class="text-muted-foreground text-sm">{{ it.body }}</p>
            </div>
        </div>
    </SectionShell>
</template>
