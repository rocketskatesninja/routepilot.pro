<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage<SharedData>();
// Inside a company the mark is its logo, once uploaded; a customer's portal carries the
// company's name too, since that's who they deal with. Staff keep the RoutePilot name.
const logo = computed(() => (page.props.tenant?.logo_path ? `/storage/${page.props.tenant.logo_path}` : null));
const company = computed(() => (page.props.auth.role === 'customer' ? (page.props.tenant?.name ?? null) : null));
</script>

<template>
    <img
        v-if="logo"
        :src="logo"
        alt=""
        class="aspect-square size-10 shrink-0 rounded-md object-contain transition-[width,height] duration-[var(--sidebar-duration)] ease-[var(--sidebar-ease)] group-data-[collapsible=icon]:size-[var(--rail-icon,2rem)]"
    />
    <div
        v-else
        class="flex aspect-square size-10 shrink-0 items-center justify-center rounded-md bg-orange-500 text-white transition-[width,height] duration-[var(--sidebar-duration)] ease-[var(--sidebar-ease)] group-data-[collapsible=icon]:size-[var(--rail-icon,2rem)]"
    >
        <AppLogoIcon class="size-7 group-data-[collapsible=icon]:size-[calc(var(--rail-icon,2rem)*0.625)]" />
    </div>
    <div
        class="ml-2 grid flex-1 text-left text-4xl whitespace-nowrap transition-opacity duration-[var(--sidebar-duration)] group-data-[collapsible=icon]:opacity-0"
    >
        <span v-if="company" class="line-clamp-2 text-xl leading-[1.1] font-semibold" :title="company">{{ company }}</span>
        <span v-else class="mb-0.5 truncate leading-none font-semibold"
            ><span class="text-sky-500 dark:text-sky-300">Route</span><span class="text-orange-500">Pilot</span></span
        >
    </div>
</template>
