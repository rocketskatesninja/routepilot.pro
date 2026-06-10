<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    agent_name: string;
    stats: { today_total: number; completed_today: number; remaining_today: number; week_completed: number };
    today_stops: { id: number; pool: string | null; status: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Today', href: '/dashboard' }];

const statusClasses: Record<string, string> = {
    completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    in_progress: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    pending: 'bg-muted text-muted-foreground',
    skipped: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
};
</script>

<template>
    <Head title="Today" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Good day, {{ agent_name }}</h1>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.today_total }}</div>
                    <div class="text-sm text-muted-foreground">Stops today</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ stats.completed_today }}</div>
                    <div class="text-sm text-muted-foreground">Done</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.remaining_today }}</div>
                    <div class="text-sm text-muted-foreground">Remaining</div>
                </div>
                <div class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold">{{ stats.week_completed }}</div>
                    <div class="text-sm text-muted-foreground">This week</div>
                </div>
            </div>

            <div class="rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Today's route</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="(stop, i) in today_stops" :key="stop.id" class="flex items-center justify-between px-4 py-2.5">
                        <span
                            ><span class="mr-2 text-muted-foreground">{{ i + 1 }}.</span>{{ stop.pool }}</span
                        >
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses[stop.status] ?? 'bg-muted'">
                            {{ stop.status.replace('_', ' ') }}
                        </span>
                    </li>
                    <li v-if="today_stops.length === 0" class="px-4 py-6 text-center text-muted-foreground">No stops scheduled today.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
