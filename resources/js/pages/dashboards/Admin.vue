<script setup lang="ts">
import DashboardGrid from '@/components/dashboard/DashboardGrid.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface LayoutItem {
    i: string;
    x: number;
    y: number;
    w: number;
    h: number;
}
interface AvailableWidget {
    key: string;
    label: string;
    icon: string;
    w: number;
    h: number;
    minW: number;
    minH: number;
}
interface CatalogMeta {
    label: string;
    icon: string;
    w: number;
    h: number;
    minW: number;
    minH: number;
}

defineProps<{
    layout: LayoutItem[];
    available: AvailableWidget[];
    catalog: Record<string, CatalogMeta>;
    widgets: Record<string, unknown>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <DashboardGrid :layout="layout" :available="available" :catalog="catalog" :widgets="widgets" />
        </div>
    </AppLayout>
</template>
