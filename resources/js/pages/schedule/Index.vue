<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ScheduleMap from '@/components/schedule/ScheduleMap.vue';
import { Button } from '@/components/ui/button';
import { subscribeTenantSchedule } from '@/echo';
import AppLayout from '@/layouts/AppLayout.vue';
import { visitStatusClass } from '@/lib/statusColors';
import { clone } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, GripVertical, Inbox, SkipForward, Sparkles, Undo2, Wand2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import draggable from 'vuedraggable';

interface Stop {
    id: number;
    order: number;
    pool: string | null;
    pool_photo: string | null;
    customer: string | null;
    status: string;
    eta: string | null;
}

interface RouteCard {
    id: number;
    agent: string | null;
    agent_id: number | null;
    agent_photo: string | null;
    color: string;
    completed: number;
    total: number;
    stops: Stop[];
}

interface MapMarker {
    id: number;
    lat: number;
    lng: number;
    order: number;
    pool: string | null;
    address: string | null;
    status: string;
    agent: string | null;
    agent_id: number | null;
}

interface AgentMarker {
    agent_id: number;
    name: string;
    lat: number;
    lng: number;
    recorded_at?: string;
}

const props = defineProps<{
    date: string;
    today: string;
    routes: RouteCard[];
    unassigned: RouteCard | null;
    canManage: boolean;
    manageAgentId: number | null;
    coords: Record<number, { lat: number; lng: number; address: string | null }>;
    hq: { lat: number; lng: number; label: string | null } | null;
    mapsKey: string | null;
    agentLocations: AgentMarker[];
}>();

// Admins manage every route; an Agent+ manages only their own route card.
const canManageRoute = (route: RouteCard): boolean => props.canManage || (props.manageAgentId !== null && route.agent_id === props.manageAgentId);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Schedule', href: '/schedule' }];
const busy = ref(false);
const dateInput = ref<HTMLInputElement | null>(null);

// A local, mutable copy of the routes that drag-and-drop reorders optimistically;
// it re-syncs from the server (the source of truth) whenever the props change.
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

// Live sync: route changes refresh the board (debounced, only the day on
// screen); agent movement slides each marker on the map in place.
const page = usePage();
const tenantId = Number((page.props.auth as { user?: { tenant_id?: number } } | undefined)?.user?.tenant_id ?? 0);
const liveAgents = ref<Record<number, AgentMarker>>(Object.fromEntries(props.agentLocations.map((a) => [a.agent_id, a])));
watch(
    () => props.agentLocations,
    (value) => (liveAgents.value = Object.fromEntries(value.map((a) => [a.agent_id, a]))),
);
const agentMarkers = computed<AgentMarker[]>(() => Object.values(liveAgents.value));

let unsubscribe: (() => void) | null = null;
let reloadTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    if (!tenantId) return;
    unsubscribe = subscribeTenantSchedule(tenantId, {
        onRouteUpdate: (e) => {
            if (e.date !== props.date) return;
            if (reloadTimer) clearTimeout(reloadTimer);
            reloadTimer = setTimeout(() => router.reload({ only: ['routes', 'unassigned', 'coords', 'agentLocations'], preserveScroll: true }), 400);
        },
        onAgentMove: (e) => {
            // Only show movement on today's board, and only for a known agent name.
            if (props.date !== props.today) return;
            const name = liveAgents.value[e.agent_id]?.name ?? props.routes.find((r) => r.agent_id === e.agent_id)?.agent ?? null;
            if (name === null) return;
            liveAgents.value = {
                ...liveAgents.value,
                [e.agent_id]: { agent_id: e.agent_id, name, lat: e.lat, lng: e.lng, recorded_at: e.recorded_at },
            };
        },
    });
});

onBeforeUnmount(() => {
    if (reloadTimer) clearTimeout(reloadTimer);
    unsubscribe?.();
});

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
const unskipStop = (id: number) => router.post(`/stops/${id}/unskip`, {}, { preserveScroll: true });

