<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { fullAnalysis, type FullAnalysis, type Reading } from '@/lib/chemistry';
import { type FieldStop } from '@/lib/field/store';
import { queueCompletion } from '@/lib/field/sync';
import { ChevronLeft, FlaskConical, Navigation, Plus, Sparkles, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{ stop: FieldStop; online: boolean }>();
const emit = defineEmits<{ (e: 'done', stopId: number): void; (e: 'close'): void }>();

const pool = computed(() => props.stop.pool);

// Reading inputs (strings, as form fields). Keys match the API; `water_temperature`
// is remapped to `temperature` for the chemistry engine.
const readingFields = [
    { key: 'free_chlorine', label: 'Free Cl', unit: 'ppm' },
    { key: 'total_chlorine', label: 'Total Cl', unit: 'ppm' },
    { key: 'ph', label: 'pH', unit: '' },
    { key: 'alkalinity', label: 'Alkalinity', unit: 'ppm' },
    { key: 'calcium_hardness', label: 'Calcium', unit: 'ppm' },
    { key: 'cyanuric_acid', label: 'CYA', unit: 'ppm' },
    { key: 'salt', label: 'Salt', unit: 'ppm' },
    { key: 'water_temperature', label: 'Temp', unit: '°F' },
];
const reading = reactive<Record<string, string>>(Object.fromEntries(readingFields.map((f) => [f.key, ''])));

const tasks = reactive((props.stop.service.tasks ?? []).map((name) => ({ name, done: false })));
const treatments = reactive<{ name: string; amount: string; unit: string }[]>([]);
const notes = ref('');
const analysis = ref<FullAnalysis | null>(null);
const submitting = ref(false);

/** Build a numeric reading for the chemistry engine (water_temperature → temperature). */
function numericReading(): Reading {
    const out: Reading = {};
    for (const f of readingFields) {
        const v = reading[f.key];
        if (v === '' || v === null) continue;
        out[f.key === 'water_temperature' ? 'temperature' : f.key] = Number(v);
    }
    return out;
}

/** On-device analysis — no network. */
function analyze() {
    analysis.value = fullAnalysis(numericReading(), {
        volume_gallons: pool.value?.volume_gallons ?? null,
        sanitizer: pool.value?.sanitizer ?? null,
        custom_target_ranges: pool.value?.custom_target_ranges ?? null,
    });
}

function applyRec(chemical: string, amount: number, unit: string) {
    if (treatments.some((t) => t.name === chemical)) return;
    treatments.push({ name: chemical, amount: amount > 0 ? String(amount) : '', unit });
}
const addTreatment = () => treatments.push({ name: '', amount: '', unit: 'oz' });
const removeTreatment = (i: number) => treatments.splice(i, 1);

// Directions to the pool (works offline — the maps app handles the route).
const navUrl = computed(() =>
    pool.value?.lat != null && pool.value?.lng != null
        ? `https://www.google.com/maps/dir/?api=1&destination=${pool.value.lat},${pool.value.lng}`
        : null,
);

/** Best-effort GPS proof-of-presence — never blocks completion if denied/unavailable. */
function captureLocation(): Promise<{ lat: number; lng: number } | null> {
    return new Promise((resolve) => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 },
        );
    });
}

const lsiTone = computed(() => {
    const s = analysis.value?.lsi.status;
    return s === 'balanced' ? 'text-emerald-600' : s === 'corrosive' ? 'text-red-600' : 'text-amber-600';
});
const urgencyTone = (u: string) =>
    u === 'high' ? 'border-red-300 bg-red-50' : u === 'medium' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50';

