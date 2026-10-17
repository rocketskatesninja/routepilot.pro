<script setup lang="ts">
/** A portal page title in the display face, with a one-line plain summary under it. */
withDefaults(defineProps<{ title: string; summary?: string; level?: 'h1' | 'h2' }>(), { level: 'h1' });
</script>

<template>
    <header>
        <component :is="level" class="font-display text-[1.75rem] leading-tight font-semibold tracking-tight sm:text-[2rem]">{{ title }}</component>
        <p v-if="summary" class="text-muted-foreground mt-1.5 max-w-[60ch] text-[15px]">{{ summary }}</p>
        <slot />
    </header>
</template>
