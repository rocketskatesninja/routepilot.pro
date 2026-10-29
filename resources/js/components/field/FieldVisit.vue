<script setup lang="ts">
import MultiImageUpload from '@/components/MultiImageUpload.vue';
import Chip from '@/components/pane/Chip.vue';
import PaneAction from '@/components/pane/PaneAction.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import WaterChecks from '@/components/portal/WaterChecks.vue';
import { Button } from '@/components/ui/button';
import { fullAnalysis, type FullAnalysis, type Reading, type Recommendation } from '@/lib/chemistry';
import { SUGGESTION_KEYS, toStockAmount } from '@/lib/field/chemicals';
import { shrinkPhoto } from '@/lib/field/photo';
import { isSignedOut, SIGNED_OUT } from '@/lib/field/session';
import {
    deleteDraft,
    getDraft,
    saveDraft,
    saveDraftPhotos,
    type FieldChemical,
    type FieldStop,
    type FieldWorkOrder,
    type TreatmentDraft,
    type WorkOrderDraft,
} from '@/lib/field/store';
import { queueCompletion } from '@/lib/field/sync';
import { deleteJson } from '@/lib/http';
import { telLink } from '@/lib/links';
import { checkWater, waterSummary, type WaterReading } from '@/lib/portal';
import { formatAmount, formatDay, isEmail, unitFor } from '@/lib/utils';
import { ChevronLeft, LoaderCircle, Lock, Navigation, Plus, Sparkles, Trash2, WifiOff, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

// `chemicals`: the company's list (from the day bundle, so it works offline).
// `office`: the office filling in a tech's route (no GPS; it picks whether the customer is emailed).
// `past`: an earlier day (no GPS either — it wouldn't be where the visit happened).
const props = defineProps<{ stop: FieldStop; online: boolean; chemicals: FieldChemical[]; office?: boolean; past?: boolean }>();
const emit = defineEmits<{ (e: 'done', stopId: number, raisedWorkOrder: boolean): void; (e: 'removed'): void; (e: 'close'): void }>();

const pool = computed(() => props.stop.pool);

const orderLine = (o: FieldWorkOrder): string =>
    [o.mine ? 'Yours' : o.assignee ? `Assigned to ${o.assignee}` : 'Not assigned yet', o.due_on ? `due ${formatDay(o.due_on)}` : null]
        .filter(Boolean)
        .join(' · ');

// Which sections this visit shows — the service type's checkboxes on the Services page.
const has = (m: string): boolean => props.stop.service.modules?.[m] ?? true;
const show = { tasks: has('tasks'), chemistry: has('chemistry'), treatments: has('treatments'), photos: has('photos') };

// Reading inputs (strings, as form fields). Keys match the API; `water_temperature`
// is remapped to `temperature` for the chemistry engine.
// `max`: the highest value the server accepts (CompleteVisitRequest); a reading past it is caught here, before the
// visit is saved, since a visit the server refuses would be stuck on the phone.
const readingFields = [
    { key: 'free_chlorine', label: 'Free Cl', unit: 'ppm', max: 100 },
    { key: 'total_chlorine', label: 'Total Cl', unit: 'ppm', max: 100 },
    { key: 'ph', label: 'pH', unit: '', max: 14 },
    { key: 'alkalinity', label: 'Alkalinity', unit: 'ppm', max: 1000 },
    { key: 'calcium_hardness', label: 'Calcium', unit: 'ppm', max: 5000 },
    { key: 'cyanuric_acid', label: 'CYA', unit: 'ppm', max: 1000 },
    { key: 'salt', label: 'Salt', unit: 'ppm', max: 20000 },
    { key: 'water_temperature', label: 'Temp', unit: '°F', max: 120 },
];
/** A reading the server would refuse: not a number, or outside 0 to its max. */
const outOfRange = (f: (typeof readingFields)[number]): boolean => {
    const v = reading[f.key];
    return v !== '' && !(Number(v) >= 0 && Number(v) <= f.max);
};
const reading = reactive<Record<string, string>>(Object.fromEntries(readingFields.map((f) => [f.key, ''])));

const tasks = reactive((props.stop.service.tasks ?? []).map((name) => ({ name, done: false })));
const treatments = reactive<TreatmentDraft[]>([]);
const parts = reactive<TreatmentDraft[]>([]); // repair parts used; chemical_id is the part's id
const notes = ref('');
const officeNotes = ref(''); // for the company only; the homeowner never sees it
// Someone else who gets the report (a landlord, a property manager), remembered on the pool.
const copyTo = ref(pool.value?.report_cc ?? '');
const badCopyTo = computed(() => copyTo.value.trim() !== '' && !isEmail(copyTo.value.trim()));
// Something to do later: becomes a work order on the company's list.
const workOrder = reactive<WorkOrderDraft>({ title: '', details: '', urgent: false, price: '' });
const raisesWorkOrder = computed(() => workOrder.title.trim() !== '' || workOrder.details.trim() !== '');
const photos = ref<File[]>([]);
const analysis = ref<FullAnalysis | null>(null);
const submitting = ref(false);

// The visit so far is kept on the phone as it's filled in, so a refresh (or the
// browser reloading the page behind the camera) picks up where the tech left off.
let restored = false;
let finished = false;
let saveTimer: ReturnType<typeof setTimeout> | undefined;

onMounted(async () => {
    try {
        const { draft, photos: saved } = await getDraft(props.stop.id);
        if (draft) {
            for (const f of readingFields) reading[f.key] = draft.reading[f.key] ?? '';
            tasks.forEach((t, i) => {
                if (draft.tasks[i]?.name === t.name) t.done = draft.tasks[i].done;
            });
            treatments.splice(0, treatments.length, ...restoredTreatments(draft.treatments));
            parts.splice(0, parts.length, ...(draft.parts ?? []));
            notes.value = draft.notes;
            officeNotes.value = draft.office_notes ?? '';
            if (draft.report_cc !== undefined) copyTo.value = draft.report_cc;
            // A draft from the single box keeps its text in Details; the server titles it from the first line.
            Object.assign(workOrder, draft.work_order_form ?? { details: draft.work_order ?? '', urgent: draft.work_order_urgent ?? false });
            if (draft.analyzed) analyze();
        }
        if (saved.length) photos.value = saved;
    } catch (e) {
        console.error('Could not restore the unfinished visit:', e);
    }
    await nextTick(); // let the restore's own watcher runs pass before saving starts
    restored = true;
});

function persist() {
    clearTimeout(saveTimer);
    saveTimer = undefined;
    if (finished) return;
    saveDraft({
        stop_id: props.stop.id,
        reading: { ...reading },
        tasks: tasks.map((t) => ({ name: t.name, done: t.done })),
        treatments: treatments.map((t) => ({ ...t })),
        parts: parts.map((p) => ({ ...p })),
        notes: notes.value,
        office_notes: officeNotes.value,
        report_cc: copyTo.value,
        work_order_form: { ...workOrder },
        analyzed: analysis.value !== null,
        updated_at: Date.now(),
    }).catch((e) => console.error('Could not save the visit on the phone:', e));
}

watch(
    [reading, tasks, treatments, parts, notes, officeNotes, copyTo, workOrder, () => analysis.value !== null],
    () => {
        if (!restored || finished) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persist, 300);
    },
    { deep: true },
);
watch(photos, (files) => {
    if (!restored || finished) return;
    saveDraftPhotos(props.stop.id, files).catch((e) => console.error('Could not save the photos on the phone:', e));
});
// Going back to the route keeps the draft; write any pending keystrokes first.
onBeforeUnmount(() => {
    if (saveTimer !== undefined) persist();
});

