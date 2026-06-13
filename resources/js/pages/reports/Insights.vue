<script setup lang="ts">
import { formatMoney } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';

interface LeadRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    message: string | null;
    source: string;
    status: string;
    on: string | null;
}

const props = defineProps<{
    revenue_month: number;
    outstanding: number;
    overdue_invoices: number;
    visits_month: number;
    visits_week: number;
    active_pools: number;
    active_agents: number;
    top_agents: { name: string; visits: number }[];
    leads: LeadRow[];
    new_leads: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Insights', href: '/insights' }];
const money = formatMoney;

const cards = [
    { label: 'Revenue this month', value: money(props.revenue_month), accent: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Outstanding AR', value: money(props.outstanding), accent: props.outstanding > 0 ? 'text-amber-600 dark:text-amber-400' : '' },
    { label: 'Overdue invoices', value: String(props.overdue_invoices), accent: props.overdue_invoices > 0 ? 'text-red-600 dark:text-red-400' : '' },
    { label: 'Visits this month', value: String(props.visits_month), accent: '' },
    { label: 'Visits this week', value: String(props.visits_week), accent: '' },
    { label: 'Active pools', value: String(props.active_pools), accent: '' },
    { label: 'Active agents', value: String(props.active_agents), accent: '' },
];

const statuses = ['new', 'contacted', 'converted', 'archived'];
const statusClass = (s: string) =>
    s === 'converted'
        ? 'text-emerald-600 dark:text-emerald-400'
        : s === 'archived'
          ? 'text-muted-foreground'
          : s === 'contacted'
            ? 'text-sky-600 dark:text-sky-400'
            : 'text-amber-600 dark:text-amber-400';
const setStatus = (lead: LeadRow, status: string) => router.patch(`/leads/${lead.id}`, { status }, { preserveScroll: true });
</script>

<template>
    <Head title="Insights" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-4">
            <div class="grid flex-1 gap-4 xl:min-h-0 xl:grid-cols-5">
                <!-- metrics -->
                <div class="flex flex-col gap-4 xl:col-span-3 xl:min-h-0 xl:overflow-y-auto xl:pr-1">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
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

                <!-- leads inbox -->
                <div class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-border xl:col-span-2">
                    <h2 class="flex items-center justify-between border-b border-border px-4 py-2 font-medium">
                        <span>Leads</span>
                        <span
                            v-if="props.new_leads"
                            class="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                            >{{ props.new_leads }} new</span
                        >
                    </h2>
                    <ul class="min-h-0 flex-1 divide-y divide-border overflow-y-auto text-sm">
                        <li v-for="lead in props.leads" :key="lead.id" class="flex items-start justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <div class="font-medium">{{ lead.name }}</div>
                                <div class="truncate text-xs text-muted-foreground">
                                    {{ lead.email ?? lead.phone ?? '—' }} · <span class="capitalize">{{ lead.source }}</span>
                                    <span v-if="lead.on"> · {{ lead.on }}</span>
                                </div>
                                <div v-if="lead.message" class="mt-0.5 truncate text-xs text-muted-foreground">{{ lead.message }}</div>
                            </div>
                            <select
                                :value="lead.status"
                                class="h-8 shrink-0 rounded-md border border-input bg-background px-2 text-xs font-medium capitalize"
                                :class="statusClass(lead.status)"
                                @change="setStatus(lead, ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </li>
                        <li v-if="props.leads.length === 0" class="px-4 py-10 text-center text-muted-foreground">
                            <UserPlus class="mx-auto mb-2 size-6 opacity-50" />
                            No leads yet — they arrive from your public site.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
