<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FieldAddPool from '@/components/field/FieldAddPool.vue';
import FieldVisit from '@/components/field/FieldVisit.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import { useAgentTracking } from '@/composables/useAgentTracking';
import { signedOut } from '@/lib/field/session';
import { pruneDrafts, saveBundle, type FieldStop, type TodayBundle } from '@/lib/field/store';
import {
    QUEUE_SYNCED,
    discardFailed,
    failedCount,
    firstFailed,
    flushQueue,
    loadToday,
    queuedCount,
    reopenFailed,
    retryFailed,
} from '@/lib/field/sync';
import { formatDay } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, CloudOff, LoaderCircle, MapPin, Navigation, Plus, RefreshCw, TriangleAlert, Wifi, WifiOff, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const {
    sharing: sharingLocation,
    toggle: toggleLocation,
    restore: restoreLocation,
    notice: locationNotice,
    dismissNotice: dismissLocationNotice,
} = useAgentTracking();

const bundle = ref<TodayBundle | null>(null);
const source = ref<'network' | 'cache' | 'none'>('none');
const loading = ref(true);
const online = ref(true);
const queued = ref(0);
const failed = ref(0);
// The first visit that didn't sync: the banner shows the server's reason, and offers to fix or discard it.
const firstFail = ref<{ stop_id: number; pool_name: string; error: string | null } | null>(null);
// A short "it went through" after finishing a visit that raised a work order (no bell for your own).
const notice = ref<string | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | undefined;
const selected = ref<FieldStop | null>(null);
const addingPool = ref(false);

// The office can open any tech's route on any day up to today (the server checks it's an admin).
// null = the signed-in person's own route, today — the only one kept on the device for offline use.
const page = usePage<SharedData>();
const pickedTech = ref<number | null>(null);
const pickedDay = ref<string | null>(null);
const todayIso = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();
const title = computed(() => {
    const b = bundle.value;
    if (b?.office) return `${b.agent.name.split(' ')[0]}’s route`;
    return b?.past ? 'Your route' : 'Today’s route';
});
async function pick(tech: number | null, day: string | null) {
    pickedTech.value = tech === page.props.auth.user?.id ? null : tech;
    pickedDay.value = day === todayIso ? null : day;
    selected.value = null;
    addingPool.value = false;
    await load();
}
const onTech = (e: Event) => pick(Number((e.target as HTMLSelectElement).value), pickedDay.value);
const onDay = (e: Event) => pick(pickedTech.value, (e.target as HTMLInputElement).value || null);

/** Keep the route on the device for offline use — only ever the person's own. */
async function cacheRoute() {
    if (bundle.value && !bundle.value.office) await saveBundle(bundle.value);
}

// The open stop is remembered for this tab, so a refresh lands back on the visit
// (whose entries FieldVisit restores) instead of the list.
const OPEN_STOP = 'field-open-stop';
watch(selected, (stop) => {
    try {
        if (stop) sessionStorage.setItem(OPEN_STOP, String(stop.id));
        else sessionStorage.removeItem(OPEN_STOP);
    } catch {
        // Storage blocked — a refresh just lands on the list; the visit's entries are still kept.
    }
});
function rememberedStop(): number {
    try {
        return Number(sessionStorage.getItem(OPEN_STOP));
    } catch {
        return 0;
    }
}

async function refreshCounts() {
    queued.value = await queuedCount();
    failed.value = await failedCount();
    firstFail.value = await firstFailed();
}

function navUrl(stop: FieldStop): string | null {
    const p = stop.pool;
    return p?.lat != null && p?.lng != null ? `https://www.google.com/maps/dir/?api=1&destination=${p.lat},${p.lng}` : null;
}

async function retry() {
    await retryFailed();
    await refreshCounts();
}

const stops = computed(() => bundle.value?.stops ?? []);
const isDone = (s: FieldStop) => s.completed || s.status === 'completed';
const toDo = computed(() => stops.value.filter((s) => !isDone(s) && s.status !== 'skipped'));
const remaining = computed(() => toDo.value.length);
const done = computed(() => stops.value.filter(isDone).length);
// The next stop still to do — named in the summary at the top.
const next = computed(() => toDo.value[0] ?? null);

