<script setup lang="ts">
import type { Component } from 'vue';

/** A small icon on the company's colour, the portal's picture for things without a photo. */
withDefaults(defineProps<{ icon: Component; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' });
</script>

<template>
    <span
        class="text-primary grid shrink-0 place-items-center bg-[hsl(var(--brand)/0.12)]"
        :class="{ sm: 'size-9 rounded-lg', md: 'size-10 rounded-xl', lg: 'size-16 rounded-2xl' }[size]"
    >
        <component :is="icon" :class="{ sm: 'size-4', md: 'size-5', lg: 'size-8' }[size]" />
    </span>
</template>
