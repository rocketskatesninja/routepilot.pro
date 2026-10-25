<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex aspect-square size-10 items-center justify-center rounded-md bg-orange-500 text-white group-data-[collapsible=icon]:size-[var(--rail-icon,2rem)]"
    >
        <AppLogoIcon class="size-7 group-data-[collapsible=icon]:size-[calc(var(--rail-icon,2rem)*0.625)]" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-4xl">
        <span class="mb-0.5 truncate leading-none font-semibold"
            ><span class="text-sky-500 dark:text-sky-300">Route</span><span class="text-orange-500">Pilot</span></span
        >
    </div>
</template>
