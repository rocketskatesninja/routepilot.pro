<script setup lang="ts">
import { Droplet, Filter, FlaskConical, ShieldCheck, Sparkles, Sun, Thermometer, Waves, Wrench } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { button } from '../layout';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';
import { quoteAnchor } from '../visibility';

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
    <SectionShell id="services" :heading="c.heading || 'Our services'">
        <template #aside>
            <a :href="quoteAnchor(onPage)" class="mt-8" :class="button">Get a free quote</a>
        </template>

        <ul v-if="items.length" class="grid gap-x-12 gap-y-10 @3xl:grid-cols-2">
            <li v-for="(it, i) in items" :key="i" class="border-border border-t pt-7">
                <div class="flex items-center gap-4">
                    <span
                        class="text-primary inline-flex size-12 shrink-0 items-center justify-center rounded-xl"
                        style="background: color-mix(in srgb, var(--tenant-primary) 13%, transparent)"
                    >
                        <component :is="icons[it.icon || 'droplet'] || Droplet" class="size-6" />
                    </span>
                    <h3 class="text-foreground text-xl font-semibold">{{ it.title }}</h3>
                </div>
                <p class="text-muted-foreground mt-4 leading-relaxed">{{ it.body }}</p>
            </li>
        </ul>
        <p v-else class="border-border text-muted-foreground rounded-xl border border-dashed p-6 text-sm">Add services in the editor.</p>
    </SectionShell>
</template>
