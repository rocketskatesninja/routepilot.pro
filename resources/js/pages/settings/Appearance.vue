<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { useBigView } from '@/composables/useBigView';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];

const { on: bigView, set: setBigView } = useBigView();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Appearance settings" description="How RoutePilot looks in this browser." />
                <AppearanceTabs />

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        class="mt-0.5 size-5"
                        :checked="bigView"
                        @change="setBigView(($event.target as HTMLInputElement).checked)"
                    />
                    <span class="grid gap-1">
                        <span class="text-sm font-medium">Big view</span>
                        <span class="text-muted-foreground text-sm">Everything 20% bigger, for easier tapping. It’s saved in this browser only.</span>
                    </span>
                </label>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
