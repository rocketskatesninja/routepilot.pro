<script setup lang="ts">
import { computed } from 'vue';
import { balancedGrid } from '../layout';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; items?: { quote?: string; author?: string; location?: string }[] });
const items = computed(() => c.value.items ?? []);
</script>

<template>
    <SectionShell id="testimonials" layout="stack" :heading="c.heading || 'What our customers say'">
        <ul v-if="items.length" class="gap-6" :class="balancedGrid(items.length, 3)">
            <li v-for="(t, i) in items" :key="i">
                <figure class="site-tint flex h-full flex-col rounded-2xl p-8">
                    <div class="flex gap-1" role="img" aria-label="5 out of 5 stars">
                        <svg v-for="s in 5" :key="s" class="size-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path
                                d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.07 3.29a1 1 0 00.95.69h3.46c.97 0 1.37 1.24.59 1.81l-2.8 2.03a1 1 0 00-.36 1.12l1.07 3.29c.3.92-.76 1.69-1.54 1.12l-2.8-2.03a1 1 0 00-1.18 0l-2.8 2.03c-.78.57-1.84-.2-1.54-1.12l1.07-3.29a1 1 0 00-.36-1.12L2.98 8.7c-.78-.57-.38-1.81.59-1.81h3.46a1 1 0 00.95-.69l1.07-3.29z"
                            />
                        </svg>
                    </div>
                    <blockquote class="text-foreground mt-5 flex-1 text-lg leading-relaxed">“{{ t.quote }}”</blockquote>
                    <figcaption class="mt-6">
                        <span class="text-foreground block font-semibold">{{ t.author }}</span>
                        <span v-if="t.location" class="text-muted-foreground block text-base">{{ t.location }}</span>
                    </figcaption>
                </figure>
            </li>
        </ul>
        <p v-else class="border-border text-muted-foreground rounded-xl border border-dashed p-6 text-sm">Add testimonials in the editor.</p>
    </SectionShell>
</template>
