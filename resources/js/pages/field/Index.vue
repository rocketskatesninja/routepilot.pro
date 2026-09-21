<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FieldVisit from '@/components/field/FieldVisit.vue';
import { useAgentTracking } from '@/composables/useAgentTracking';
import { saveBundle, type FieldStop, type TodayBundle } from '@/lib/field/store';
import { failedCount, flushQueue, loadToday, queuedCount, retryFailed } from '@/lib/field/sync';
import { Head, Link } from '@inertiajs/vue3';
import { Check, ChevronRight, CloudOff, LoaderCircle, MapPin, Navigation, RefreshCw, TriangleAlert, Wifi, WifiOff } from 'lucide-vue-next';
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
const remaining = computed(() => stops.value.filter((s) => !s.completed && s.status !== 'skipped').length);
const done = computed(() => stops.value.filter((s) => s.completed || s.status === 'completed').length);

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

const statusLabel = (s: FieldStop) => (s.completed || s.status === 'completed' ? 'Done' : s.status === 'skipped' ? 'Skipped' : 'Pending');
</script>

<template>
    <Head title="Field" />

    <div class="min-h-svh bg-background text-foreground">
        <!-- top bar -->
        <header class="sticky top-0 z-10 border-b border-border bg-background">
            <div class="flex items-center gap-3 px-4 py-3">
                <Link href="/dashboard" class="flex size-9 items-center justify-center rounded-lg bg-orange-500 text-white">
                    <AppLogoIcon class="size-5" />
                </Link>
                <div class="flex-1">
                    <h1 class="font-bold leading-tight">My route</h1>
                    <p class="text-xs text-muted-foreground">{{ bundle?.date ?? 'Today' }} · {{ done }}/{{ stops.length }} done</p>
                </div>
                <span
                    class="flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold"
                    :class="
                        online
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'
                    "
                >
                    <Wifi v-if="online" class="size-3.5" /><WifiOff v-else class="size-3.5" />
                    {{ online ? 'Online' : 'Offline' }}
                </span>
                <button
                    class="rounded-lg p-2"
                    :class="sharingLocation ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' : 'text-muted-foreground hover:bg-muted'"
                    :title="sharingLocation ? 'Sharing your location — tap to stop' : 'Share your location with dispatch'"
                    @click="toggleLocation"
                >
                    <MapPin class="size-4" :class="sharingLocation ? 'animate-pulse' : ''" />
                </button>
                <button class="rounded-lg p-2 hover:bg-muted" :disabled="loading" @click="refresh">
                    <RefreshCw class="size-4" :class="loading ? 'animate-spin' : ''" />
                </button>
            </div>
            <div
                v-if="queued > 0"
                class="flex items-center gap-1.5 bg-sky-50 px-4 py-1.5 text-xs font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
            >
                <CloudOff class="size-3.5" /> {{ queued }} visit{{ queued === 1 ? '' : 's' }} waiting to sync{{
                    online ? '…' : ' — will send when back online'
                }}
            </div>
            <div
                v-if="source === 'cache'"
                class="bg-amber-50 px-4 py-1.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400"
            >
                Showing your last saved route (offline).
            </div>
            <div
                v-if="failed > 0"
                class="flex items-center justify-between gap-2 bg-red-50 px-4 py-1.5 text-xs font-medium text-red-700 dark:bg-red-500/10 dark:text-red-400"
            >
                <span class="flex items-center gap-1.5"
                    ><TriangleAlert class="size-3.5" /> {{ failed }} visit{{ failed === 1 ? '' : 's' }} failed to sync</span
                >
                <button
                    class="rounded bg-red-100 px-2 py-0.5 font-semibold hover:bg-red-200 dark:bg-red-500/20 dark:hover:bg-red-500/30"
                    @click="retry"
                >
                    Retry
                </button>
            </div>
        </header>

        <!-- body -->
        <main class="mx-auto max-w-2xl p-4">
            <div v-if="loading" class="flex justify-center py-20 text-muted-foreground"><LoaderCircle class="size-8 animate-spin" /></div>

            <div v-else-if="!stops.length" class="py-20 text-center text-muted-foreground">
                <p class="font-medium">No stops on your route{{ source === 'none' ? ' — connect to load it' : '' }}.</p>
            </div>

            <ul v-else class="space-y-2.5">
                <li
                    v-for="stop in stops"
                    :key="stop.id"
                    class="flex items-center gap-3 rounded-xl border bg-card p-3.5 shadow-sm transition active:scale-[0.99]"
                    :class="
                        stop.completed || stop.status === 'completed' ? 'border-emerald-200 opacity-70 dark:border-emerald-500/30' : 'border-border'
                    "
                    @click="open(stop)"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        :class="stop.completed || stop.status === 'completed' ? 'bg-emerald-500 text-white' : 'bg-foreground text-background'"
                    >
                        <Check v-if="stop.completed || stop.status === 'completed'" class="size-5" />
                        <span v-else>{{ stop.order }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ stop.pool?.name ?? 'Pool' }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ stop.pool?.customer }}
                            <span
                                v-if="stop.eta && !(stop.completed || stop.status === 'completed')"
                                class="font-medium text-sky-600 dark:text-sky-400"
                                >· ~{{ stop.eta }}</span
                            >
                        </p>
                    </div>
                    <a
                        v-if="navUrl(stop)"
                        :href="navUrl(stop) ?? undefined"
                        target="_blank"
                        rel="noopener"
                        class="rounded-lg p-2 text-sky-600 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-500/10"
                        @click.stop
                    >
                        <Navigation class="size-4" />
                    </a>
                    <span class="text-xs font-medium text-muted-foreground">{{ statusLabel(stop) }}</span>
                    <ChevronRight v-if="!(stop.completed || stop.status === 'completed')" class="size-5 shrink-0 text-muted-foreground/50" />
                </li>
            </ul>

            <p v-if="!loading && stops.length" class="mt-4 text-center text-sm text-muted-foreground">
                {{ remaining }} stop{{ remaining === 1 ? '' : 's' }} left today
            </p>
        </main>

        <FieldVisit v-if="selected" :stop="selected" :online="online" @done="onCompleted" @close="selected = null" />
    </div>
</template>
