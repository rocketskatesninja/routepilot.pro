<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, GripVertical, Inbox, Map as MapIcon, SkipForward, Sparkles, Wand2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';

interface Stop {
    id: number;
    order: number;
    pool: string | null;
    pool_photo: string | null;
    customer: string | null;
    status: string;
}

interface RouteCard {
    id: number;
    agent: string | null;
    agent_photo: string | null;
    completed: number;
    total: number;
    stops: Stop[];
}

const props = defineProps<{
    date: string;
    today: string;
    routes: RouteCard[];
    unassigned: RouteCard | null;
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Schedule', href: '/schedule' }];
const busy = ref(false);
const dateInput = ref<HTMLInputElement | null>(null);

// A local, mutable copy of the routes that drag-and-drop reorders optimistically;
// it re-syncs from the server (the source of truth) whenever the props change.
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value));
const localRoutes = ref<RouteCard[]>(clone(props.routes));
watch(
    () => props.routes,
    (value) => (localRoutes.value = clone(value)),
);

// The per-day "unassigned" bucket is just a route whose agent_id is null; it
// rides the same drag group so a stop dragged onto/off it is (un)assigned.
const localUnassigned = ref<RouteCard | null>(props.unassigned ? clone(props.unassigned) : null);
watch(
    () => props.unassigned,
    (value) => (localUnassigned.value = value ? clone(value) : null),
);

const isToday = computed(() => props.date === props.today);
const totalStops = computed(() => props.routes.reduce((n, r) => n + r.total, 0));
const completedStops = computed(() => props.routes.reduce((n, r) => n + r.completed, 0));
const doneCount = (stops: Stop[]) => stops.filter((s) => s.status === 'completed').length;

function go(date?: string) {
    router.get('/schedule', date ? { date } : {}, { preserveState: true, preserveScroll: true });
}

function shift(days: number) {
    const d = new Date(props.date + 'T00:00:00');
    d.setDate(d.getDate() + days);
    go(d.toISOString().slice(0, 10));
}

function openCalendar() {
    try {
        dateInput.value?.showPicker();
    } catch {
        dateInput.value?.focus();
    }
}
const onPickDate = (e: Event) => {
    const value = (e.target as HTMLInputElement).value;
    if (value) go(value);
};