// Live per-agent route colours drive both the card headers and the map lines.
// Derived from the local (mutable) routes so a colour pick updates instantly.
const agentColors = computed<Record<number, string>>(() => {
    const out: Record<number, string> = {};
    for (const r of localRoutes.value) {
        if (r.agent_id !== null) {
            out[r.agent_id] = r.color;
        }
    }
    return out;
});

// Map markers are computed from the LIVE route lists joined to the server's
// geocode table — so dragging a stop between agents re-plots it immediately
// (new colour, new polyline grouping) with no round-trip. Order follows the
// list position; stops without coordinates are dropped.
const mapMarkers = computed<MapMarker[]>(() => {
    const out: MapMarker[] = [];
    const add = (route: RouteCard) => {
        route.stops.forEach((s, i) => {
            const c = props.coords[s.id];
            if (!c) return;
            out.push({
                id: s.id,
                lat: c.lat,
                lng: c.lng,
                order: i + 1,
                pool: s.pool,
                address: c.address,
                status: s.status,
                agent: route.agent,
                agent_id: route.agent_id,
            });
        });
    };
    localRoutes.value.forEach(add);
    if (localUnassigned.value) add(localUnassigned.value);
    return out;
});

// Agent toggles: a map legend whose chips hide/show each route. Keyed by agent
// id, with the unassigned bucket under the 'unassigned' key.
type AgentKey = number | 'unassigned';
const hiddenAgents = ref<Set<AgentKey>>(new Set());
const keyOf = (id: number | null): AgentKey => id ?? 'unassigned';
const isAgentHidden = (id: number | null) => hiddenAgents.value.has(keyOf(id));
function toggleAgent(id: number | null) {
    const next = new Set(hiddenAgents.value);
    const k = keyOf(id);
    if (next.has(k)) {
        next.delete(k);
    } else {
        next.add(k);
    }
    hiddenAgents.value = next;
}

// The legend's entries: each agent with stops, plus the unassigned bucket.
const mapAgents = computed(() => {
    const list = localRoutes.value
        .filter((r) => r.stops.length > 0)
        .map((r) => ({ id: r.agent_id as number | null, label: r.agent, color: r.color }));
    if (localUnassigned.value && localUnassigned.value.stops.length > 0) {
        list.push({ id: null, label: 'Unassigned', color: '#9ca3af' });
    }
    return list;
});

const visibleMarkers = computed(() => mapMarkers.value.filter((m) => !isAgentHidden(m.agent_id)));

// Click-to-focus: clicking a stop pans/zooms the map to its pin (toggle off by
// clicking it again). The id is shared with the map (highlight) and the list row.
const focusId = ref<number | null>(null);
function focusStop(stop: Stop) {
    focusId.value = focusId.value === stop.id ? null : stop.id;
}

function setColor(route: RouteCard, event: Event) {
    const hex = (event.target as HTMLInputElement).value;
    route.color = hex; // optimistic — header + map recolour at once
    if (route.agent_id !== null) {
        router.patch(`/agents/${route.agent_id}/color`, { map_color: hex }, { preserveScroll: true, preserveState: true });
    }
}

const prettyDate = computed(() =>
    new Date(props.date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }),
);
</script>