/** New photos are shrunk on the phone; removing one just drops it. Finish waits for it. */
const shrinking = ref(false);
async function setPhotos(files: File[]) {
    shrinking.value = true;
    try {
        photos.value = await Promise.all(files.map((f) => (photos.value.includes(f) ? f : shrinkPhoto(f))));
    } finally {
        shrinking.value = false;
    }
}

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

// --- chemicals: picked from the company's list, each a whole number of its own unit ---
const unitOf = (id: number | null): string => props.chemicals.find((c) => c.id === id)?.unit ?? '';
// The list holds repair parts too (bundles from before parts have no kind: all chemicals).
const chemicalList = computed(() => props.chemicals.filter((c) => c.kind !== 'part'));

/**
 * Each suggestion with what Add would record: the company chemical it calls for
 * (found by its standard key; actions like running the generator have none) and
 * the dose in that chemical's unit, rounded up.
 */
interface Suggestion {
    rec: Recommendation;
    chemical: FieldChemical | null;
    amount: number | null;
}
const suggestions = computed<Suggestion[]>(() =>
    (analysis.value?.recommendations ?? []).map((rec) => {
        const key = SUGGESTION_KEYS[rec.chemical];
        const chemical = key ? (props.chemicals.find((c) => c.key === key) ?? null) : null;
        return { rec, chemical, amount: chemical ? toStockAmount(rec.amount, rec.unit, chemical.unit) : null };
    }),
);
const added = (chemical: FieldChemical): boolean => treatments.some((t) => t.chemical_id === chemical.id);

