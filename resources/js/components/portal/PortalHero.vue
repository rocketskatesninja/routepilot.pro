<script setup lang="ts">
/**
 * The portal's lead panel: a greeting, one big plain sentence (the thing the
 * customer most wants to know), an optional follow-up line, and the pool
 * waterline-tile strip in the company's colour. Used on Home and Services.
 */
defineProps<{ greeting?: string }>();
</script>

<template>
    <section class="overflow-hidden rounded-2xl bg-[hsl(var(--brand)/0.08)]">
        <div class="px-6 pt-6 pb-7 sm:px-8 sm:pt-7">
            <p v-if="greeting" class="text-muted-foreground text-[15px]">{{ greeting }}</p>
            <h1 class="font-display mt-2 max-w-[24ch] text-[1.75rem] leading-[1.15] font-semibold tracking-tight text-balance sm:text-[2.125rem]">
                <slot />
            </h1>
            <div v-if="$slots.sub" class="text-muted-foreground mt-3 space-y-1 text-[15px]">
                <slot name="sub" />
            </div>
            <div v-if="$slots.actions" class="mt-5 flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </div>
        <div class="waterline h-4" aria-hidden="true"></div>
    </section>
</template>

<style scoped>
/* Pool waterline tile in the company's colour: two rows of small square
   tiles with light grout. The portal's one decorative signature. */
.waterline {
    background:
        linear-gradient(90deg, hsl(var(--background) / 0.7) 1px, transparent 1px) 0 0 / 8px 8px,
        linear-gradient(hsl(var(--background) / 0.7) 1px, transparent 1px) 0 0 / 8px 8px,
        repeating-conic-gradient(hsl(var(--brand) / 0.55) 0 25%, hsl(var(--brand) / 0.28) 0 50%) 0 0 / 16px 16px;
}
</style>
