<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    stats: { today_stops: number; completed_today: number; remaining_today: number; agents: number; customers: number; pools: number };
    recent_visits: { id: number; pool: string | null; agent: string; completed_on: string | null }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Today</h1>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.today_stops }}</div>
                    <div class="text-sm text-muted-foreground">Stops today</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ stats.completed_today }}</div>
                    <div class="text-sm text-muted-foreground">Completed</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.remaining_today }}</div>
                    <div class="text-sm text-muted-foreground">Remaining</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.agents }}</div>
                    <div class="text-sm text-muted-foreground">Agents</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.customers }}</div>
                    <div class="text-sm text-muted-foreground">Customers</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.pools }}</div>
                    <div class="text-sm text-muted-foreground">Pools</div>
                </div>
            </div>

            <div class="rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Recent service visits</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="v in recent_visits" :key="v.id" class="flex items-center justify-between px-4 py-2.5">
                        <span>{{ v.pool }} <span class="text-muted-foreground">· {{ v.agent }}</span></span>
                        <span class="text-xs text-muted-foreground">{{ v.completed_on }}</span>
                    </li>
                    <li v-if="recent_visits.length === 0" class="px-4 py-6 text-center text-muted-foreground">No visits yet.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
