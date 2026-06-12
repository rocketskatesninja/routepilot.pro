<script setup lang="ts">
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

const props = defineProps<{ leads: { data: LeadRow[]; total: number } }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Leads', href: '/leads' }];
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
    <Head title="Leads" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.leads.total} leads`">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Contact</th>
                            <th class="px-4 py-2 font-medium">Source</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="hidden px-4 py-2 font-medium lg:table-cell">Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in props.leads.data" :key="lead.id" class="border-t border-border align-top">
                            <td class="px-4 py-2.5">
                                <div class="font-medium">{{ lead.name }}</div>
                                <div v-if="lead.message" class="max-w-xs truncate text-xs text-muted-foreground">{{ lead.message }}</div>
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ lead.email ?? lead.phone ?? '—' }}</td>
                            <td class="px-4 py-2.5 capitalize text-muted-foreground">{{ lead.source }}</td>
                            <td class="px-4 py-2.5">
                                <select
                                    :value="lead.status"
                                    class="h-8 rounded-md border border-input bg-background px-2 text-xs font-medium capitalize"
                                    :class="statusClass(lead.status)"
                                    @change="setStatus(lead, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ lead.on }}</td>
                        </tr>
                        <tr v-if="props.leads.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                <UserPlus class="mx-auto mb-2 size-6 opacity-50" />
                                No leads yet — they arrive from your public site.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
