<script setup lang="ts">
// A standard section of a company's site. `rail`: the heading sits in a left
// column beside the content (sticky while a long section scrolls); `stack`: the
// heading sits above full-width content. Tinted sections get a faint wash of the
// brand color.
import { frame } from '../layout';

withDefaults(
    defineProps<{
        id: string;
        heading?: string | null;
        intro?: string | null;
        layout?: 'rail' | 'stack';
        tinted?: boolean;
    }>(),
    { layout: 'rail', tinted: false },
);
</script>

<template>
    <section :id="id" class="scroll-mt-16 py-20 @5xl:py-28" :class="tinted ? 'site-tint' : ''">
        <div :class="[frame, layout === 'rail' ? 'grid gap-10 @5xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] @5xl:gap-20' : '']">
            <header
                :class="
                    layout === 'rail' ? '@5xl:sticky @5xl:top-24 @5xl:self-start' : 'mb-12 flex flex-wrap items-end justify-between gap-x-12 gap-y-4'
                "
            >
                <div class="max-w-xl">
                    <h2 v-if="heading" class="site-heading text-foreground">{{ heading }}</h2>
                    <p v-if="intro" class="text-muted-foreground mt-4 text-lg leading-relaxed">{{ intro }}</p>
                </div>
                <slot name="aside" />
            </header>
            <div class="min-w-0"><slot /></div>
        </div>
    </section>
</template>
