<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    revenue_month: number;
    outstanding: number;
    overdue_invoices: number;
    visits_month: number;
    visits_week: number;
    active_pools: number;
    active_agents: number;
    top_agents: { name: string; visits: number }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Insights', href: '/insights' }];
const money = (n: number) => `$${n.toFixed(2)}`;

const cards = [
    { label: 'Revenue this month', value: money(props.revenue_month), accent: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Outstanding AR', value: money(props.outstanding), accent: props.outstanding > 0 ? 'text-amber-600 dark:text-amber-400' : '' },
    { label: 'Overdue invoices', value: String(props.overdue_invoices), accent: props.overdue_invoices > 0 ? 'text-red-600 dark:text-red-400' : '' },
    { label: 'Visits this month', value: String(props.visits_month), accent: '' },
    { label: 'Visits this week', value: String(props.visits_week), accent: '' },
    { label: 'Active pools', value: String(props.active_pools), accent: '' },
    { label: 'Active agents', value: String(props.active_agents), accent: '' },
];
</script>

<template>
    <Head title="Insights" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                <div v-for="c in cards" :key="c.label" class="rounded-xl border border-border p-4">
                    <div class="text-2xl font-semibold" :class="c.accent">{{ c.value }}</div>
                    <div class="text-sm text-muted-foreground">{{ c.label }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Top techs this month</h2>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="(a, i) in props.top_agents" :key="i" class="border-t border-border first:border-t-0">
                            <td class="px-4 py-2.5 font-medium">{{ a.name }}</td>
                            <td class="px-4 py-2.5 text-right text-muted-foreground">{{ a.visits }} visits</td>
                        </tr>
                        <tr v-if="props.top_agents.length === 0">
                            <td class="px-4 py-8 text-center text-muted-foreground">No completed visits this month.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
