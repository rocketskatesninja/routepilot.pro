<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    stats: { tenants: number; active_tenants: number; users: number; pools: number; visits_this_week: number };
    recent_tenants: { id: number; name: string; status: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Platform', href: '/dashboard' }];
</script>

<template>
    <Head title="Platform" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.tenants }}</div>
                    <div class="text-sm text-muted-foreground">Tenants</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.active_tenants }}</div>
                    <div class="text-sm text-muted-foreground">Active</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.users }}</div>
                    <div class="text-sm text-muted-foreground">Users</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.pools }}</div>
                    <div class="text-sm text-muted-foreground">Pools</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.visits_this_week }}</div>
                    <div class="text-sm text-muted-foreground">Visits this week</div>
                </div>
            </div>

            <div class="rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Recent tenants</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="t in recent_tenants" :key="t.id" class="flex items-center justify-between px-4 py-2.5">
                        <span>{{ t.name }}</span>
                        <span class="text-xs capitalize text-muted-foreground">{{ t.status }}</span>
                    </li>
                    <li v-if="recent_tenants.length === 0" class="px-4 py-6 text-center text-muted-foreground">No tenants yet.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