async function complete() {
    submitting.value = true;
    const payload: Record<string, unknown> = {
        tasks: tasks.map((t) => ({ name: t.name, done: t.done })),
        treatments: treatments
            .filter((t) => t.name !== '')
            .map((t) => ({ name: t.name, amount: t.amount === '' ? null : Number(t.amount), unit: t.unit })),
        notes: notes.value || null,
    };
    for (const f of readingFields) {
        if (reading[f.key] !== '') payload[f.key] = Number(reading[f.key]);
    }
    const loc = await captureLocation();
    if (loc) {
        payload.completed_lat = loc.lat;
        payload.completed_lng = loc.lng;
    }
    await queueCompletion(props.stop.id, pool.value?.name ?? 'Pool', payload);
    submitting.value = false;
    emit('done', props.stop.id);
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-slate-50 text-slate-900 [color-scheme:light]">
        <!-- header -->
        <header class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
            <button class="rounded-lg p-1.5 hover:bg-slate-100" @click="emit('close')"><ChevronLeft class="size-5" /></button>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-bold">{{ pool?.name }}</h1>
                <p class="truncate text-sm text-slate-500">{{ pool?.customer }}</p>
            </div>
            <a
                v-if="navUrl"
                :href="navUrl"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-1 rounded-lg bg-sky-50 px-2.5 py-1.5 text-sm font-semibold text-sky-700"
            >
                <Navigation class="size-4" /> Navigate
            </a>
            <span v-if="!online" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Offline</span>
        </header>

        <div class="flex-1 space-y-4 overflow-y-auto p-4 pb-28">
            <!-- access info -->
            <div v-if="pool?.gate_code || pool?.access_notes || pool?.phone" class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                <p v-if="pool?.gate_code"><span class="font-semibold">Gate:</span> {{ pool.gate_code }}</p>
                <p v-if="pool?.phone"><span class="font-semibold">Phone:</span> {{ pool.phone }}</p>
                <p v-if="pool?.access_notes" class="text-slate-600">{{ pool.access_notes }}</p>
            </div>

            <!-- checklist: the physical checklist comes first -->
            <section v-if="tasks.length" class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="mb-3 font-semibold">{{ stop.service.name || 'Service' }} checklist</h2>
                <label v-for="(t, i) in tasks" :key="i" class="flex items-center gap-3 py-1.5">
                    <input v-model="t.done" type="checkbox" class="size-5 rounded border-slate-300 text-sky-600" />
                    <span :class="t.done ? 'text-slate-400 line-through' : ''">{{ t.name }}</span>
                </label>
            </section>

            <!-- notes -->
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="mb-2 font-semibold">Notes</h2>
                <textarea
                    v-model="notes"
                    rows="3"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Anything to flag for the office or the homeowner…"
                ></textarea>
            </section>

            <!-- readings: the water test + dosing is the last thing before completing -->
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="mb-3 flex items-center gap-2 font-semibold"><FlaskConical class="size-4 text-sky-500" /> Water test</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <label v-for="f in readingFields" :key="f.key" class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500"
                            >{{ f.label }} <span v-if="f.unit" class="text-slate-400">{{ f.unit }}</span></span
                        >
                        <input
                            v-model="reading[f.key]"
                            type="number"
                            inputmode="decimal"
                            step="any"
                            class="w-full rounded-lg border border-slate-300 px-2 py-2 text-base focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                    </label>
                </div>
                <Button class="mt-3 w-full" variant="outline" @click="analyze"><Sparkles class="mr-1 size-4" /> Analyze (offline)</Button>
            </section>

            <!-- analysis -->
            <section v-if="analysis" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Analysis</h2>
                    <span class="text-sm font-semibold" :class="lsiTone">LSI {{ analysis.lsi.value }} · {{ analysis.lsi.label }}</span>
                </div>
                <p v-if="!analysis.recommendations.length" class="text-sm text-slate-500">No dosing needed — chemistry is in range.</p>
                <ul v-else class="space-y-2">
                    <li v-for="(rec, i) in analysis.recommendations" :key="i" class="rounded-lg border p-3" :class="urgencyTone(rec.urgency)">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ rec.chemical }}</p>
                                <p class="text-sm text-slate-600">
                                    {{ rec.parameter }}<span v-if="rec.amount"> · {{ rec.amount }} {{ rec.unit }}</span>
                                </p>
                            </div>
                            <Button size="sm" variant="outline" @click="applyRec(rec.chemical, rec.amount, rec.unit)"><Plus class="size-4" /></Button>
                        </div>
                        <ul v-if="rec.notes?.length" class="mt-1 list-disc pl-5 text-xs text-slate-500">
                            <li v-for="(n, j) in rec.notes" :key="j">{{ n }}</li>
                        </ul>
                    </li>
                </ul>
            </section>

            <!-- treatments -->
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Treatments applied</h2>
                    <Button size="sm" variant="outline" @click="addTreatment"><Plus class="mr-1 size-4" /> Add</Button>
                </div>
                <p v-if="!treatments.length" class="text-sm text-slate-500">None yet — apply a recommendation or add one.</p>
                <div v-for="(t, i) in treatments" :key="i" class="mb-2 flex items-center gap-2">
                    <input v-model="t.name" placeholder="Chemical" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-2 py-2 text-sm" />
                    <input
                        v-model="t.amount"
                        type="number"
                        inputmode="decimal"
                        step="any"
                        placeholder="Amt"
                        class="w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm"
                    />
                    <input v-model="t.unit" class="w-16 rounded-lg border border-slate-300 px-2 py-2 text-sm" />
                    <button class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="removeTreatment(i)"><X class="size-4" /></button>
                </div>
            </section>
        </div>

        <!-- complete -->
        <footer class="absolute inset-x-0 bottom-0 border-t border-slate-200 bg-white p-4">
            <Button class="h-12 w-full text-base" :disabled="submitting" @click="complete">
                {{ online ? 'Complete visit' : 'Complete (will sync when online)' }}
            </Button>
        </footer>
    </div>
</template>
