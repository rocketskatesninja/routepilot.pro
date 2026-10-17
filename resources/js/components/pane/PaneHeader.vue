<script setup lang="ts">
/**
 * The top of a side panel, in the customer portal's style: a picture (avatar or
 * icon tile), the name in the display face, one plain sentence about it, an
 * optional status chip, and the panel's actions underneath.
 */
defineProps<{ title: string; summary?: string | null }>();
</script>

<template>
    <header>
        <div class="rounded-2xl bg-[hsl(var(--brand)/0.08)] p-5">
            <div class="flex items-start gap-4">
                <slot name="media" />
                <!-- pr-7 below xl clears the overlay sheet's close button; the docked pane has none -->
                <div class="min-w-0 flex-1 pr-7 xl:pr-0">
                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                        <h2 class="font-display min-w-0 text-2xl leading-tight font-semibold tracking-tight text-balance">{{ title }}</h2>
                        <slot name="badge" />
                    </div>
                    <p v-if="summary" class="text-muted-foreground mt-1 text-[15px]">{{ summary }}</p>
                    <slot />
                </div>
            </div>
        </div>
        <div v-if="$slots.actions" class="mt-3 flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
