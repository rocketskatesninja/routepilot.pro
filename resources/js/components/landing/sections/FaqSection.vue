<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; items?: { q?: string; a?: string }[] });
const items = computed(() => c.value.items ?? []);

// Accordion open index — client-only interaction; answers stay in the DOM
// (v-show) so they remain crawlable for SEO.
const open = ref<number | null>(null);
const toggle = (i: number) => (open.value = open.value === i ? null : i);
</script>

<template>
    <SectionShell
        v-if="items.length || editing"
        id="faq"
        width="xs"
        tinted
        :heading="c.heading || 'Frequently asked questions'"
        subheading="Everything you need to know"
    >
        <div v-if="items.length" class="space-y-3">
            <div v-for="(item, i) in items" :key="i" class="reveal overflow-hidden rounded-xl border border-border bg-background shadow-sm">
                <button type="button" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left" @click="toggle(i)">
                    <span class="text-sm font-semibold text-foreground">{{ item.q }}</span>
                    <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform" :class="open === i ? 'rotate-180' : ''" />
                </button>
                <p v-show="open === i" class="px-5 pb-4 text-sm leading-relaxed text-muted-foreground">{{ item.a }}</p>
            </div>
        </div>
        <p v-else class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">Add questions in the editor.</p>
    </SectionShell>
</template>
