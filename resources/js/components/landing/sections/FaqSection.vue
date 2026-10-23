<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; items?: { q?: string; a?: string }[] });
const items = computed(() => c.value.items ?? []);

// Answers stay in the DOM (v-show) so they're crawlable.
const open = ref<number | null>(null);
const toggle = (i: number) => (open.value = open.value === i ? null : i);
</script>

<template>
    <SectionShell id="faq" tinted :heading="c.heading || 'Frequently asked questions'">
        <template v-if="onPage.includes('contact')" #aside>
            <p class="text-muted-foreground mt-6 text-lg">
                Don’t see your question?
                <a href="#contact" class="text-primary font-medium underline-offset-4 hover:underline">Ask us directly.</a>
            </p>
        </template>

        <ul v-if="items.length" class="border-border divide-border divide-y border-y">
            <li v-for="(item, i) in items" :key="i">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-6 py-6 text-left"
                    :aria-expanded="open === i"
                    :aria-controls="`faq-${i}`"
                    @click="toggle(i)"
                >
                    <span class="text-foreground text-lg font-semibold">{{ item.q }}</span>
                    <ChevronDown class="text-muted-foreground size-5 shrink-0 transition-transform" :class="open === i ? 'rotate-180' : ''" />
                </button>
                <p v-show="open === i" :id="`faq-${i}`" class="text-muted-foreground max-w-3xl pb-6 leading-relaxed">{{ item.a }}</p>
            </li>
        </ul>
        <p v-else class="border-border text-muted-foreground rounded-xl border border-dashed p-6 text-sm">Add questions in the editor.</p>
    </SectionShell>
</template>
