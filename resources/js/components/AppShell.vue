<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

// Seed from the persisted cookie (shared by the server) so the sidebar renders
// in its last state on the first paint — no open→collapse flash. SidebarProvider
// writes the cookie on every toggle; we only mirror the state for its controlled
// `open` binding.
const page = usePage<SharedData>();
const isOpen = ref(page.props.sidebarOpen ?? true);

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
};
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :open="isOpen" @update:open="handleSidebarChange">
        <slot />
    </SidebarProvider>
</template>