function materialize() {
    if (busy.value) return;
    busy.value = true;
    router.post('/schedule/materialize', {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

// Persist the current arrangement after a drag (debounced — a cross-route move
// fires a change on both lists). Idempotent on the server, so a double send is safe.
let persistTimer: ReturnType<typeof setTimeout> | undefined;
function persist() {
    clearTimeout(persistTimer);
    persistTimer = setTimeout(() => {
        const routes = localRoutes.value.map((r) => ({ id: r.id, stop_ids: r.stops.map((s) => s.id) }));
        if (localUnassigned.value) {
            routes.push({ id: localUnassigned.value.id, stop_ids: localUnassigned.value.stops.map((s) => s.id) });
        }
        router.post('/schedule/arrange', { routes }, { preserveScroll: true, preserveState: true });
    }, 120);
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
        <template #actions>
            <Button v-if="props.canManage" size="sm" variant="outline" :disabled="busy" @click="materialize"
                ><Sparkles class="mr-1 size-4" /> Generate</Button
            >
            <div class="flex items-center gap-1">
                <button class="rounded-md border border-border p-1.5 hover:bg-muted" aria-label="Previous day" @click="shift(-1)">
                    <ChevronLeft class="size-4" />
                </button>
                <button
                    class="flex min-w-44 items-center justify-center gap-1.5 rounded-md border border-border px-2 py-1.5 text-sm font-medium hover:bg-muted"
                    @click="openCalendar"
                >
                    {{ prettyDate }}
                    <CalendarDays class="size-3.5 text-muted-foreground" />
                </button>
                <input ref="dateInput" type="date" :value="props.date" tabindex="-1" class="sr-only" @change="onPickDate" />
                <button class="rounded-md border border-border p-1.5 hover:bg-muted" aria-label="Next day" @click="shift(1)">
                    <ChevronRight class="size-4" />
                </button>
                <Button v-if="!isToday" size="sm" variant="ghost" @click="go()">Today</Button>
            </div>
        </template>

        <div class="flex h-full flex-1 gap-4 p-4">
            <!-- Left: the day's routes as a vertical, drag-and-drop list -->
            <div class="flex w-full min-w-0 flex-col gap-3 overflow-y-auto lg:w-[26rem] lg:shrink-0">
                <div
                    v-if="localRoutes.length === 0 && !localUnassigned"
                    class="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center text-muted-foreground"
                >
                    <CalendarDays class="size-8 opacity-50" />
                    <p>No routes scheduled for this day.</p>
                    <Button v-if="props.canManage" size="sm" variant="outline" :disabled="busy" @click="materialize"
                        ><Sparkles class="mr-1 size-4" /> Generate from subscriptions</Button
                    >
                </div>

                <!-- Unassigned bucket: the day's stops on no agent. A persistent
                     drop target — dragging a stop here un-assigns it. -->
                <div v-if="localUnassigned" class="rounded-xl border border-dashed border-border bg-muted/20">
                    <div class="flex items-center justify-between border-b border-border px-4 py-2">
                        <div class="flex items-center gap-2">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Inbox class="size-4" />
                            </span>
                            <span class="font-medium">Unassigned stops</span>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ localUnassigned.stops.length }}</span>
                    </div>

                    <draggable
                        :list="localUnassigned.stops"
                        tag="ul"
                        item-key="id"
                        group="route-stops"
                        :animation="150"
                        :disabled="!props.canManage"
                        handle=".drag-handle"
                        ghost-class="bg-muted/60"
                        class="min-h-[2.75rem] divide-y divide-border text-sm"
                        @change="persist"
                    >
                        <template #item="{ element: stop, index }">
                            <li class="flex items-center justify-between gap-2 px-4 py-2">
                                <span class="flex min-w-0 items-center gap-2 truncate">
                                    <GripVertical
                                        v-if="props.canManage && stop.status === 'pending'"
                                        class="drag-handle size-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing"
                                    />
                                    <span v-else class="size-4 shrink-0" aria-hidden="true" />
                                    <span class="text-muted-foreground">{{ index + 1 }}.</span>
                                    <EntityAvatar :src="stop.pool_photo" type="pool" :name="stop.pool" size="sm" />
                                    <span class="truncate"
                                        >{{ stop.pool }} <span class="text-xs text-muted-foreground">· {{ stop.customer }}</span></span
                                    >
                                </span>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                        :class="statusClasses[stop.status] ?? 'bg-muted'"
                                        >{{ stop.status.replace('_', ' ') }}</span
                                    >
                                </div>
                            </li>
                        </template>
                    </draggable>
                    <p v-if="localUnassigned.stops.length === 0" class="px-4 py-3 text-center text-xs text-muted-foreground">
                        No unassigned stops for this day.
                    </p>
                </div>

                <div v-for="route in localRoutes" :key="route.id" class="rounded-xl border border-border">
                    <div class="flex items-center justify-between border-b border-border px-4 py-2">
                        <div class="flex items-center gap-2">
                            <EntityAvatar :src="route.agent_photo" type="person" :name="route.agent" size="sm" shape="circle" />
                            <span class="font-medium">{{ route.agent }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">{{ doneCount(route.stops) }}/{{ route.stops.length }} done</span>
                            <button
                                v-if="props.canManage && route.stops.length > 1"
                                class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="Optimize order"
                                @click="optimize(route.id)"
                            >
                                <Wand2 class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <draggable
                        :list="route.stops"
                        tag="ul"
                        item-key="id"
                        group="route-stops"
                        :animation="150"
                        :disabled="!props.canManage"
                        handle=".drag-handle"
                        ghost-class="bg-muted/60"
                        class="min-h-[2.75rem] divide-y divide-border text-sm"
                        @change="persist"
                    >
                        <template #item="{ element: stop, index }">
                            <li class="flex items-center justify-between gap-2 px-4 py-2">
                                <span class="flex min-w-0 items-center gap-2 truncate">
                                    <GripVertical
                                        v-if="props.canManage && stop.status === 'pending'"
                                        class="drag-handle size-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing"
                                    />
                                    <span v-else class="size-4 shrink-0" aria-hidden="true" />
                                    <span class="text-muted-foreground">{{ index + 1 }}.</span>
                                    <EntityAvatar :src="stop.pool_photo" type="pool" :name="stop.pool" size="sm" />
                                    <span class="truncate"
                                        >{{ stop.pool }} <span class="text-xs text-muted-foreground">· {{ stop.customer }}</span></span
                                    >
                                </span>
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
                        </template>
                    </draggable>
                    <p v-if="route.stops.length === 0" class="px-4 py-3 text-center text-xs text-muted-foreground">No stops — drag one here.</p>
                </div>
            </div>

            <!-- Right: route map + day details (reserved space) -->
            <aside class="hidden min-h-0 flex-1 flex-col gap-4 lg:flex">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-border p-3 text-center">
                        <div class="text-xl font-semibold">{{ localRoutes.length }}</div>
                        <div class="text-xs text-muted-foreground">Routes</div>
                    </div>
                    <div class="rounded-xl border border-border p-3 text-center">
                        <div class="text-xl font-semibold">{{ totalStops }}</div>
                        <div class="text-xs text-muted-foreground">Stops</div>
                    </div>
                    <div class="rounded-xl border border-border p-3 text-center">
                        <div class="text-xl font-semibold text-emerald-600 dark:text-emerald-400">{{ completedStops }}</div>
                        <div class="text-xs text-muted-foreground">Completed</div>
                    </div>
                </div>
                <div
                    class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/20 text-center text-muted-foreground"
                >
                    <MapIcon class="size-8 opacity-40" />
                    <p class="text-sm font-medium">Route map — {{ prettyDate }}</p>
                    <p class="max-w-xs text-xs">Coming soon: an interactive map of the day's stops, geocoded from each pool's address.</p>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
