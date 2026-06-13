<script setup lang="ts">
import { computed } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; items?: { quote?: string; author?: string; location?: string }[] });
const items = computed(() => c.value.items ?? []);
</script>

<template>
    <SectionShell
        v-if="items.length || editing"
        id="testimonials"
        width="sm"
        :heading="c.heading || 'What our customers say'"
        subheading="Real reviews from real customers"
    >
        <div v-if="items.length" class="stagger-children reveal grid gap-6 md:grid-cols-3">
            <div
                v-for="(t, i) in items"
                :key="i"
                class="testimonial-card card-hover relative rounded-xl border border-border bg-background p-6 shadow-sm"
            >
                <div class="mb-2 flex gap-1">
                    <svg v-for="s in 5" :key="s" class="size-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.07 3.29a1 1 0 00.95.69h3.46c.97 0 1.37 1.24.59 1.81l-2.8 2.03a1 1 0 00-.36 1.12l1.07 3.29c.3.92-.76 1.69-1.54 1.12l-2.8-2.03a1 1 0 00-1.18 0l-2.8 2.03c-.78.57-1.84-.2-1.54-1.12l1.07-3.29a1 1 0 00-.36-1.12L2.98 8.7c-.78-.57-.38-1.81.59-1.81h3.46a1 1 0 00.95-.69l1.07-3.29z"
                        />
                    </svg>
                </div>
                <p class="relative text-sm leading-relaxed text-foreground">{{ t.quote }}</p>
                <p class="mt-3 text-sm font-semibold text-foreground">
                    {{ t.author }}<span v-if="t.location" class="font-normal text-muted-foreground"> · {{ t.location }}</span>
                </p>
            </div>
        </div>
        <p v-else class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
            Add testimonials in the editor.
        </p>
    </SectionShell>
</template>
