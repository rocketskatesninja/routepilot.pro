<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/**
 * One row in a PaneList: an optional icon, a title with a plain line under it,
 * and something on the right (an amount, a date, a chip). With `href` the whole
 * row opens that page.
 */
defineProps<{ title?: string | null; sub?: string | null; href?: string | null }>();
</script>

<template>
    <li>
        <component
            :is="href ? Link : 'div'"
            :href="href ?? undefined"
            class="flex items-center gap-3 px-4 py-3 text-sm"
            :class="href ? 'hover:bg-muted/50 focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset' : ''"
        >
            <slot name="icon" />
            <div class="min-w-0 flex-1">
                <p class="font-medium">
                    <slot name="title">{{ title }}</slot>
                </p>
                <p v-if="sub || $slots.sub" class="text-muted-foreground">
                    <slot name="sub">{{ sub }}</slot>
                </p>
                <slot />
            </div>
            <div v-if="$slots.trailing" class="shrink-0 text-right"><slot name="trailing" /></div>
        </component>
    </li>
</template>
