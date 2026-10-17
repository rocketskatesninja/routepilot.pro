<script setup lang="ts">
/** A titled part of a side panel, with an optional quiet action on the right ("Add a plan"). */
defineProps<{ title: string }>();
</script>

<template>
    <section>
        <div class="mb-2.5 flex items-baseline justify-between gap-3">
            <h3 class="font-display text-lg font-semibold tracking-tight">{{ title }}</h3>
            <slot name="action" />
        </div>
        <slot />
    </section>
</template>
