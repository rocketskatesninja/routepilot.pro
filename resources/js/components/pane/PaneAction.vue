<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

/**
 * A quiet text action for a panel section — a page link when given `href`
 * (`newTab` for files like a PDF), otherwise a button.
 */
defineProps<{ href?: string; newTab?: boolean; icon?: Component; danger?: boolean }>();
</script>

<template>
    <component
        :is="href ? (newTab ? 'a' : Link) : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        :target="newTab ? '_blank' : undefined"
        :rel="newTab ? 'noopener' : undefined"
        class="focus-visible:ring-ring inline-flex items-center gap-1 rounded text-sm font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
        :class="danger ? 'text-red-600 dark:text-red-400' : 'text-primary'"
    >
        <component :is="icon" v-if="icon" class="size-3.5" />
        <slot />
    </component>
</template>
