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
    <section :id="id" class="px-4 py-20 sm:px-6" :class="tinted ? 'border-y border-border bg-muted/40' : ''">
        <div class="mx-auto" :class="widths[width ?? 'default']">
            <div v-if="heading" class="mb-12 text-center">
                <h2 class="section-heading reveal text-2xl font-bold text-foreground sm:text-3xl">{{ heading }}</h2>
                <p v-if="subheading" class="reveal mt-3 text-muted-foreground">{{ subheading }}</p>
            </div>
            <slot />
        </div>
    </section>
</template>
