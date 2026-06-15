<script setup lang="ts">
import BillingBanner from '@/components/BillingBanner.vue';
import FlashToast from '@/components/FlashToast.vue';
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
        <template #actions><slot name="actions" /></template>
        <template #filters><slot name="filters" /></template>
        <ImpersonationBanner />
        <BillingBanner />
        <slot />
        <FlashToast />
    </AppLayout>
</template>
