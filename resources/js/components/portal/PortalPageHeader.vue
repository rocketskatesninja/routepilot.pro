<script setup lang="ts">
/** A portal page title in the display face, with a one-line plain summary under it. */
defineProps<{ title: string; summary?: string }>();
</script>

<template>
    <header>
        <h1 class="font-display text-[1.75rem] leading-tight font-semibold tracking-tight sm:text-[2rem]">{{ title }}</h1>
        <p v-if="summary" class="text-muted-foreground mt-1.5 max-w-[60ch] text-[15px]">{{ summary }}</p>
        <slot />
    </header>
</template>
