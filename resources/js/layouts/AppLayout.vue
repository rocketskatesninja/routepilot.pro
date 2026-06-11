<script setup lang="ts">
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    meta?: string;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :meta="meta">
        <ImpersonationBanner />
        <slot />
    </AppLayout>
</template>
