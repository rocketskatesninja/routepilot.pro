<script setup lang="ts">
interface Tile {
    label: string;
    value: number | string;
    accent?: string;
}

defineProps<{ data: { tiles: Tile[] } }>();
</script>

<template>
    <!-- auto-fit columns adapt to the tile count + the widget width; the number
         scales with each tile via container-query units. -->
    <div class="grid h-full auto-rows-fr grid-cols-[repeat(auto-fit,minmax(4.5rem,1fr))] gap-2">
        <div
            v-for="t in data.tiles"
            :key="t.label"
            class="flex h-full flex-col items-center rounded-lg border border-border bg-background/40 px-2 py-2 text-center"
            style="container-type: size"
        >
            <div class="text-xs leading-tight text-muted-foreground">{{ t.label }}</div>
            <div
                class="flex flex-1 items-center font-semibold leading-none tabular-nums"
                :class="t.accent"
                style="font-size: clamp(1.25rem, min(26cqh, 24cqw), 3.25rem)"
            >
                {{ t.value }}
            </div>
        </div>
    </div>
</template>
