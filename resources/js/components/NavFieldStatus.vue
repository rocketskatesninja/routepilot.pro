<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAgentTracking } from '@/composables/useAgentTracking';
import { MapPin, Wifi, WifiOff } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Agent-only field-status cluster (connectivity + live location sharing), shown in
// the sidebar footer. Gated by the caller. Location state is the singleton shared
// with the field app; connectivity is this device's online/offline status.
const { sharing, toggle, restore } = useAgentTracking();

const online = ref(true);
const onOnline = () => (online.value = true);
const onOffline = () => (online.value = false);

onMounted(() => {
    online.value = typeof navigator !== 'undefined' ? navigator.onLine : true;
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    restore();
});
onBeforeUnmount(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
});
</script>

<template>
    <!-- p-0 to match the footer inset used by NavNotifications / NavUser. -->
    <SidebarGroup class="p-0">
        <SidebarMenu>
            <!-- Connectivity (informational, non-interactive). -->
            <SidebarMenuItem>
                <SidebarMenuButton as="div" class="cursor-default" :tooltip="online ? 'Online' : 'Offline'">
                    <Wifi v-if="online" class="text-emerald-600 dark:text-emerald-400" />
                    <WifiOff v-else class="text-amber-600 dark:text-amber-400" />
                    <span>{{ online ? 'Online' : 'Offline' }}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <!-- Live location sharing toggle. -->
            <SidebarMenuItem>
                <SidebarMenuButton
                    :is-active="sharing"
                    :tooltip="sharing ? 'Sharing your location — tap to stop' : 'Share your location with dispatch'"
                    :aria-pressed="sharing"
                    @click="toggle"
                >
                    <MapPin :class="sharing ? 'animate-pulse text-sky-600 dark:text-sky-400' : ''" />
                    <span>{{ sharing ? 'Sharing location' : 'Share location' }}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
