<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

// Seed the open state from the server-shared cookie value so the very first
// render matches the user's last choice — no expand-then-collapse flash. The
// SidebarProvider keeps the `sidebar:state` cookie in sync on every toggle.
const page = usePage<{ sidebarOpen?: boolean }>();
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
