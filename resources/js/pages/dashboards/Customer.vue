<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Health {
    color: 'red' | 'amber' | 'green';
    label: string;
}

defineProps<{
    customer_name: string;
    pools: { id: number; name: string; health: Health | null }[];
    next_visit: { pool: string | null; date: string | null } | null;
    recent_visits: { id: number; pool: string | null; completed_on: string | null }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Pools', href: '/dashboard' }];

const healthClasses: Record<Health['color'], string> = {
    green: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    red: 'bg-red-500/15 text-red-600 dark:text-red-400',
};
</script>

<template>
    <Head title="My Pools" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Hi {{ customer_name }}</h1>

            <div v-if="next_visit" class="rounded-xl border border-border bg-muted/30 p-4 text-sm">
                Next service: <span class="font-medium">{{ next_visit.pool }}</span> on
                <span class="font-medium">{{ next_visit.date }}</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="pool in pools" :key="pool.id" class="rounded-xl border border-border p-4">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ pool.name }}</span>
                        <span
                            v-if="pool.health"
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="healthClasses[pool.health.color]"
                        >
                            {{ pool.health.label }}
                        </span>
                        <span v-else class="text-xs text-muted-foreground">No reading yet</span>
                    </div>
                </div>
                <div v-if="pools.length === 0" class="text-sm text-muted-foreground">No pools on file.</div>
            </div>

            <div class="rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Recent service</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="v in recent_visits" :key="v.id" class="flex items-center justify-between px-4 py-2.5">
                        <span>{{ v.pool }}</span>
                        <span class="text-xs text-muted-foreground">{{ v.completed_on }}</span>
                    </li>
                    <li v-if="recent_visits.length === 0" class="px-4 py-6 text-center text-muted-foreground">No visits yet.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
