<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Chip from '@/components/pane/Chip.vue';
import { workOrderLink } from '@/lib/links';
import { formatDay } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';

interface Order {
    id: number;
    title: string;
    urgent: boolean;
    overdue: boolean;
    due_on: string | null;
    pool: string | null;
    customer: string | null;
    assignee: string | null;
}

// `mine`: a tech's own list, so who it's assigned to goes without saying.
const props = defineProps<{ data: { orders: Order[]; open: number; mine: boolean } }>();

const line = (o: Order): string => [o.pool, o.customer, props.data.mine ? null : (o.assignee ?? 'Not assigned')].filter(Boolean).join(' · ');
</script>

<template>
    <div class="flex h-full flex-col">
        <ul v-if="data.orders.length" class="divide-border min-h-0 flex-1 divide-y overflow-y-auto text-sm">
            <li v-for="o in data.orders" :key="o.id">
                <Link :href="workOrderLink(o.id)" class="hover:bg-muted/50 flex items-start justify-between gap-3 rounded-md px-1 py-2.5">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2">
                            <span class="truncate font-medium">{{ o.title }}</span>
                            <Chip v-if="o.urgent" tone="bad">Urgent</Chip>
                        </p>
                        <p v-if="line(o)" class="text-muted-foreground truncate text-xs">{{ line(o) }}</p>
                    </div>
                    <Chip v-if="o.overdue" tone="warn">Overdue</Chip>
                    <span v-else-if="o.due_on" class="text-muted-foreground shrink-0 text-xs">Due {{ formatDay(o.due_on) }}</span>
                </Link>
            </li>
        </ul>
        <EmptyState v-else class="flex-1 py-6">{{ data.mine ? 'Nothing assigned to you.' : 'No open work orders.' }}</EmptyState>
        <Link v-if="data.open > data.orders.length" href="/work-orders" class="text-primary border-border border-t px-1 pt-2 text-sm font-medium">
            See all {{ data.open }} open
        </Link>
    </div>
</template>
