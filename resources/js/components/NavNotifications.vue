<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

// Desktop-only sidebar entry for notifications (mirrors the header bell on mobile).
const page = usePage();
const unread = computed(() => (page.props.auth as { unread?: number } | undefined)?.unread ?? 0);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton as-child :is-active="page.url.startsWith('/notifications')" tooltip="Notifications">
                    <Link href="/notifications">
                        <Bell />
                        <span>Notifications</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge v-if="unread > 0">{{ unread > 9 ? '9+' : unread }}</SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