// A refused visit can be reopened to fix while its stop is on today's route; any can be discarded.
const fixable = computed(() => firstFail.value !== null && stops.value.some((s) => s.id === firstFail.value?.stop_id));

/** A refused visit never reached the server, so its stop is still to do. */
async function backToDo(stopId: number): Promise<FieldStop | null> {
    const stop = stops.value.find((s) => s.id === stopId) ?? null;
    if (stop) {
        stop.completed = false;
        stop.status = 'pending';
        await cacheRoute().catch(() => {});
    }
    return stop;
}

async function fixFailed() {
    const failed = firstFail.value;
    if (!fixable.value || !failed) return;
    await reopenFailed(failed.stop_id);
    const stop = await backToDo(failed.stop_id);
    await refreshCounts();
    selected.value = stop; // reopen it with what was entered
}

async function discardFailedVisit() {
    const failed = firstFail.value;
    if (!failed) return;
    if (!confirm(`Discard the visit to ${failed.pool_name}? What was entered for it is lost, and the office won’t get the report.`)) return;
    await discardFailed(failed.stop_id);
    await backToDo(failed.stop_id);
    await refreshCounts();
}

async function load() {
    loading.value = true;
    const result = await loadToday(pickedDay.value ?? undefined, pickedTech.value ?? undefined);
    bundle.value = result.bundle;
    source.value = result.source;
    await refreshCounts();
    loading.value = false;
}

async function refresh() {
    // Spin from the moment of the tap, through the sync (flushQueue) AND the
    // reload — the sync is the slow part (uploads), so it needs the feedback.
    loading.value = true;
    try {
        if (online.value) await flushQueue();
        await load();
    } finally {
        loading.value = false;
    }
}

const onOnline = async () => {
    online.value = true;
    await flushQueue();
    await load();
};
const onOffline = () => {
    online.value = false;
};

onMounted(async () => {
    online.value = navigator.onLine;
    window.addEventListener('online', onOnline);
    window.addEventListener(QUEUE_SYNCED, refreshCounts);
    window.addEventListener('offline', onOffline);
    await load();
    restoreLocation();
    pruneDrafts(2 * 24 * 60 * 60 * 1000).catch(() => {});
    // Deep-links, stripped once read: /field?stop=<id> opens that stop (e.g. from the
    // dashboard route widget); /field?new=1 opens New report (from the Reports page).
    // Otherwise reopen the stop that was open before a refresh.
    const params = new URLSearchParams(window.location.search);
    const linked = Number(params.get('stop'));
    if (linked || params.has('new')) window.history.replaceState({}, '', '/field');
    if (params.get('new') === '1' && online.value) {
        addingPool.value = true;
        return;
    }
    const stopId = linked || rememberedStop();
    if (stopId) {
        const target = stops.value.find((s) => s.id === stopId);
        if (target) open(target);
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener(QUEUE_SYNCED, refreshCounts);
    window.removeEventListener('offline', onOffline);
    clearTimeout(noticeTimer);
});

function open(stop: FieldStop) {
    if (stop.completed || stop.status === 'completed') return;
    selected.value = stop;
}

// An extra visit is made on the server, so reload the route and open its new stop.
async function onPoolAdded(stopId: number) {
    addingPool.value = false;
    await load();
    const stop = stops.value.find((s) => s.id === stopId);
    if (stop) open(stop);
}

async function onRemoved() {
    selected.value = null;
    await load();
}

async function onCompleted(stopId: number, raisedWorkOrder: boolean) {
    if (raisedWorkOrder) {
        notice.value = online.value
            ? 'Work order sent to your team’s list.'
            : 'Work order saved. It goes to your team’s list when you’re back online.';
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => (notice.value = null), 6000);
    }
    // Optimistic: mark done locally and persist so a reload (even offline) keeps it.
    const stop = stops.value.find((s) => s.id === stopId);
    if (stop) {
        stop.completed = true;
        stop.status = 'completed';
    }
    // Close first: the visit is already saved (queued/synced) at this point, so a re-cache
    // failure below must never block the UI from advancing to the next stop.
    selected.value = null;
    try {
        await cacheRoute();
    } catch (e) {
        console.error('Failed to re-cache the route for offline use (visit was still saved):', e);
    }
    await refreshCounts();
}
</script>

