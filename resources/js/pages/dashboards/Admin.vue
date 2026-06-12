<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    stats: { today_stops: number; completed_today: number; remaining_today: number; agents: number; customers: number; pools: number };
    my_route_label: string | null;
    my_stops: { id: number; pool: string | null; pool_photo: string | null; status: string }[];
    recent_visits: {
        id: number;
        pool: string | null;
        pool_photo: string | null;
        agent: string;
        agent_photo: string | null;
        completed_on: string | null;
    }[];
    pending_requests: {
        id: number;
        type: string;
        message: string;
        customer: string | null;
        customer_photo: string | null;
        pool: string | null;
        pool_photo: string | null;
        preferred_date: string | null;
        on: string | null;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const resolveRequest = (id: number) => router.post(`/requests/${id}/resolve`, {}, { preserveScroll: true });

const statusClasses: Record<string, string> = {
    completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    in_progress: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    pending: 'bg-muted text-muted-foreground',
    skipped: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
};
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
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

            <div v-if="my_stops.length" class="rounded-xl border border-sky-500/40 bg-sky-500/5">
                <h2 class="border-b border-border px-4 py-2 font-medium">My route {{ my_route_label }} · {{ my_stops.length }} stop(s)</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="(stop, i) in my_stops" :key="stop.id">
                        <Link
                            :href="`/visit/${stop.id}`"
                            class="flex items-center justify-between gap-2 px-4 py-2.5 transition-colors hover:bg-muted/40"
                        >
                            <span class="flex items-center gap-2">
                                <span class="text-muted-foreground">{{ i + 1 }}.</span>
                                <EntityAvatar :src="stop.pool_photo" type="pool" :name="stop.pool" size="sm" />
                                <span>{{ stop.pool }}</span>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="statusClasses[stop.status] ?? 'bg-muted'"
                                    >{{ stop.status.replace('_', ' ') }}</span
                                >
                                <ChevronRight class="size-4 text-muted-foreground" />
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>

            <div v-if="pending_requests.length" class="rounded-xl border border-amber-500/40 bg-amber-500/5">
                <h2 class="border-b border-border px-4 py-2 font-medium">Customer requests · {{ pending_requests.length }} pending</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="r in pending_requests" :key="r.id" class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="
                                    r.type === 'hold'
                                        ? 'bg-sky-500/15 text-sky-600 dark:text-sky-400'
                                        : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                "
                            >
                                {{ r.type === 'hold' ? 'Vacation hold' : 'New service' }}
                            </span>
                            <span class="ml-2 inline-flex items-center gap-1.5 align-middle font-medium"
                                ><EntityAvatar :src="r.customer_photo" type="person" :name="r.customer" size="sm" shape="circle" />{{
                                    r.customer
                                }}</span
                            >
                            <span v-if="r.pool" class="text-muted-foreground"> · {{ r.pool }}</span>
                            <p class="mt-0.5 text-muted-foreground">{{ r.message }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ r.on }}<span v-if="r.preferred_date"> · prefers {{ r.preferred_date }}</span>
                            </p>
                        </div>
                        <Button size="sm" variant="outline" @click="resolveRequest(r.id)">Resolve</Button>
                    </li>
                </ul>
            </div>

            <div class="rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Recent service visits</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="v in recent_visits" :key="v.id" class="flex items-center justify-between px-4 py-2.5">
                        <span class="flex items-center gap-2">
                            <EntityAvatar :src="v.pool_photo" type="pool" :name="v.pool" size="sm" />
                            <span
                                >{{ v.pool }} <span class="text-muted-foreground">· {{ v.agent }}</span></span
                            >
                        </span>
                        <span class="text-xs text-muted-foreground">{{ v.completed_on }}</span>
                    </li>
                    <li v-if="recent_visits.length === 0" class="px-4 py-6 text-center text-muted-foreground">No visits yet.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
