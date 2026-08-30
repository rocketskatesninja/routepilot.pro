<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FieldVisit from '@/components/field/FieldVisit.vue';
import { useAgentTracking } from '@/composables/useAgentTracking';
import { saveBundle, type FieldStop, type TodayBundle } from '@/lib/field/store';
import { failedCount, flushQueue, loadToday, queuedCount, retryFailed } from '@/lib/field/sync';
import { Head, Link } from '@inertiajs/vue3';
import { Check, ChevronRight, CloudOff, LoaderCircle, MapPin, Navigation, RefreshCw, TriangleAlert, Wifi, WifiOff } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const { sharing: sharingLocation, toggle: toggleLocation, restore: restoreLocation, cleanup: cleanupLocation } = useAgentTracking();

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
    if (online.value) await flushQueue();
    await load();
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
});
onBeforeUnmount(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
    cleanupLocation();
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

    <div class="daylight min-h-svh bg-slate-50 text-slate-900">
        <!-- top bar -->
        <header class="sticky top-0 z-10 border-b border-slate-200 bg-white">
            <div class="flex items-center gap-3 px-4 py-3">
                <Link href="/dashboard" class="flex size-9 items-center justify-center rounded-lg bg-orange-500 text-white">
                    <AppLogoIcon class="size-5" />
                </Link>
                <div class="flex-1">
                    <h1 class="font-bold leading-tight">My route</h1>
                    <p class="text-xs text-slate-500">{{ bundle?.date ?? 'Today' }} · {{ done }}/{{ stops.length }} done</p>
                </div>
                <span
                    class="flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold"
                    :class="online ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                >
                    <Wifi v-if="online" class="size-3.5" /><WifiOff v-else class="size-3.5" />
                    {{ online ? 'Online' : 'Offline' }}
                </span>
                <button
                    class="rounded-lg p-2"
                    :class="sharingLocation ? 'bg-sky-100 text-sky-700' : 'text-slate-400 hover:bg-slate-100'"
                    :title="sharingLocation ? 'Sharing your location — tap to stop' : 'Share your location with dispatch'"
                    @click="toggleLocation"
                >
                    <MapPin class="size-4" :class="sharingLocation ? 'animate-pulse' : ''" />
                </button>
                <button class="rounded-lg p-2 hover:bg-slate-100" :disabled="loading" @click="refresh">
                    <RefreshCw class="size-4" :class="loading ? 'animate-spin' : ''" />
                </button>
            </div>
            <div v-if="queued > 0" class="flex items-center gap-1.5 bg-sky-50 px-4 py-1.5 text-xs font-medium text-sky-700">
                <CloudOff class="size-3.5" /> {{ queued }} visit{{ queued === 1 ? '' : 's' }} waiting to sync{{
                    online ? '…' : ' — will send when back online'
                }}
            </div>
            <div v-if="source === 'cache'" class="bg-amber-50 px-4 py-1.5 text-xs font-medium text-amber-700">
                Showing your last saved route (offline).
            </div>
            <div v-if="failed > 0" class="flex items-center justify-between gap-2 bg-red-50 px-4 py-1.5 text-xs font-medium text-red-700">
                <span class="flex items-center gap-1.5"
                    ><TriangleAlert class="size-3.5" /> {{ failed }} visit{{ failed === 1 ? '' : 's' }} failed to sync</span
                >
                <button class="rounded bg-red-100 px-2 py-0.5 font-semibold hover:bg-red-200" @click="retry">Retry</button>
            </div>
        </header>

        <!-- body -->
        <main class="mx-auto max-w-2xl p-4">
            <div v-if="loading" class="flex justify-center py-20 text-slate-400"><LoaderCircle class="size-8 animate-spin" /></div>

            <div v-else-if="!stops.length" class="py-20 text-center text-slate-500">
                <p class="font-medium">No stops on your route{{ source === 'none' ? ' — connect to load it' : '' }}.</p>
            </div>

            <ul v-else class="space-y-2.5">
                <li
                    v-for="stop in stops"
                    :key="stop.id"
                    class="flex items-center gap-3 rounded-xl border bg-white p-3.5 shadow-sm transition active:scale-[0.99]"
                    :class="stop.completed || stop.status === 'completed' ? 'border-emerald-200 opacity-70' : 'border-slate-200'"
                    @click="open(stop)"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        :class="stop.completed || stop.status === 'completed' ? 'bg-emerald-500 text-white' : 'bg-slate-900 text-white'"
                    >
                        <Check v-if="stop.completed || stop.status === 'completed'" class="size-5" />
                        <span v-else>{{ stop.order }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ stop.pool?.name ?? 'Pool' }}</p>
                        <p class="truncate text-sm text-slate-500">
                            {{ stop.pool?.customer }}
                            <span v-if="stop.eta && !(stop.completed || stop.status === 'completed')" class="font-medium text-sky-600"
                                >· ~{{ stop.eta }}</span
                            >
                        </p>
                    </div>
                    <a
                        v-if="navUrl(stop)"
                        :href="navUrl(stop) ?? undefined"
                        target="_blank"
                        rel="noopener"
                        class="rounded-lg p-2 text-sky-600 hover:bg-sky-50"
                        @click.stop
                    >
                        <Navigation class="size-4" />
                    </a>
                    <span class="text-xs font-medium text-slate-400">{{ statusLabel(stop) }}</span>
                    <ChevronRight v-if="!(stop.completed || stop.status === 'completed')" class="size-5 shrink-0 text-slate-300" />
                </li>
            </ul>

            <p v-if="!loading && stops.length" class="mt-4 text-center text-sm text-slate-400">
                {{ remaining }} stop{{ remaining === 1 ? '' : 's' }} left today
            </p>
        </main>

        <FieldVisit v-if="selected" :stop="selected" :online="online" @done="onCompleted" @close="selected = null" />
    </div>
</template>
