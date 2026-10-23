<script setup lang="ts">
// A standard section of a company's site, from its config: its anchor (the key),
// its heading (the company's, else the default) and intro (its blurb, if any).
// `rail`: the heading sits in a left column beside the content (sticky while a
// long section scrolls); `stack`: above full-width content. Tinted sections get
// a faint wash of the brand color.
import { frame } from '../layout';
import { sectionHeading } from '../meta';
import type { SectionConfig } from '../types';

withDefaults(defineProps<{ section: SectionConfig; layout?: 'rail' | 'stack'; tinted?: boolean }>(), { layout: 'rail', tinted: false });
</script>

<template>
    <section :id="section.key" class="scroll-mt-16 py-20 @5xl:py-28" :class="tinted ? 'site-tint' : ''">
        <div :class="[frame, layout === 'rail' ? 'grid gap-10 @5xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] @5xl:gap-20' : '']">
            <header
                :class="
                    layout === 'rail' ? '@5xl:sticky @5xl:top-24 @5xl:self-start' : 'mb-12 flex flex-wrap items-end justify-between gap-x-12 gap-y-4'
                "
            >
                <div class="max-w-xl">
                    <h2 class="site-heading text-foreground">{{ sectionHeading(section) }}</h2>
                    <p v-if="section.blurb" class="text-muted-foreground mt-4 text-lg leading-relaxed">{{ section.blurb }}</p>
                </div>
                <slot name="aside" />
            </header>
            <div class="min-w-0"><slot /></div>
        </div>
    </section>
</template>
