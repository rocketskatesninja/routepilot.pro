<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const page = usePage<SharedData>();

// The current path with the query string + hash stripped, so master-detail pages
// that append ?selected=… (e.g. /people?selected=5) keep their nav item lit.
const strip = (url: string): string => {
    const path = url.split('?')[0].split('#')[0];
    return path.length > 1 ? path.replace(/\/$/, '') : path;
};
const currentPath = computed(() => strip(page.url));
const isActive = (href: string): boolean => currentPath.value === strip(href);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label">{{ label }}</SidebarGroupLabel>
        <!-- Rows sit flush (no gap) so the tenant admin's full menu fits without scrolling. -->
        <SidebarMenu class="gap-0">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.badge ? `${item.title} (${item.badge})` : item.title">
                    <Link :href="item.href" :class="{ 'pr-11': item.badge }">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                        <span v-if="item.badge" class="sr-only">({{ item.badge }})</span>
                    </Link>
                </SidebarMenuButton>
                <!-- its count: a number at the row's end, or a dot on the icon when the panel is collapsed -->
                <template v-if="item.badge">
                    <span
                        class="bg-primary text-primary-foreground pointer-events-none absolute top-1/2 right-2 flex h-6 min-w-6 -translate-y-1/2 items-center justify-center rounded-full px-1.5 text-xs font-semibold tabular-nums group-data-[collapsible=icon]:hidden"
                        aria-hidden="true"
                        >{{ item.badge > 99 ? '99+' : item.badge }}</span
                    >
                    <span
                        class="bg-primary pointer-events-none absolute top-1 right-1 hidden size-2.5 rounded-full group-data-[collapsible=icon]:block"
                    />
                </template>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