function applySuggestion({ chemical, amount }: Suggestion) {
    if (!chemical || added(chemical)) return;
    treatments.push({ chemical_id: chemical.id, amount: amount !== null ? String(amount) : '' });
}
const addTreatment = () => treatments.push({ chemical_id: null, amount: '' });
const removeTreatment = (i: number) => treatments.splice(i, 1);

/** A saved draft's chemicals; ones typed before the list are matched to it by name, or dropped. */
function restoredTreatments(rows: (TreatmentDraft | { name?: string; amount?: string })[]): TreatmentDraft[] {
    return rows.flatMap((row) => {
        if ('chemical_id' in row) return [row];
        const match = chemicalList.value.find((c) => c.name.toLowerCase() === (row.name ?? '').trim().toLowerCase());
        return match ? [{ chemical_id: match.id, amount: String(Math.ceil(Number(row.amount) || 0) || '') }] : [];
    });
}

// --- repair parts used: on any visit, whenever the company keeps parts; counted in whole units ---
const partList = computed(() => props.chemicals.filter((c) => c.kind === 'part'));
const partLabel = (c: FieldChemical): string => (c.part_number ? `${c.name} · #${c.part_number}` : c.name);
const addPart = () => parts.push({ chemical_id: null, amount: '1' });
const removePart = (i: number) => parts.splice(i, 1);

// Directions to the pool (works offline — the maps app handles the route).
const navUrl = computed(() =>
    pool.value?.lat != null && pool.value?.lng != null
        ? `https://www.google.com/maps/dir/?api=1&destination=${pool.value.lat},${pool.value.lng}`
        : null,
);

/**
 * Best-effort GPS proof-of-presence — never blocks completion if denied/unavailable.
 * A fix from the last couple of minutes is still at the pool, so take that rather
 * than make the tech wait; a fresh one gets 3 seconds at most. The browser only
 * starts that clock once location is allowed, so an unanswered "Allow location?"
 * prompt would hold the visit forever: after 5 seconds in all, it finishes without.
 */
