<script setup lang="ts">
// Shared wrapper for standard sections: alternating background, clamped width,
// and a centered heading with the brand gradient underline.
withDefaults(
    defineProps<{
        heading?: string | null;
        subheading?: string | null;
        tinted?: boolean;
        width?: 'default' | 'sm' | 'xs';
        id?: string;
    }>(),
    { tinted: false, width: 'default' },
);

const widths: Record<'default' | 'sm' | 'xs', string> = {
    default: 'max-w-5xl',
    sm: 'max-w-3xl',
    xs: 'max-w-2xl',
};
</script>

<template>
    <section :id="id" class="px-4 py-20 sm:px-6" :class="tinted ? 'border-border bg-muted/40 border-y' : ''">
        <div class="mx-auto" :class="widths[width ?? 'default']">
            <div v-if="heading" class="mb-12 text-center">
                <h2 class="section-heading reveal text-foreground text-2xl font-bold sm:text-3xl">{{ heading }}</h2>
                <p v-if="subheading" class="reveal text-muted-foreground mt-3">{{ subheading }}</p>
            </div>
            <slot />
        </div>
    </section>
</template>
