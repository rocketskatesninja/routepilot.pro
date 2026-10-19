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
        <!-- Forward the header slots only when the page fills them: an empty #filters
             would still render the header's full-width tabs row and push the actions
             off the title's row on a phone. -->
        <template v-if="$slots.actions" #actions><slot name="actions" /></template>
        <template v-if="$slots.filters" #filters><slot name="filters" /></template>
        <ImpersonationBanner />
        <BillingBanner />
        <slot />
        <FlashToast />
    </AppLayout>
</template>