function captureLocation(): Promise<{ lat: number; lng: number } | null> {
    return new Promise((resolve) => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) return resolve(null);
        const giveUp = setTimeout(() => resolve(null), 5000);
        const done = (loc: { lat: number; lng: number } | null) => {
            clearTimeout(giveUp);
            resolve(loc);
        };
        navigator.geolocation.getCurrentPosition(
            (pos) => done({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            () => done(null),
            { enableHighAccuracy: true, timeout: 3000, maximumAge: 120000 },
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

// The office typing a report in can hold back the customer's email (say, an old paper sheet).
const emailCustomer = ref(true);

// While it saves, the form is locked and the button says so; if the phone can't save it, say that and unlock.
const saveError = ref<string | null>(null);

async function complete() {
    const bad = show.chemistry ? readingFields.find(outOfRange) : undefined;
    if (bad) {
        saveError.value = `Check ${bad.label}: it must be between 0 and ${bad.max}${bad.unit ? ` ${bad.unit}` : ''}.`;
        return;
    }
    // The server would refuse the whole visit over a mistyped address, so catch it here.
    if (badCopyTo.value) {
        saveError.value = 'Check the “Also email the report to” address, or clear it.';
        return;
    }
    submitting.value = true;
    saveError.value = null;
    try {
        await saveVisit();
    } catch (e) {
        console.error('Could not save the visit on the phone:', e);
        saveError.value = 'Couldn’t save the visit on this phone. Try again.';
        submitting.value = false;
    }
}

async function saveVisit() {
    const payload: Record<string, unknown> = {
        // Hidden sections send nothing, so the report only carries what this service covers.
        tasks: show.tasks ? tasks.map((t) => ({ name: t.name, done: t.done })) : [],
        treatments: show.treatments
            ? treatments
                  .filter((t) => t.chemical_id !== null && t.amount !== '')
                  .map((t) => ({ chemical_id: t.chemical_id, amount: Number(t.amount) }))
            : [],
        parts: parts.filter((p) => p.chemical_id !== null && p.amount !== '').map((p) => ({ part_id: p.chemical_id, amount: Number(p.amount) })),
        notes: notes.value || null,
        office_notes: officeNotes.value || null,
        report_cc_email: copyTo.value.trim(), // always sent: blank clears it for the pool
        work_order_title: workOrder.title.trim() || null,
        work_order_details: workOrder.details.trim() || null,
        work_order_urgent: raisesWorkOrder.value && workOrder.urgent,
        work_order_price: raisesWorkOrder.value && workOrder.price !== '' ? Number(workOrder.price) : null,
    };
    if (show.chemistry) {
        for (const f of readingFields) {
            if (reading[f.key] !== '') payload[f.key] = Number(reading[f.key]);
        }
    }
    const loc = props.office || props.past ? null : await captureLocation();
    if (props.office) payload.email_customer = emailCustomer.value;
    if (loc) {
        payload.completed_lat = loc.lat;
        payload.completed_lng = loc.lng;
    }
    await queueCompletion(props.stop.id, pool.value?.name ?? 'Pool', payload, show.photos ? photos.value : []);
    // It's in the sync queue now, so the draft has done its job.
    finished = true;
    clearTimeout(saveTimer);
    await deleteDraft(props.stop.id).catch((e) => console.error('Could not clear the finished visit’s draft:', e));
    submitting.value = false;
    emit('done', props.stop.id, raisesWorkOrder.value);
}

// An extra visit added by mistake (or not got to) comes back off the route, with whatever was entered.
const removing = ref(false);
const removeError = ref<string | null>(null);
async function removeFromToday() {
    if (!confirm(`Take ${pool.value?.name ?? 'this pool'} off today’s route? Anything you’ve entered for it is discarded.`)) return;
    removing.value = true;
    removeError.value = null;
    try {
        const res = await deleteJson(`/api/field/stops/${props.stop.id}`);
        if (isSignedOut(res)) {
            removeError.value = SIGNED_OUT;
            return;
        }
        if (!res.ok) throw new Error(String(res.status));
        finished = true;
        clearTimeout(saveTimer);
        await deleteDraft(props.stop.id).catch((e) => console.error('Could not clear the removed visit’s draft:', e));
        emit('removed');
    } catch {
        removeError.value = 'Couldn’t take it off your route. Check your connection and try again.';
    } finally {
        removing.value = false;
    }
}
</script>

<template>
    <!-- A full-screen sheet on a phone; on a desktop, a pane docked beside the route. -->
    <div
        class="bg-background text-foreground lg:border-border fixed inset-0 z-50 flex flex-col lg:relative lg:inset-auto lg:z-auto lg:h-full lg:overflow-hidden lg:rounded-2xl lg:border"
    >
        <!-- header -->
        <header class="border-border bg-background flex items-center gap-2 border-b px-3 py-2.5">
            <button
                class="hover:bg-muted grid size-10 shrink-0 place-items-center rounded-xl disabled:opacity-40"
                aria-label="Back to the route"
                :disabled="submitting"
                @click="emit('close')"
            >
                <ChevronLeft class="size-6 lg:hidden" /><X class="hidden size-6 lg:block" />
            </button>
            <div class="min-w-0 flex-1">
                <h1 class="font-display truncate text-xl leading-tight font-semibold tracking-tight">{{ pool?.name }}</h1>
                <p class="text-muted-foreground truncate text-sm">{{ pool?.customer }}</p>
            </div>
            <!-- Offline, as an icon only (same badge as the route screen's). -->
            <span
                v-if="!online"
                role="status"
                class="grid size-8 shrink-0 place-items-center rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400"
                title="Offline"
                aria-label="Offline"
            >
                <WifiOff class="size-4" />
            </span>
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

        <!-- Locked while the visit saves (inert: no taps, no typing, no focus). -->
        <div
            class="flex-1 space-y-7 overflow-y-auto p-4 transition-opacity lg:px-8 lg:*:mx-auto lg:*:max-w-3xl"
            :class="[{ 'opacity-60': submitting }, office ? 'pb-44' : 'pb-28']"
            :inert="submitting || undefined"
        >
            <!-- getting in -->
            <section class="space-y-2">
                <PaneFacts
                    :facts="[
                        { label: 'Service', value: stop.extra ? 'Extra visit' : stop.service.name },
                        { label: 'Gate code', value: pool?.gate_code },
                        { label: 'Phone', value: pool?.phone, href: pool?.phone ? telLink(pool.phone) : null },
                    ]"
                />
                <p v-if="pool?.access_notes" class="text-muted-foreground text-sm">{{ pool.access_notes }}</p>
            </section>

            <!-- someone else who gets the report (a landlord, a property manager), remembered for this pool -->
            <label class="block space-y-1.5">
                <span class="text-[15px] font-medium">Also email the report to</span>
                <span class="relative block">
                    <input
                        v-model="copyTo"
                        type="email"
                        inputmode="email"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        maxlength="255"
                        placeholder="Optional, if the customer asked"
                        :aria-invalid="badCopyTo || undefined"
                        class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary h-12 w-full rounded-xl border px-3 pr-12 text-base focus:ring-1 focus:outline-none"
                        :class="{ 'border-red-500 ring-1 ring-red-500': badCopyTo }"
                    />
                    <button
                        v-if="copyTo"
                        type="button"
                        aria-label="Clear the address"
                        class="text-muted-foreground hover:text-foreground absolute inset-y-0 right-1 grid w-10 place-items-center"
                        @click="copyTo = ''"
                    >
                        <X class="size-5" />
                    </button>
                </span>
                <span class="text-muted-foreground block text-sm">They get a copy after every visit to this pool. Clear it to stop.</span>
            </label>

            <!-- what's already waiting on this pool (read-only; from the day bundle, so offline too) -->
            <PaneSection v-if="stop.work_orders?.length" title="Open work orders">
                <ul class="space-y-2">
                    <li v-for="o in stop.work_orders" :key="o.id" class="border-border rounded-2xl border px-4 py-3">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="text-[15px] font-medium">{{ o.title }}</span>
                            <Chip v-if="o.urgent" tone="bad">Urgent</Chip>
                            <Chip v-if="o.overdue" tone="warn">Overdue</Chip>
                        </p>
                        <p v-if="o.details" class="text-muted-foreground mt-1 text-sm whitespace-pre-line">{{ o.details }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">{{ orderLine(o) }}</p>
                    </li>
                </ul>
            </PaneSection>

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
                <p class="text-muted-foreground mb-3 text-sm">Leave a box blank to keep that reading off the report.</p>
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
                            min="0"
                            :max="f.max"
                            :aria-invalid="outOfRange(f) || undefined"
                            class="border-input bg-background text-foreground focus:border-primary focus:ring-primary h-12 w-full rounded-xl border px-3 text-base focus:ring-1 focus:outline-none"
                            :class="{ 'border-red-500 ring-1 ring-red-500': outOfRange(f) }"
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
                <p v-if="!suggestions.length" class="text-muted-foreground text-sm">Nothing to add. The water is in range.</p>
                <ul v-else class="border-border divide-border divide-y overflow-hidden rounded-2xl border">
                    <li v-for="(s, i) in suggestions" :key="i" class="flex items-start gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ s.rec.chemical }}</p>
                                <Chip v-if="s.rec.urgency !== 'low'" :tone="s.rec.urgency === 'high' ? 'bad' : 'warn'">{{
                                    s.rec.urgency === 'high' ? 'Needed now' : 'Soon'
                                }}</Chip>
                            </div>
                            <p class="text-muted-foreground text-sm">
                                {{ s.rec.parameter }}<template v-if="s.rec.amount">: {{ s.rec.amount }} {{ s.rec.unit }}</template>
                            </p>
                            <ul v-if="s.rec.notes?.length" class="text-muted-foreground mt-1 list-disc pl-5 text-sm">
                                <li v-for="(n, j) in s.rec.notes" :key="j">{{ n }}</li>
                            </ul>
                        </div>
                        <!-- Only a chemical the company keeps gets Add: it records the dose in that chemical's unit, rounded up. -->
                        <Button
                            v-if="show.treatments && s.chemical"
                            size="sm"
                            variant="outline"
                            class="h-10 shrink-0 rounded-xl"
                            :disabled="added(s.chemical)"
                            @click="applySuggestion(s)"
                        >
                            <Plus class="mr-1 size-4" />
                            {{ added(s.chemical) ? 'Added' : s.amount !== null ? `Add ${formatAmount(s.amount, s.chemical.unit)}` : 'Add' }}
                        </Button>
                    </li>
                </ul>
            </PaneSection>

            <!-- chemicals added -->
            <PaneSection v-if="show.treatments" title="Chemicals added">
                <template v-if="chemicalList.length" #action>
                    <PaneAction :icon="Plus" @click="addTreatment">Add one</PaneAction>
                </template>
                <p v-if="!chemicalList.length" class="text-muted-foreground text-sm">
                    No chemicals set up yet. The office adds them on the Inventory page.
                </p>
                <p v-else-if="!treatments.length" class="text-muted-foreground text-sm">None yet. Add one from “What to add”, or pick your own.</p>
                <div v-for="(t, i) in treatments" :key="i" class="mb-2 flex items-center gap-2">
                    <select
                        v-model="t.chemical_id"
                        aria-label="Chemical"
                        class="border-input bg-background text-foreground h-11 min-w-0 flex-1 rounded-xl border px-3 text-base"
                    >
                        <option :value="null" disabled>Pick a chemical</option>
                        <option v-for="c in chemicalList" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <!-- Whole units only: anything less is rounded up when it's saved. -->
                    <input
                        v-model="t.amount"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        step="1"
                        placeholder="Amt"
                        aria-label="Amount"
                        class="border-input bg-background text-foreground placeholder:text-muted-foreground h-11 w-20 rounded-xl border px-3 text-base"
                    />
                    <span class="text-muted-foreground w-12 shrink-0 text-base">{{ unitFor(Number(t.amount), unitOf(t.chemical_id)) }}</span>
                    <button
                        class="text-muted-foreground hover:bg-muted grid size-11 place-items-center rounded-xl"
                        aria-label="Remove"
                        @click="removeTreatment(i)"
                    >
                        <X class="size-5" />
                    </button>
                </div>
            </PaneSection>

            <!-- repair parts used: on every visit, whatever the service -->
            <PaneSection v-if="partList.length" title="Parts used">
                <template #action>
                    <PaneAction :icon="Plus" @click="addPart">Add a part</PaneAction>
                </template>
                <p v-if="!parts.length" class="text-muted-foreground text-sm">Replaced something? Add the part so it comes off the shelf.</p>
                <div v-for="(p, i) in parts" :key="i" class="mb-2 flex items-center gap-2">
                    <select
                        v-model="p.chemical_id"
                        aria-label="Part"
                        class="border-input bg-background text-foreground h-11 min-w-0 flex-1 rounded-xl border px-3 text-base"
                    >
                        <option :value="null" disabled>Pick a part</option>
                        <option v-for="c in partList" :key="c.id" :value="c.id">{{ partLabel(c) }}</option>
                    </select>
                    <input
                        v-model="p.amount"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        step="1"
                        placeholder="Qty"
                        aria-label="How many"
                        class="border-input bg-background text-foreground placeholder:text-muted-foreground h-11 w-20 rounded-xl border px-3 text-base"
                    />
                    <span class="text-muted-foreground w-12 shrink-0 text-base">{{ unitFor(Number(p.amount), 'unit') }}</span>
                    <button
                        class="text-muted-foreground hover:bg-muted grid size-11 place-items-center rounded-xl"
                        aria-label="Remove"
                        @click="removePart(i)"
                    >
                        <X class="size-5" />
                    </button>
                </div>
            </PaneSection>

            <!-- photos -->
            <PaneSection v-if="show.photos" title="Photos">
                <MultiImageUpload :model-value="photos" capture="environment" :max="6" @update:model-value="setPhotos" />
            </PaneSection>

            <!-- two notes just before finishing: one the homeowner reads, one only the company sees -->
            <PaneSection title="Note to the homeowner">
                <textarea
                    v-model="notes"
                    maxlength="2000"
                    rows="3"
                    aria-label="Note to the homeowner"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground w-full rounded-xl border px-3 py-2.5 text-base"
                    placeholder="The homeowner sees this in their report and email."
                ></textarea>
            </PaneSection>

            <PaneSection title="Office note">
                <template #action>
                    <span class="text-muted-foreground flex items-center gap-1 text-sm"><Lock class="size-3.5" /> Only your team sees this</span>
                </template>
                <textarea
                    v-model="officeNotes"
                    maxlength="2000"
                    rows="3"
                    aria-label="Office note"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground w-full rounded-xl border px-3 py-2.5 text-base"
                    placeholder="Anything the office should know: repairs, access trouble, a word about the customer."
                ></textarea>
            </PaneSection>

            <!-- a job for later, apart from the note: it lands on the company's work orders list -->
            <PaneSection title="Work order">
                <template #action>
                    <span class="text-muted-foreground flex items-center gap-1 text-sm"><Lock class="size-3.5" /> Goes on your team’s list</span>
                </template>
                <input
                    v-model="workOrder.title"
                    type="text"
                    maxlength="160"
                    aria-label="Work order title"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground w-full rounded-xl border px-3 py-2.5 text-base"
                    placeholder="Title: what needs doing, e.g. Replace the skimmer lid"
                />
                <textarea
                    v-model="workOrder.details"
                    maxlength="5000"
                    rows="3"
                    aria-label="Work order details"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground mt-2 w-full rounded-xl border px-3 py-2.5 text-base"
                    placeholder="Details (optional): part size, where it is, anything that helps."
                ></textarea>
                <div v-if="raisesWorkOrder" class="mt-2 flex flex-wrap items-center justify-between gap-3">
                    <label class="flex cursor-pointer items-center gap-3 py-1">
                        <input v-model="workOrder.urgent" type="checkbox" class="border-input accent-primary size-6 shrink-0 rounded" />
                        <span class="text-[15px]">Urgent</span>
                    </label>
                    <label class="flex items-center gap-2 text-[15px]">
                        Price $
                        <input
                            v-model="workOrder.price"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="Optional"
                            aria-label="Work order price"
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground w-28 rounded-xl border px-3 py-2 text-base"
                        />
                    </label>
                </div>
            </PaneSection>

            <!-- an extra visit can come back off the route until it's done -->
            <section v-if="stop.extra" class="border-border space-y-2 border-t pt-5">
                <p v-if="removeError" role="alert" class="text-sm font-medium text-red-700 dark:text-red-400">{{ removeError }}</p>
                <button
                    class="flex h-11 items-center gap-2 rounded-xl px-1 text-base font-semibold text-red-700 disabled:opacity-50 dark:text-red-400"
                    :disabled="!online || removing"
                    @click="removeFromToday"
                >
                    <Trash2 class="size-5" />
                    {{ online ? (past ? 'Remove from this day' : 'Remove from today') : 'Remove from today (needs a connection)' }}
                </button>
            </section>
        </div>

        <!-- complete -->
        <footer class="border-border bg-background absolute inset-x-0 bottom-0 border-t p-4 lg:px-8">
            <div class="lg:mx-auto lg:max-w-3xl">
                <label v-if="office" class="mb-3 flex items-center gap-2 text-[15px]">
                    <input v-model="emailCustomer" type="checkbox" class="border-input accent-primary size-5 shrink-0 rounded" />
                    Email this report to the customer
                </label>
                <p v-if="saveError" role="alert" class="mb-2 text-sm font-medium text-red-700 dark:text-red-400">{{ saveError }}</p>
                <Button class="h-12 w-full rounded-xl text-base" :disabled="submitting || shrinking" @click="complete">
                    <template v-if="submitting"><LoaderCircle class="mr-2 size-5 animate-spin" /> Saving…</template>
                    <template v-else>{{ online ? 'Finish visit' : 'Finish visit (sends when you’re back online)' }}</template>
                </Button>
            </div>
        </footer>
    </div>
</template>
