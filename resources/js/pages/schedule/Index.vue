<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import Hint from '@/components/Hint.vue';
import ScheduleMap from '@/components/schedule/ScheduleMap.vue';
import StopDetail from '@/components/schedule/StopDetail.vue';
import StopRow from '@/components/schedule/StopRow.vue';
import type { AgentDot, MapRoute, RouteCard, Stop } from '@/components/schedule/types';
import { Button } from '@/components/ui/button';
import { subscribeTenantSchedule } from '@/echo';
import AppLayout from '@/layouts/AppLayout.vue';
import { clone, localDay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, EyeOff, Inbox, Sparkles, Wand2, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps<{
    date: string;
    today: string;
    routes: RouteCard[];
    /** The day's unassigned bucket (agent_id null); admins always get one to drop stops into. */
    unassigned: RouteCard | null;
    canManage: boolean;
    manageAgentId: number | null;
    hq: { lat: number; lng: number; label: string | null } | null;
    mapsKey: string | null;
    agentLocations: AgentDot[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Schedule', href: '/schedule' }];
const busy = ref(false);
const dateInput = ref<HTMLInputElement | null>(null);

// Admins manage every route; an Agent+ manages only their own.
const canManageRoute = (route: RouteCard): boolean => props.canManage || (props.manageAgentId !== null && route.agent_id === props.manageAgentId);
const isBucket = (route: RouteCard) => route.agent_id === null;

// Local copies that drag-and-drop reorders at once; they re-sync whenever the server sends the day again.
const localRoutes = ref<RouteCard[]>(clone(props.routes));
watch(
    () => props.routes,
    (value) => (localRoutes.value = clone(value)),
);
const localUnassigned = ref<RouteCard | null>(clone(props.unassigned));
watch(
    () => props.unassigned,
    (value) => (localUnassigned.value = clone(value)),
);
// Every card, in screen order: the unassigned bucket first. All share one drag group,
// so a stop dragged onto or off the bucket is un- or re-assigned.
const cards = computed(() => (localUnassigned.value ? [localUnassigned.value, ...localRoutes.value] : localRoutes.value));

// Live sync: a route change reloads the day on screen (debounced); a tech's ping moves their dot.
const page = usePage();
const tenantId = Number((page.props.auth as { user?: { tenant_id?: number } } | undefined)?.user?.tenant_id ?? 0);
const toDots = (list: AgentDot[]) => Object.fromEntries(list.map((a) => [a.agent_id, a]));
const liveAgents = ref<Record<number, AgentDot>>(toDots(props.agentLocations));
watch(
    () => props.agentLocations,
    (value) => (liveAgents.value = toDots(value)),
);

let unsubscribe: (() => void) | null = null;
let reloadTimer: ReturnType<typeof setTimeout> | undefined;
onMounted(() => {
    if (!tenantId) return;
    unsubscribe = subscribeTenantSchedule(tenantId, {
        onRouteUpdate: (e) => {
            if (e.date !== props.date) return;
            clearTimeout(reloadTimer);
            reloadTimer = setTimeout(() => router.reload({ only: ['routes', 'unassigned', 'agentLocations'], preserveScroll: true }), 400);
        },
        onAgentMove: (e) => {
            // Today's board only, and only for a tech it knows.
            if (props.date !== props.today) return;
            const name = liveAgents.value[e.agent_id]?.name ?? props.routes.find((r) => r.agent_id === e.agent_id)?.agent;
            if (!name) return;
            liveAgents.value = { ...liveAgents.value, [e.agent_id]: { agent_id: e.agent_id, name, lat: e.lat, lng: e.lng } };
        },
    });
});
onBeforeUnmount(() => {
    clearTimeout(reloadTimer);
    unsubscribe?.();
});

// The day's numbers, live as stops are dragged.
const doneCount = (stops: Stop[]) => stops.filter((s) => s.status === 'completed').length;
const stats = computed(() => {
    const stops = cards.value.flatMap((r) => r.stops);
    return [
        { label: 'Routes', value: localRoutes.value.filter((r) => r.stops.length > 0).length, tone: '' },
        { label: 'Stops', value: stops.length, tone: '' },
        { label: 'Completed', value: doneCount(stops), tone: 'text-emerald-600 dark:text-emerald-400' },
    ];
});

// Date navigation.
const isToday = computed(() => props.date === props.today);
const prettyDate = computed(() =>
    new Date(props.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }),
);
const go = (date?: string) => router.get('/schedule', date ? { date } : {}, { preserveState: true, preserveScroll: true });
// Step a day on the local calendar (toISOString would convert to UTC and can skip a day).
function shift(days: number) {
    const d = new Date(props.date + 'T00:00:00');
    d.setDate(d.getDate() + days);
    go(localDay(d));
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

// What Generate does (both its buttons say it).
const generateHint = 'Fills the next 8 weeks of routes from every active service plan. Stops already on the schedule stay as they are.';
function materialize() {
    if (busy.value) return;
    busy.value = true;
    router.post('/schedule/materialize', {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

// Save the arrangement after a drag (debounced: a move between cards fires a change on
// both). The server is idempotent, so a double send is safe. agent_id lets it save an
// unsaved card (the bucket, an admin's own) the first time a stop lands on it.
let persistTimer: ReturnType<typeof setTimeout> | undefined;
function persist() {
    clearTimeout(persistTimer);
    persistTimer = setTimeout(() => {
        const routes = cards.value.map((r) => ({ id: r.id, agent_id: r.agent_id, stop_ids: r.stops.map((s) => s.id) }));
        router.post('/schedule/arrange', { date: props.date, routes }, { preserveScroll: true, preserveState: true });
    }, 120);
}

const optimize = (id: number | null) => id !== null && router.post(`/routes/${id}/optimize`, {}, { preserveScroll: true });
const skipStop = (id: number) => router.post(`/stops/${id}/skip`, {}, { preserveScroll: true });
const unskipStop = (id: number) => router.post(`/stops/${id}/unskip`, {}, { preserveScroll: true });

function setColor(route: RouteCard, event: Event) {
    route.color = (event.target as HTMLInputElement).value; // the card and the map recolour at once
    if (route.agent_id !== null) {
        router.patch(`/agents/${route.agent_id}/color`, { map_color: route.color }, { preserveScroll: true, preserveState: true });
    }
}

// The map legend: a chip per card with stops hides or shows that route (and its tech's
// live dot). A hidden card is greyed out and its stops can't be picked.
const hidden = ref(new Set<number | null>());
const isHidden = (agentId: number | null) => hidden.value.has(agentId);
const legend = computed(() => cards.value.filter((r) => r.stops.length > 0));
const filtering = computed(() => legend.value.some((r) => isHidden(r.agent_id)));
const allHidden = computed(() => legend.value.length > 0 && legend.value.every((r) => isHidden(r.agent_id)));
function toggleHidden(agentId: number | null) {
    const next = new Set(hidden.value);
    if (!next.delete(agentId)) next.add(agentId);
    hidden.value = next;
    // A hidden route's stop can't stay picked: its pin is gone.
    if (selected.value && isHidden(selected.value.route.agent_id)) focusId.value = null;
}
const clearFilters = () => (hidden.value = new Set());

// What the map draws: the shown routes' placed stops, numbered as on their card, and the
// shown techs' live dots in their route's colour.
const mapRoutes = computed<MapRoute[]>(() =>
    cards.value
        .filter((r) => !isHidden(r.agent_id))
        .map((r) => ({
            agent: r.agent,
            color: r.color,
            stops: r.stops.flatMap((s, i) =>
                s.lat !== null && s.lng !== null ? [{ id: s.id, order: i + 1, lat: s.lat, lng: s.lng, pool: s.pool, status: s.status }] : [],
            ),
        })),
);
const mapAgents = computed(() =>
    Object.values(liveAgents.value)
        .filter((a) => !isHidden(a.agent_id))
        .map((a) => ({ ...a, color: localRoutes.value.find((r) => r.agent_id === a.agent_id)?.color ?? '#0ea5e9' })),
);

// The picked stop (from its row or its pin): highlighted in the list, enlarged on the
// map, and shown in the panel under it. Picking it again lets it go.
const focusId = ref<number | null>(null);
const selected = computed(() => {
    for (const route of cards.value) {
        const stop = route.stops.find((s) => s.id === focusId.value);
        if (stop) return { stop, route };
    }
    return null;
});
const pick = (id: number) => (focusId.value = focusId.value === id ? null : id);
// A pin click also brings its row into view in the list.
function pickPin(id: number) {
    pick(id);
    void nextTick(() => document.querySelector(`[data-stop-id="${id}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
}
</script>

<template>
    <Head title="Schedule" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #actions>
            <Hint v-if="props.canManage" :text="generateHint">
                <Button size="sm" variant="outline" :disabled="busy" @click="materialize"><Sparkles class="mr-1 size-4" /> Generate</Button>
            </Hint>
            <Button v-if="!isToday" size="sm" variant="ghost" @click="go()">Today</Button>
            <div class="flex items-center gap-1">
                <button class="border-border hover:bg-muted rounded-md border p-1.5" aria-label="Previous day" @click="shift(-1)">
                    <ChevronLeft class="size-4" />
                </button>
                <button
                    class="border-border hover:bg-muted flex min-w-44 items-center justify-center gap-1.5 rounded-md border px-2 py-1.5 text-sm font-medium"
                    @click="openCalendar"
                >
                    {{ prettyDate }}
                    <CalendarDays class="text-muted-foreground size-3.5" />
                </button>
                <input ref="dateInput" type="date" :value="props.date" tabindex="-1" class="sr-only" @change="onPickDate" />
                <button class="border-border hover:bg-muted rounded-md border p-1.5" aria-label="Next day" @click="shift(1)">
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </template>

        <div class="rp-fit-screen flex min-h-0 flex-1 gap-4 p-4">
            <!-- Left: the day's cards, a drag-and-drop list that scrolls on its own. -->
            <div class="flex w-full min-w-0 flex-col gap-3 overflow-y-auto lg:w-[30rem] lg:shrink-0">
                <div v-if="cards.length === 0" class="text-muted-foreground flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
                    <CalendarDays class="size-8 opacity-50" />
                    <p>No routes scheduled for this day.</p>
                    <Hint v-if="props.canManage" :text="generateHint">
                        <Button size="sm" variant="outline" :disabled="busy" @click="materialize"
                            ><Sparkles class="mr-1 size-4" /> Generate from subscriptions</Button
                        >
                    </Hint>
                </div>

                <!-- A tech's route, or the unassigned bucket (dashed): dragging a stop there un-assigns it. -->
                <div
                    v-for="route in cards"
                    :key="route.id ?? `agent-${route.agent_id}`"
                    class="border-border overflow-hidden rounded-xl border transition-opacity"
                    :class="[isBucket(route) ? 'bg-muted/20 border-dashed' : 'border-l-[3px]', { 'opacity-50': isHidden(route.agent_id) }]"
                    :style="isBucket(route) ? undefined : { borderLeftColor: route.color }"
                >
                    <div
                        class="border-border flex items-center justify-between border-b px-4 py-2"
                        :style="isBucket(route) ? undefined : { backgroundColor: route.color + '14' }"
                    >
                        <div v-if="isBucket(route)" class="flex items-center gap-2">
                            <span class="bg-muted text-muted-foreground flex size-7 shrink-0 items-center justify-center rounded-full">
                                <Inbox class="size-4" />
                            </span>
                            <span class="font-medium">Unassigned stops</span>
                        </div>
                        <div v-else class="flex min-w-0 items-center gap-2">
                            <EntityAvatar :src="route.agent_photo" type="person" :name="route.agent" size="sm" shape="circle" />
                            <span class="truncate font-medium" :title="route.agent ?? undefined">{{ route.agent }}</span>
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
                                <span class="border-border size-3.5 rounded-full border shadow-sm" :style="{ backgroundColor: route.color }" />
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="isHidden(route.agent_id)"
                                type="button"
                                class="text-muted-foreground hover:bg-muted inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs"
                                title="Hidden on the map. Click to show it again."
                                @click="toggleHidden(route.agent_id)"
                            >
                                <EyeOff class="size-3" /> Hidden
                            </button>
                            <span class="text-muted-foreground text-xs">{{
                                isBucket(route) ? route.stops.length : `${doneCount(route.stops)}/${route.stops.length} done`
                            }}</span>
                            <Hint
                                v-if="!isBucket(route) && canManageRoute(route) && route.stops.length > 1"
                                text="Reorders the stops still to do for the shortest drive. Finished ones keep their place."
                            >
                                <button
                                    class="text-muted-foreground hover:bg-muted hover:text-foreground rounded p-1"
                                    aria-label="Optimize order"
                                    @click="optimize(route.id)"
                                >
                                    <Wand2 class="size-3.5" />
                                </button>
                            </Hint>
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
                        class="divide-border min-h-[2.75rem] divide-y text-sm"
                        @change="persist"
                    >
                        <template #item="{ element: stop, index }">
                            <StopRow
                                :stop="stop"
                                :number="index + 1"
                                :focused="focusId === stop.id"
                                :movable="canManageRoute(route)"
                                :skippable="!isBucket(route) && canManageRoute(route)"
                                @click="isHidden(route.agent_id) || pick(stop.id)"
                                @skip="skipStop(stop.id)"
                                @unskip="unskipStop(stop.id)"
                            />
                        </template>
                    </draggable>
                    <p v-if="route.stops.length === 0" class="text-muted-foreground px-4 py-3 text-center text-xs">
                        {{ isBucket(route) ? 'No unassigned stops for this day.' : 'No stops — drag one here.' }}
                    </p>
                </div>
            </div>

            <!-- Right: the day's numbers, the map and the picked visit. -->
            <aside class="hidden min-h-0 flex-1 flex-col gap-4 lg:flex">
                <div class="grid grid-cols-3 gap-3">
                    <div v-for="s in stats" :key="s.label" class="border-border rounded-xl border p-3 text-center">
                        <div class="text-xl font-semibold" :class="s.tone">{{ s.value }}</div>
                        <div class="text-muted-foreground text-xs">{{ s.label }}</div>
                    </div>
                </div>
                <!-- The legend: Clear filters stays put (disabled until a chip is off), so the chips never shift. -->
                <div v-if="props.mapsKey && legend.length" class="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        class="border-border hover:bg-muted inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium transition-colors disabled:pointer-events-none disabled:opacity-40"
                        :disabled="!filtering"
                        @click="clearFilters"
                    >
                        <X class="size-3" /> Clear filters
                    </button>
                    <button
                        v-for="r in legend"
                        :key="r.agent_id ?? 'unassigned'"
                        type="button"
                        class="border-border inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium transition-colors"
                        :class="isHidden(r.agent_id) ? 'text-muted-foreground line-through opacity-50' : 'hover:bg-muted'"
                        :title="`${isHidden(r.agent_id) ? 'Show' : 'Hide'} ${r.agent ?? 'Unassigned'}`"
                        @click="toggleHidden(r.agent_id)"
                    >
                        <span class="size-2.5 rounded-full" :style="{ backgroundColor: r.color }" />
                        {{ r.agent ?? 'Unassigned' }}
                    </button>
                </div>
                <ScheduleMap
                    :maps-key="props.mapsKey"
                    :hq="props.hq"
                    :routes="mapRoutes"
                    :agents="mapAgents"
                    :focus-id="focusId"
                    :all-hidden="allHidden"
                    @select="pickPin"
                />
                <StopDetail :stop="selected?.stop ?? null" :tech="selected?.route.agent ?? null" />
            </aside>
        </div>
    </AppLayout>
</template>
