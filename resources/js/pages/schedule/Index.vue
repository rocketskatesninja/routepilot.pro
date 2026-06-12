<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, SkipForward, Sparkles, Wand2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Stop {
    id: number;
    order: number;
    pool: string | null;
    customer: string | null;
    status: string;
}

interface RouteCard {
    id: number;
    agent: string;
    completed: number;
    total: number;
    stops: Stop[];
}

const props = defineProps<{
    date: string;
    routes: RouteCard[];
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Schedule', href: '/schedule' }];
const busy = ref(false);

function shift(days: number) {
    const d = new Date(props.date + 'T00:00:00');
    d.setDate(d.getDate() + days);
    router.get('/schedule', { date: d.toISOString().slice(0, 10) }, { preserveState: true, preserveScroll: true });
}

function materialize() {
    if (busy.value) return;
    busy.value = true;
    router.post('/schedule/materialize', {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

const optimize = (id: number) => router.post(`/routes/${id}/optimize`, {}, { preserveScroll: true });
const skipStop = (id: number) => router.post(`/stops/${id}/skip`, {}, { preserveScroll: true });

const prettyDate = computed(() =>
    new Date(props.date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }),
);

const statusClasses: Record<string, string> = {
    completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    in_progress: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    pending: 'bg-muted text-muted-foreground',
    skipped: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
};
</script>

<template>
    <Head title="Schedule" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-end gap-3">
                <div class="flex items-center gap-2">
                    <Button v-if="props.canManage" size="sm" variant="outline" :disabled="busy" @click="materialize"
                        ><Sparkles class="mr-1 size-4" /> Generate</Button
                    >
                    <button class="rounded-md border border-border p-1.5 hover:bg-muted" @click="shift(-1)"><ChevronLeft class="size-4" /></button>
                    <span class="min-w-48 text-center text-sm font-medium">{{ prettyDate }}</span>
                    <button class="rounded-md border border-border p-1.5 hover:bg-muted" @click="shift(1)"><ChevronRight class="size-4" /></button>
                </div>
            </div>

            <div v-if="props.routes.length === 0" class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground">
                <CalendarDays class="size-8 opacity-50" />
                <p>No routes scheduled for this day.</p>
                <Button v-if="props.canManage" size="sm" variant="outline" :disabled="busy" @click="materialize"
                    ><Sparkles class="mr-1 size-4" /> Generate from subscriptions</Button
                >
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="route in props.routes" :key="route.id" class="rounded-xl border border-border">
                    <div class="flex items-center justify-between border-b border-border px-4 py-2">
                        <span class="font-medium">{{ route.agent }}</span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">{{ route.completed }}/{{ route.total }} done</span>
                            <button
                                v-if="props.canManage && route.total > 1"
                                class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="Optimize order"
                                @click="optimize(route.id)"
                            >
                                <Wand2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <ul class="divide-y divide-border text-sm">
                        <li v-for="stop in route.stops" :key="stop.id" class="flex items-center justify-between gap-2 px-4 py-2">
                            <span class="min-w-0 truncate"
                                ><span class="mr-2 text-muted-foreground">{{ stop.order }}.</span>{{ stop.pool }}
                                <span class="text-xs text-muted-foreground">· {{ stop.customer }}</span></span
                            >
                            <div class="flex shrink-0 items-center gap-1.5">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="statusClasses[stop.status] ?? 'bg-muted'"
                                    >{{ stop.status.replace('_', ' ') }}</span
                                >
                                <button
                                    v-if="props.canManage && stop.status === 'pending'"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-amber-600"
                                    title="Skip stop"
                                    @click="skipStop(stop.id)"
                                >
                                    <SkipForward class="size-3.5" />
                                </button>
                            </div>
                        </li>
                        <li v-if="route.stops.length === 0" class="px-4 py-3 text-center text-muted-foreground">No stops.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
