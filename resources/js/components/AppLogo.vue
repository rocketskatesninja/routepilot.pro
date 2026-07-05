<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-orange-500 text-white">
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none"
            ><span class="text-sky-500 dark:text-sky-300">Route</span><span class="text-orange-500">Pilot</span></span
        >
    </div>
</template>