<template>
    <Head :title="title" />

    <!-- A phone shows the route, with a visit opening full-screen over it; a desktop
         puts the route on the left and the open visit (or New report) on the right. -->
    <div class="bg-background text-foreground min-h-svh lg:grid lg:h-svh lg:grid-cols-[26rem_minmax(0,1fr)] lg:gap-6 lg:overflow-hidden lg:p-6">
        <div class="lg:border-border lg:min-h-0 lg:overflow-y-auto lg:rounded-2xl lg:border">
            <!-- top bar -->
            <header class="border-border bg-background sticky top-0 z-10 border-b">
                <ImpersonationBanner />
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <Link
                        href="/dashboard"
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-orange-500 text-white"
                        aria-label="Back to Today"
                    >
                        <AppLogoIcon class="size-5" />
                    </Link>
                    <div class="min-w-0 flex-1 pl-1">
                        <h1 class="font-display truncate text-lg leading-tight font-semibold tracking-tight">{{ title }}</h1>
                        <p class="text-muted-foreground truncate text-xs">{{ bundle?.date ? formatDay(bundle.date) : 'Today' }}</p>
                    </div>
                    <!-- Connection, as an icon only: the colour and icon say it; the label is for screen readers and a long-press. -->
                    <span
                        role="status"
                        class="grid size-8 shrink-0 place-items-center rounded-full"
                        :class="
                            online ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                        "
                        :title="online ? 'Online' : 'Offline'"
                        :aria-label="online ? 'Online' : 'Offline'"
                    >
                        <Wifi v-if="online" class="size-4" /><WifiOff v-else class="size-4" />
                    </span>
                    <!-- Location + refresh sit together, no gap: the 40px tap targets already space the icons. -->
                    <div class="flex items-center">
                        <button
                            v-if="!bundle?.office && !bundle?.past"
                            class="grid size-10 place-items-center rounded-xl"
                            :class="sharingLocation ? 'text-primary bg-[hsl(var(--brand)/0.12)]' : 'text-muted-foreground hover:bg-muted'"
                            :title="sharingLocation ? 'Sharing your location. Tap to stop.' : 'Share your location with the office'"
                            :aria-label="sharingLocation ? 'Stop sharing your location' : 'Share your location with the office'"
                            @click="toggleLocation"
                        >
                            <MapPin class="size-5" :class="sharingLocation ? 'animate-pulse' : ''" />
                        </button>
                        <button
                            class="hover:bg-muted grid size-10 place-items-center rounded-xl"
                            :disabled="loading"
                            aria-label="Sync and refresh"
                            @click="refresh"
                        >
                            <RefreshCw class="size-5" :class="loading ? 'animate-spin' : ''" />
                        </button>
                    </div>
                </div>
                <!-- the office: whose route, which day -->
                <div v-if="bundle?.techs" class="flex flex-wrap items-center gap-2 px-4 pb-2.5">
                    <select
                        :value="bundle.agent.id"
                        aria-label="Whose route"
                        class="border-input bg-background h-9 w-44 rounded-md border px-2 text-sm"
                        @change="onTech"
                    >
                        <option v-for="t in bundle.techs" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <input
                        type="date"
                        :value="bundle.date"
                        :max="todayIso"
                        aria-label="Which day"
                        class="border-input bg-background h-9 w-40 rounded-md border px-2 text-sm"
                        @change="onDay"
                    />
                    <button v-if="bundle.office || bundle.past" class="text-primary text-sm font-medium" @click="pick(null, null)">
                        Back to my day
                    </button>
                </div>
                <div
                    v-if="notice"
                    role="status"
                    class="flex items-center gap-1.5 bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-400"
                >
                    <Check class="size-4" /> {{ notice }}
                </div>
                <div v-if="queued > 0" class="flex items-center gap-1.5 bg-sky-500/10 px-4 py-2 text-sm font-medium text-sky-700 dark:text-sky-300">
                    <CloudOff class="size-4" /> {{ queued }} visit{{ queued === 1 ? '' : 's' }} waiting to sync{{
                        online ? '…' : '. They’ll send when you’re back online.'
                    }}
                </div>
                <div
                    v-if="locationNotice"
                    role="status"
                    class="flex items-center justify-between gap-2 bg-amber-500/10 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400"
                >
                    <span class="flex items-center gap-1.5"><MapPin class="size-4 shrink-0" /> {{ locationNotice }}</span>
                    <button
                        class="grid size-8 shrink-0 place-items-center rounded-lg hover:bg-amber-500/15"
                        aria-label="Dismiss"
                        @click="dismissLocationNotice"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <!-- Signed out (the account signed in somewhere else): not a connection problem, and nothing is lost. -->
                <div v-if="signedOut" role="alert" class="bg-amber-500/10 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                    You’ve been signed out, maybe because this account signed in on another device. Visits you finish stay on this device until you
                    <a href="/login" class="underline underline-offset-2">sign in again</a>.
                </div>
                <div v-else-if="source === 'cache'" class="bg-amber-500/10 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                    You’re offline, so this is your last saved route.
                </div>
                <div
                    v-if="failed > 0"
                    class="flex items-center justify-between gap-2 bg-red-500/10 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-400"
                >
                    <span class="min-w-0">
                        <span class="flex items-center gap-1.5"
                            ><TriangleAlert class="size-4 shrink-0" /> {{ failed }} visit{{ failed === 1 ? '' : 's' }} didn’t sync</span
                        >
                        <span v-if="firstFail?.error" class="mt-0.5 block text-xs font-normal">{{ firstFail.error }}</span>
                        <button class="mt-0.5 text-xs font-normal underline underline-offset-2" @click="discardFailedVisit">Discard it</button>
                    </span>
                    <div class="flex shrink-0 gap-1.5">
                        <button v-if="fixable" class="rounded-lg bg-red-600 px-3 py-1 font-semibold text-white hover:bg-red-700" @click="fixFailed">
                            Fix
                        </button>
                        <button class="rounded-lg bg-red-500/15 px-3 py-1 font-semibold hover:bg-red-500/25" @click="retry">Try again</button>
                    </div>
                </div>
            </header>

            <!-- body -->
            <main class="mx-auto max-w-2xl space-y-5 p-4">
                <div v-if="loading" class="text-muted-foreground flex justify-center py-20"><LoaderCircle class="size-8 animate-spin" /></div>

                <template v-else>
                    <!-- where the day stands -->
                    <section class="rounded-2xl bg-[hsl(var(--brand)/0.08)] p-5">
                        <p class="font-display text-2xl leading-tight font-semibold tracking-tight">
                            <template v-if="!stops.length"
                                >No stops on {{ bundle?.office ? `${bundle.agent.name.split(' ')[0]}’s` : 'your' }} route{{
                                    source === 'none' ? ' yet' : ''
                                }}.</template
                            >
                            <template v-else-if="!remaining">All done for {{ bundle?.past ? 'that day' : 'today' }}.</template>
                            <template v-else>{{ remaining }} stop{{ remaining === 1 ? '' : 's' }} to go.</template>
                        </p>
                        <p v-if="!stops.length && source === 'none'" class="text-muted-foreground mt-1 text-[15px]">
                            Connect to the internet to load it.
                        </p>
                        <p v-else-if="next" class="text-muted-foreground mt-1 text-[15px]">Next up: {{ next.pool?.name ?? 'Pool' }}.</p>
                        <template v-if="stops.length">
                            <div
                                class="mt-3 h-2 overflow-hidden rounded-full bg-[hsl(var(--brand)/0.15)]"
                                role="progressbar"
                                :aria-valuenow="done"
                                aria-valuemin="0"
                                :aria-valuemax="stops.length"
                                :aria-label="`${done} of ${stops.length} stops done`"
                            >
                                <div
                                    class="bg-primary h-full rounded-full transition-[width]"
                                    :style="{ width: `${(done / stops.length) * 100}%` }"
                                ></div>
                            </div>
                            <p class="text-muted-foreground mt-1.5 text-sm">{{ done }} of {{ stops.length }} done</p>
                        </template>
                    </section>

                    <!-- the stops, in order -->
                    <ol v-if="stops.length" class="border-border divide-border divide-y overflow-hidden rounded-2xl border">
                        <!-- Pool name on its own line; the customer shares the next with Done /
                         Skipped, which never shrinks; then the service. -->
                        <li
                            v-for="(stop, i) in stops"
                            :key="stop.id"
                            class="flex items-center gap-3 p-4"
                            :class="[
                                isDone(stop) ? 'text-muted-foreground' : 'active:bg-muted/60 cursor-pointer',
                                { 'lg:bg-muted/60': selected?.id === stop.id },
                            ]"
                            @click="open(stop)"
                        >
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-full text-base font-semibold"
                                :class="isDone(stop) ? 'bg-emerald-600 text-white dark:bg-emerald-500' : 'text-primary bg-[hsl(var(--brand)/0.12)]'"
                            >
                                <Check v-if="isDone(stop)" class="size-5" />
                                <template v-else>{{ i + 1 }}</template>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[17px] font-semibold" :class="isDone(stop) ? '' : 'text-foreground'">
                                    {{ stop.pool?.name ?? 'Pool' }}
                                </p>
                                <div class="flex items-baseline gap-2 text-sm">
                                    <span class="text-muted-foreground min-w-0 flex-1 truncate">{{ stop.pool?.customer }}</span>
                                    <span v-if="isDone(stop)" class="shrink-0 font-medium">Done</span>
                                    <span v-else-if="stop.status === 'skipped'" class="text-muted-foreground shrink-0 font-medium">Skipped</span>
                                </div>
                                <p class="text-muted-foreground truncate text-sm">
                                    {{ stop.extra ? 'Extra visit' : (stop.service.name ?? 'Service visit') }}
                                </p>
                            </div>
                            <a
                                v-if="!isDone(stop) && navUrl(stop)"
                                :href="navUrl(stop) ?? undefined"
                                target="_blank"
                                rel="noopener"
                                class="text-primary grid size-11 shrink-0 place-items-center rounded-xl bg-[hsl(var(--brand)/0.1)]"
                                :aria-label="`Directions to ${stop.pool?.name ?? 'the pool'}`"
                                @click.stop
                            >
                                <Navigation class="size-5" />
                            </a>
                        </li>
                    </ol>

                    <!-- a pool that isn't on the route: added to the end as an extra visit -->
                    <button
                        class="text-primary flex h-12 w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-[hsl(var(--brand)/0.4)] text-base font-semibold disabled:opacity-50"
                        :disabled="!online"
                        @click="addingPool = true"
                    >
                        <Plus class="size-5" /> {{ online ? 'New report' : 'New report (needs a connection)' }}
                    </button>
                </template>
            </main>
        </div>

        <!-- the open visit / New report: full-screen on a phone, the right-hand pane on a desktop -->
        <div class="contents lg:block lg:min-h-0">
            <FieldVisit
                v-if="selected"
                :key="selected.id"
                :stop="selected"
                :online="online"
                :chemicals="bundle?.inventory ?? []"
                :office="!!bundle?.office"
                :past="!!bundle?.past"
                @done="onCompleted"
                @removed="onRemoved"
                @close="selected = null"
            />
            <FieldAddPool
                v-else-if="addingPool"
                :agent="bundle?.office ? bundle.agent.id : null"
                :date="bundle?.past ? bundle.date : null"
                @added="onPoolAdded"
                @close="addingPool = false"
            />
            <div
                v-else
                class="border-border text-muted-foreground hidden h-full items-center justify-center rounded-2xl border border-dashed lg:flex"
            >
                <p class="text-[15px]">Pick a stop to fill in its report.</p>
            </div>
        </div>
    </div>
</template>