<template>
    <Head title="Schedule" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #actions>
            <Button v-if="props.canManage" size="sm" variant="outline" :disabled="busy" @click="materialize"
                ><Sparkles class="mr-1 size-4" /> Generate</Button
            >
            <Button v-if="!isToday" size="sm" variant="ghost" @click="go()">Today</Button>
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
                            <li
                                class="flex cursor-pointer items-center justify-between gap-2 px-4 py-2 transition-colors"
                                :class="focusId === stop.id ? 'bg-primary/10 ring-1 ring-inset ring-primary/40' : 'hover:bg-muted/40'"
                                @click="focusStop(stop)"
                            >
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
                                    <span v-if="stop.eta && stop.status === 'pending'" class="text-xs tabular-nums text-muted-foreground">{{
                                        stop.eta
                                    }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="visitStatusClass(stop.status)">{{
                                        stop.status.replace('_', ' ')
                                    }}</span>
                                </div>
                            </li>
                        </template>
                    </draggable>
                    <p v-if="localUnassigned.stops.length === 0" class="px-4 py-3 text-center text-xs text-muted-foreground">
                        No unassigned stops for this day.
                    </p>
                </div>

                <div
                    v-for="route in localRoutes"
                    :key="route.id"
                    class="overflow-hidden rounded-xl border border-l-[3px] border-border"
                    :style="{ borderLeftColor: route.color }"
                >
                    <div class="flex items-center justify-between border-b border-border px-4 py-2" :style="{ backgroundColor: route.color + '14' }">
                        <div class="flex min-w-0 items-center gap-2">
                            <EntityAvatar :src="route.agent_photo" type="person" :name="route.agent" size="sm" shape="circle" />
                            <span class="truncate font-medium">{{ route.agent }}</span>
                            <label
                                v-if="props.canManage"
                                class="relative inline-flex size-4 shrink-0 cursor-pointer items-center justify-center"
                                :title="`Route colour for ${route.agent}`"
                            >
                                <input
                                    type="color"
                                    :value="route.color"
                                    class="absolute inset-0 cursor-pointer opacity-0"
                                    @change="setColor(route, $event)"
                                />
                                <span class="size-3.5 rounded-full border border-border shadow-sm" :style="{ backgroundColor: route.color }" />
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">{{ doneCount(route.stops) }}/{{ route.stops.length }} done</span>
                            <button
                                v-if="canManageRoute(route) && route.stops.length > 1"
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
                        :disabled="!canManageRoute(route)"
                        handle=".drag-handle"
                        ghost-class="bg-muted/60"
                        class="min-h-[2.75rem] divide-y divide-border text-sm"
                        @change="persist"
                    >
                        <template #item="{ element: stop, index }">
                            <li
                                class="flex cursor-pointer items-center justify-between gap-2 px-4 py-2 transition-colors"
                                :class="focusId === stop.id ? 'bg-primary/10 ring-1 ring-inset ring-primary/40' : 'hover:bg-muted/40'"
                                @click="focusStop(stop)"
                            >
                                <span class="flex min-w-0 items-center gap-2 truncate">
                                    <GripVertical
                                        v-if="canManageRoute(route) && stop.status === 'pending'"
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
                                    <span v-if="stop.eta && stop.status === 'pending'" class="text-xs tabular-nums text-muted-foreground">{{
                                        stop.eta
                                    }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="visitStatusClass(stop.status)">{{
                                        stop.status.replace('_', ' ')
                                    }}</span>
                                    <button
                                        v-if="canManageRoute(route) && stop.status === 'pending'"
                                        class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-amber-600"
                                        title="Skip stop"
                                        @click.stop="skipStop(stop.id)"
                                    >
                                        <SkipForward class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="canManageRoute(route) && stop.status === 'skipped'"
                                        class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-emerald-600"
                                        title="Unskip stop"
                                        @click.stop="unskipStop(stop.id)"
                                    >
                                        <Undo2 class="size-3.5" />
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
                <!-- Map legend / agent toggles: click a chip to hide that route. -->
                <div v-if="props.mapsKey && mapAgents.length" class="flex flex-wrap gap-1.5">
                    <button
                        v-for="a in mapAgents"
                        :key="a.id ?? 'unassigned'"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-border px-2 py-0.5 text-xs font-medium transition-colors"
                        :class="isAgentHidden(a.id) ? 'text-muted-foreground line-through opacity-50' : 'hover:bg-muted'"
                        :title="isAgentHidden(a.id) ? `Show ${a.label}` : `Hide ${a.label}`"
                        @click="toggleAgent(a.id)"
                    >
                        <span class="size-2.5 rounded-full" :style="{ backgroundColor: a.color }" />
                        {{ a.label }}
                    </button>
                </div>
                <ScheduleMap
                    :maps-key="props.mapsKey"
                    :hq="props.hq"
                    :markers="visibleMarkers"
                    :agents="agentMarkers"
                    :colors="agentColors"
                    :focus-id="focusId"
                />
            </aside>
        </div>
    </AppLayout>
</template>
