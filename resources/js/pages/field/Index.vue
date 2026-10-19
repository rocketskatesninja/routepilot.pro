<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FieldVisit from '@/components/field/FieldVisit.vue';
import { useAgentTracking } from '@/composables/useAgentTracking';
import { saveBundle, type FieldStop, type TodayBundle } from '@/lib/field/store';
import { QUEUE_SYNCED, failedCount, flushQueue, loadToday, queuedCount, retryFailed } from '@/lib/field/sync';
import { formatDay } from '@/lib/utils';
import { Head, Link } from '@inertiajs/vue3';
import { Check, CloudOff, LoaderCircle, MapPin, Navigation, RefreshCw, TriangleAlert, Wifi, WifiOff } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const { sharing: sharingLocation, toggle: toggleLocation, restore: restoreLocation } = useAgentTracking();

const bundle = ref<TodayBundle | null>(null);
const source = ref<'network' | 'cache' | 'none'>('none');
const loading = ref(true);
const online = ref(true);
const queued = ref(0);
const failed = ref(0);
const selected = ref<FieldStop | null>(null);

async function refreshCounts() {
    queued.value = await queuedCount();
    failed.value = await failedCount();
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

async function load() {
    loading.value = true;
    const result = await loadToday();
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
    // Deep-link: /field?stop=<id> opens that stop directly (e.g. from the dashboard
    // route widget), then strips the param so a refresh lands on the list.
    const stopId = Number(new URLSearchParams(window.location.search).get('stop'));
    if (stopId) {
        const target = stops.value.find((s) => s.id === stopId);
        if (target) open(target);
        window.history.replaceState({}, '', '/field');
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener(QUEUE_SYNCED, refreshCounts);
    window.removeEventListener('offline', onOffline);
});

function open(stop: FieldStop) {
    if (stop.completed || stop.status === 'completed') return;
    selected.value = stop;
}

async function onCompleted(stopId: number) {
    // Optimistic: mark done locally and persist so a reload (even offline) keeps it.
    const stop = stops.value.find((s) => s.id === stopId);
    if (stop) {
        stop.completed = true;
        stop.status = 'completed';
    }
    // Close first: the visit is already saved (queued/synced) at this point, so a re-cache
    // failure below must never block the UI from advancing to the next stop.
    selected.value = null;
    if (bundle.value) {
        try {
            await saveBundle(bundle.value);
        } catch (e) {
            console.error('Failed to re-cache the route for offline use (visit was still saved):', e);
        }
    }
    await refreshCounts();
}
</script>

<template>
    <Head title="Today’s route" />

    <div class="bg-background text-foreground min-h-svh">
        <!-- top bar -->
        <header class="border-border bg-background sticky top-0 z-10 border-b">
            <div class="flex items-center gap-2 px-4 py-2.5">
                <Link
                    href="/dashboard"
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-orange-500 text-white"
                    aria-label="Back to Today"
                >
                    <AppLogoIcon class="size-5" />
                </Link>
                <div class="min-w-0 flex-1 pl-1">
                    <h1 class="font-display truncate text-lg leading-tight font-semibold tracking-tight">Today’s route</h1>
                    <p class="text-muted-foreground truncate text-xs">{{ bundle?.date ? formatDay(bundle.date) : 'Today' }}</p>
                </div>
                <span
                    class="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="
                        online ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                    "
                >
                    <Wifi v-if="online" class="size-3.5" /><WifiOff v-else class="size-3.5" />
                    {{ online ? 'Online' : 'Offline' }}
                </span>
                <button
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
            <div v-if="queued > 0" class="flex items-center gap-1.5 bg-sky-500/10 px-4 py-2 text-sm font-medium text-sky-700 dark:text-sky-300">
                <CloudOff class="size-4" /> {{ queued }} visit{{ queued === 1 ? '' : 's' }} waiting to sync{{
                    online ? '…' : '. They’ll send when you’re back online.'
                }}
            </div>
            <div v-if="source === 'cache'" class="bg-amber-500/10 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                You’re offline, so this is your last saved route.
            </div>
            <div
                v-if="failed > 0"
                class="flex items-center justify-between gap-2 bg-red-500/10 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-400"
            >
                <span class="flex items-center gap-1.5"
                    ><TriangleAlert class="size-4" /> {{ failed }} visit{{ failed === 1 ? '' : 's' }} didn’t sync</span
                >
                <button class="rounded-lg bg-red-500/15 px-3 py-1 font-semibold hover:bg-red-500/25" @click="retry">Try again</button>
            </div>
        </header>

        <!-- body -->
        <main class="mx-auto max-w-2xl space-y-5 p-4">
            <div v-if="loading" class="text-muted-foreground flex justify-center py-20"><LoaderCircle class="size-8 animate-spin" /></div>

            <template v-else>
                <!-- where the day stands -->
                <section class="rounded-2xl bg-[hsl(var(--brand)/0.08)] p-5">
                    <p class="font-display text-2xl leading-tight font-semibold tracking-tight">
                        <template v-if="!stops.length">No stops on your route{{ source === 'none' ? ' yet' : '' }}.</template>
                        <template v-else-if="!remaining">All done for today.</template>
                        <template v-else>{{ remaining }} stop{{ remaining === 1 ? '' : 's' }} to go.</template>
                    </p>
                    <p v-if="!stops.length && source === 'none'" class="text-muted-foreground mt-1 text-[15px]">
                        Connect to the internet to load it.
                    </p>
                    <p v-else-if="next" class="text-muted-foreground mt-1 text-[15px]">
                        Next up: {{ next.pool?.name ?? 'Pool' }}<template v-if="next.eta">, around {{ next.eta }}</template
                        >.
                    </p>
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
                    <!-- Pool name on its own line; the customer shares the next with when the
                         tech's due (or Done / Skipped), which never shrinks; then the service. -->
                    <li
                        v-for="(stop, i) in stops"
                        :key="stop.id"
                        class="flex items-center gap-3 p-4"
                        :class="isDone(stop) ? 'text-muted-foreground' : 'active:bg-muted/60 cursor-pointer'"
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
                                <span v-else-if="stop.eta" class="text-primary shrink-0 font-medium whitespace-nowrap"
                                    ><span class="hidden min-[380px]:inline">around </span>{{ stop.eta }}</span
                                >
                            </div>
                            <p class="text-muted-foreground truncate text-sm">{{ stop.service.name ?? 'Service visit' }}</p>
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
            </template>
        </main>

        <FieldVisit v-if="selected" :stop="selected" :online="online" @done="onCompleted" @close="selected = null" />
    </div>
</template>
