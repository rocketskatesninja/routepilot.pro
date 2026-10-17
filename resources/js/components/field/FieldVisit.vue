<script setup lang="ts">
import MultiImageUpload from '@/components/MultiImageUpload.vue';
import Chip from '@/components/pane/Chip.vue';
import PaneAction from '@/components/pane/PaneAction.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import WaterChecks from '@/components/portal/WaterChecks.vue';
import { Button } from '@/components/ui/button';
import { fullAnalysis, type FullAnalysis, type Reading } from '@/lib/chemistry';
import { type FieldStop } from '@/lib/field/store';
import { queueCompletion } from '@/lib/field/sync';
import { telLink } from '@/lib/links';
import { checkWater, waterSummary, type WaterReading } from '@/lib/portal';
import { ChevronLeft, Navigation, Plus, Sparkles, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{ stop: FieldStop; online: boolean }>();
const emit = defineEmits<{ (e: 'done', stopId: number): void; (e: 'close'): void }>();

const pool = computed(() => props.stop.pool);

// Which sections this visit shows — the service type's checkboxes on the Services page.
const has = (m: string): boolean => props.stop.service.modules?.[m] ?? true;
const show = { tasks: has('tasks'), chemistry: has('chemistry'), treatments: has('treatments'), photos: has('photos') };

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
const photos = ref<File[]>([]);
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

// What they've typed so far, checked against this pool's targets — the same
// good / low / high the homeowner will see in their report.
const liveChecks = computed(() => {
    const entered: WaterReading = {};
    for (const key of ['free_chlorine', 'ph', 'alkalinity', 'calcium_hardness', 'cyanuric_acid', 'salt'] as const) {
        if (reading[key] !== '') entered[key] = Number(reading[key]);
    }
    return checkWater(entered, pool.value?.custom_target_ranges ?? null);
});

async function complete() {
    submitting.value = true;
    const payload: Record<string, unknown> = {
        // Hidden sections send nothing, so the report only carries what this service covers.
        tasks: show.tasks ? tasks.map((t) => ({ name: t.name, done: t.done })) : [],
        treatments: show.treatments
            ? treatments.filter((t) => t.name !== '').map((t) => ({ name: t.name, amount: t.amount === '' ? null : Number(t.amount), unit: t.unit }))
            : [],
        notes: notes.value || null,
    };
    if (show.chemistry) {
        for (const f of readingFields) {
            if (reading[f.key] !== '') payload[f.key] = Number(reading[f.key]);
        }
    }
    const loc = await captureLocation();
    if (loc) {
        payload.completed_lat = loc.lat;
        payload.completed_lng = loc.lng;
    }
    await queueCompletion(props.stop.id, pool.value?.name ?? 'Pool', payload, show.photos ? photos.value : []);
    submitting.value = false;
    emit('done', props.stop.id);
}
</script>

<template>
    <div class="bg-background text-foreground fixed inset-0 z-50 flex flex-col">
        <!-- header -->
        <header class="border-border bg-background flex items-center gap-2 border-b px-3 py-2.5">
            <button class="hover:bg-muted grid size-10 shrink-0 place-items-center rounded-xl" aria-label="Back to the route" @click="emit('close')">
                <ChevronLeft class="size-6" />
            </button>
            <div class="min-w-0 flex-1">
                <h1 class="font-display truncate text-xl leading-tight font-semibold tracking-tight">{{ pool?.name }}</h1>
                <p class="text-muted-foreground truncate text-sm">{{ pool?.customer }}</p>
            </div>
            <span v-if="!online" class="rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-400"
                >Offline</span
            >
            <a
                v-if="navUrl"
                :href="navUrl"
                target="_blank"
                rel="noopener"
                class="text-primary flex h-10 shrink-0 items-center gap-1.5 rounded-xl bg-[hsl(var(--brand)/0.1)] px-3 text-sm font-semibold"
            >
                <Navigation class="size-4" /> Directions
            </a>
        </header>

        <div class="flex-1 space-y-7 overflow-y-auto p-4 pb-28">
            <!-- getting in -->
            <section class="space-y-2">
                <PaneFacts
                    :facts="[
                        { label: 'Service', value: stop.service.name },
                        { label: 'Gate code', value: pool?.gate_code },
                        { label: 'Phone', value: pool?.phone, href: pool?.phone ? telLink(pool.phone) : null },
                    ]"
                />
                <p v-if="pool?.access_notes" class="text-muted-foreground text-sm">{{ pool.access_notes }}</p>
            </section>

            <!-- checklist: the physical work comes first -->
            <PaneSection v-if="show.tasks && tasks.length" title="Checklist">
                <template #action>
                    <span class="text-muted-foreground text-sm">{{ tasks.filter((t) => t.done).length }} of {{ tasks.length }}</span>
                </template>
                <ul class="border-border divide-border divide-y overflow-hidden rounded-2xl border">
                    <li v-for="(t, i) in tasks" :key="i">
                        <label class="active:bg-muted/60 flex cursor-pointer items-center gap-3 px-4 py-3.5">
                            <input v-model="t.done" type="checkbox" class="border-input accent-primary size-6 shrink-0 rounded" />
                            <span class="text-[15px]" :class="t.done ? 'text-muted-foreground line-through' : ''">{{ t.name }}</span>
                        </label>
                    </li>
                </ul>
            </PaneSection>

            <!-- the water test: what they read, checked against this pool's targets as they type -->
            <PaneSection v-if="show.chemistry" title="Water test">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <label v-for="f in readingFields" :key="f.key" class="block">
                        <span class="text-muted-foreground mb-1 block text-sm font-medium"
                            >{{ f.label }} <span v-if="f.unit" class="text-muted-foreground/70 font-normal">{{ f.unit }}</span></span
                        >
                        <input
                            v-model="reading[f.key]"
                            type="number"
                            inputmode="decimal"
                            step="any"
                            class="border-input bg-background text-foreground focus:border-primary focus:ring-primary h-12 w-full rounded-xl border px-3 text-base focus:ring-1 focus:outline-none"
                        />
                    </label>
                </div>
                <template v-if="liveChecks.length">
                    <p class="text-muted-foreground mt-4 text-sm">{{ waterSummary(liveChecks, false) }}</p>
                    <div class="border-border mt-2 rounded-2xl border px-4">
                        <WaterChecks :checks="liveChecks" :explain="false" />
                    </div>
                </template>
                <Button class="mt-3 h-11 w-full rounded-xl" variant="outline" @click="analyze"
                    ><Sparkles class="mr-1.5 size-4" /> Work out what to add</Button
                >
            </PaneSection>

            <!-- dosing advice, worked out on the phone -->
            <PaneSection v-if="show.chemistry && analysis" title="What to add">
                <template #action>
                    <Chip :tone="analysis.lsi.status === 'balanced' ? 'good' : analysis.lsi.status === 'corrosive' ? 'bad' : 'warn'"
                        >LSI {{ analysis.lsi.value }}, {{ analysis.lsi.label.toLowerCase() }}</Chip
                    >
                </template>
                <p v-if="!analysis.recommendations.length" class="text-muted-foreground text-sm">Nothing to add. The water is in range.</p>
                <ul v-else class="border-border divide-border divide-y overflow-hidden rounded-2xl border">
                    <li v-for="(rec, i) in analysis.recommendations" :key="i" class="flex items-start gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ rec.chemical }}</p>
                                <Chip v-if="rec.urgency !== 'low'" :tone="rec.urgency === 'high' ? 'bad' : 'warn'">{{
                                    rec.urgency === 'high' ? 'Needed now' : 'Soon'
                                }}</Chip>
                            </div>
                            <p class="text-muted-foreground text-sm">
                                {{ rec.parameter }}<template v-if="rec.amount">: {{ rec.amount }} {{ rec.unit }}</template>
                            </p>
                            <ul v-if="rec.notes?.length" class="text-muted-foreground mt-1 list-disc pl-5 text-sm">
                                <li v-for="(n, j) in rec.notes" :key="j">{{ n }}</li>
                            </ul>
                        </div>
                        <Button
                            v-if="show.treatments"
                            size="sm"
                            variant="outline"
                            class="h-10 shrink-0 rounded-xl"
                            :disabled="treatments.some((t) => t.name === rec.chemical)"
                            @click="applyRec(rec.chemical, rec.amount, rec.unit)"
                        >
                            <Plus class="mr-1 size-4" /> {{ treatments.some((t) => t.name === rec.chemical) ? 'Added' : 'Add' }}
                        </Button>
                    </li>
                </ul>
            </PaneSection>

            <!-- chemicals added -->
            <PaneSection v-if="show.treatments" title="Chemicals added">
                <template #action>
                    <PaneAction :icon="Plus" @click="addTreatment">Add one</PaneAction>
                </template>
                <p v-if="!treatments.length" class="text-muted-foreground text-sm">None yet. Add one from “What to add”, or add your own.</p>
                <div v-for="(t, i) in treatments" :key="i" class="mb-2 flex items-center gap-2">
                    <input
                        v-model="t.name"
                        placeholder="Chemical"
                        aria-label="Chemical"
                        class="border-input bg-background text-foreground placeholder:text-muted-foreground h-11 min-w-0 flex-1 rounded-xl border px-3 text-base"
                    />
                    <input
                        v-model="t.amount"
                        type="number"
                        inputmode="decimal"
                        step="any"
                        placeholder="Amt"
                        aria-label="Amount"
                        class="border-input bg-background text-foreground placeholder:text-muted-foreground h-11 w-20 rounded-xl border px-3 text-base"
                    />
                    <input
                        v-model="t.unit"
                        aria-label="Unit"
                        class="border-input bg-background text-foreground h-11 w-16 rounded-xl border px-3 text-base"
                    />
                    <button
                        class="text-muted-foreground hover:bg-muted grid size-11 place-items-center rounded-xl"
                        aria-label="Remove"
                        @click="removeTreatment(i)"
                    >
                        <X class="size-5" />
                    </button>
                </div>
            </PaneSection>

            <!-- photos -->
            <PaneSection v-if="show.photos" title="Photos">
                <MultiImageUpload :model-value="photos" capture="environment" @update:model-value="(f) => (photos = f)" />
            </PaneSection>

            <!-- a note for the office and the homeowner, just before finishing -->
            <PaneSection title="Note">
                <textarea
                    v-model="notes"
                    rows="3"
                    aria-label="Note"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground w-full rounded-xl border px-3 py-2.5 text-base"
                    placeholder="Anything the office or the homeowner should know. The homeowner sees this in their report."
                ></textarea>
            </PaneSection>
        </div>

        <!-- complete -->
        <footer class="border-border bg-background absolute inset-x-0 bottom-0 border-t p-4">
            <Button class="h-12 w-full rounded-xl text-base" :disabled="submitting" @click="complete">
                {{ online ? 'Finish visit' : 'Finish visit (sends when you’re back online)' }}
            </Button>
        </footer>
    </div>
</template>
