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
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
