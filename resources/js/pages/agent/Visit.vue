<script setup lang="ts">
import MultiImageUpload from '@/components/MultiImageUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/http';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { FlaskConical, Plus, Sparkles, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Recommendation {
    parameter: string;
    chemical: string;
    amount: number;
    unit?: string;
    urgency: string;
}

interface ExistingVisit {
    id: number;
    notes: string | null;
    reading: Record<string, number | null> | null;
    treatments: { name: string; amount: number | null; unit: string | null }[];
    tasks: { name: string; done: boolean }[];
    photos: string[];
}

const props = defineProps<{
    stop: { id: number; status: string };
    visit: ExistingVisit | null;
    pool: {
        name: string;
        customer: string;
        type: string;
        volume_gallons: number | null;
        sanitizer: string;
        gate_code: string | null;
        access_notes: string | null;
    };
    service: { name: string | null; tasks: string[] };
    last_reading: { on: string | null; free_chlorine: number | null; ph: number | null; alkalinity: number | null; lsi_score: number | null } | null;
}>();

const isEditing = computed(() => props.visit !== null);

// String value for a saved reading field (form inputs are strings), '' when unset.
const readingValue = (key: string): string => {
    const v = props.visit?.reading?.[key];
    return v === null || v === undefined ? '' : String(v);
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Today', href: '/dashboard' },
    { title: props.pool.name, href: `/visit/${props.stop.id}` },
];

const readingFields = [
    { key: 'free_chlorine', label: 'Free Cl' },
    { key: 'ph', label: 'pH' },
    { key: 'alkalinity', label: 'Alkalinity' },
    { key: 'calcium_hardness', label: 'Calcium' },
    { key: 'cyanuric_acid', label: 'CYA' },
    { key: 'salt', label: 'Salt' },
    { key: 'water_temperature', label: 'Temp °F' },
] as const;

// Temp shares a row with the Analyze button; the rest fill the grid above.
const chemFields = readingFields.filter((f) => f.key !== 'water_temperature');

const form = useForm<{
    free_chlorine: string;
    total_chlorine: string;
    ph: string;
    alkalinity: string;
    calcium_hardness: string;
    cyanuric_acid: string;
    salt: string;
    water_temperature: string;
    tasks: { name: string; done: boolean }[];
    treatments: { name: string; amount: string; unit: string }[];
    notes: string;
    photos: File[];
}>({
    free_chlorine: readingValue('free_chlorine'),
    total_chlorine: readingValue('total_chlorine'),
    ph: readingValue('ph'),
    alkalinity: readingValue('alkalinity'),
    calcium_hardness: readingValue('calcium_hardness'),
    cyanuric_acid: readingValue('cyanuric_acid'),
    salt: readingValue('salt'),
    water_temperature: readingValue('water_temperature'),
    // Editing: keep the saved checklist (with its done-states); otherwise seed from the service template.
    tasks: props.visit ? props.visit.tasks.map((t) => ({ name: t.name, done: t.done })) : props.service.tasks.map((name) => ({ name, done: false })),
    treatments: (props.visit?.treatments ?? []).map((t) => ({
        name: t.name,
        amount: t.amount === null ? '' : String(t.amount),
        unit: t.unit ?? 'oz',
    })),
    notes: props.visit?.notes ?? '',
    photos: [],
});

const recommendations = ref<Recommendation[]>([]);
const analyzing = ref(false);

const num = (v: string) => (v === '' ? null : Number(v));

async function analyze() {
    analyzing.value = true;
    try {
        const res = await postJson(`/visit/${props.stop.id}/analyze`, {
            free_chlorine: num(form.free_chlorine),
            total_chlorine: num(form.total_chlorine),
            ph: num(form.ph),
            alkalinity: num(form.alkalinity),
            calcium_hardness: num(form.calcium_hardness),
            cyanuric_acid: num(form.cyanuric_acid),
            salt: num(form.salt),
            water_temperature: num(form.water_temperature),
        });
        const data = await res.json();
        recommendations.value = Array.isArray(data.recommendations) ? data.recommendations : [];
    } catch {
        recommendations.value = [];
    } finally {
        analyzing.value = false;
    }
}

function applyRec(rec: Recommendation) {
    form.treatments.push({ name: rec.chemical, amount: rec.amount > 0 ? String(rec.amount) : '', unit: rec.unit ?? 'oz' });
}

const urgencyClass = (u: string) =>
    u === 'high'
        ? 'bg-red-500/15 text-red-600 dark:text-red-400'
        : u === 'medium'
          ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
          : 'bg-muted text-muted-foreground';

const complete = () => form.post(`/visit/${props.stop.id}/complete`);
</script>

<template>
    <Head :title="`Visit · ${pool.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-5 p-4">
            <!-- pre-arrival -->
            <div class="rounded-xl border border-border p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-lg font-semibold">{{ pool.name }}</h1>
                        <p class="text-sm text-muted-foreground">{{ pool.customer }} · {{ pool.type.replace('_', ' ') }} · {{ pool.sanitizer }}</p>
                    </div>
                    <span v-if="pool.gate_code" class="rounded-md bg-muted px-2 py-1 font-mono text-sm">Gate {{ pool.gate_code }}</span>
                </div>
                <p v-if="pool.access_notes" class="mt-2 text-sm italic text-muted-foreground">{{ pool.access_notes }}</p>
                <p v-if="last_reading" class="mt-2 text-xs text-muted-foreground">
                    Last ({{ last_reading.on }}): FC {{ last_reading.free_chlorine ?? '—' }} · pH {{ last_reading.ph ?? '—' }} · LSI
                    {{ last_reading.lsi_score ?? '—' }}
                </p>
            </div>

            <!-- tasks: the physical checklist comes first -->
            <section v-if="form.tasks.length" class="rounded-xl border border-border p-4">
                <h2 class="mb-2 font-medium">Checklist</h2>
                <label v-for="(t, i) in form.tasks" :key="i" class="flex items-center gap-2 py-1 text-sm">
                    <input v-model="t.done" type="checkbox" /> <span :class="{ 'text-muted-foreground line-through': t.done }">{{ t.name }}</span>
                </label>
            </section>

            <!-- photos -->
            <section class="rounded-xl border border-border p-4">
                <Label class="text-sm font-medium">Photos</Label>
                <div v-if="visit && visit.photos.length" class="mt-2">
                    <p class="mb-1.5 text-xs text-muted-foreground">Already attached</p>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="(url, i) in visit.photos" :key="i" class="size-20 overflow-hidden rounded-md border border-border">
                            <img :src="url" class="h-full w-full object-cover" alt="Saved visit photo" />
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <p v-if="visit && visit.photos.length" class="mb-1.5 text-xs text-muted-foreground">Add more</p>
                    <MultiImageUpload :model-value="form.photos" @update:model-value="(f) => (form.photos = f)" />
                </div>
                <p v-if="form.errors.photos" class="mt-1 text-xs text-red-600">{{ form.errors.photos }}</p>
            </section>

            <!-- notes -->
            <section class="rounded-xl border border-border p-4">
                <Label for="notes" class="text-sm font-medium">Notes</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                ></textarea>
            </section>

            <!-- chemistry: the water test + dosing is the last thing before completing -->
            <section class="rounded-xl border border-border p-4">
                <h2 class="mb-3 font-medium">Water test</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div v-for="f in chemFields" :key="f.key" class="grid gap-1">
                        <Label :for="f.key" class="text-xs">{{ f.label }}</Label>
                        <Input :id="f.key" v-model="form[f.key]" type="number" step="0.1" inputmode="decimal" class="text-center" />
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 items-end gap-3 sm:grid-cols-4">
                    <div class="grid gap-1">
                        <Label for="water_temperature" class="text-xs">Temp °F</Label>
                        <Input
                            id="water_temperature"
                            v-model="form.water_temperature"
                            type="number"
                            step="0.1"
                            inputmode="decimal"
                            class="text-center"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" :disabled="analyzing" @click="analyze"
                            ><Sparkles class="mr-1 size-4" /> {{ analyzing ? 'Analyzing…' : 'Analyze + dose' }}</Button
                        >
                    </div>
                </div>

                <ul v-if="recommendations.length" class="mt-3 space-y-1.5">
                    <li
                        v-for="(r, i) in recommendations"
                        :key="i"
                        class="flex items-center justify-between gap-2 rounded-md border border-border p-2 text-sm"
                    >
                        <div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="urgencyClass(r.urgency)">{{
                                r.parameter
                            }}</span>
                            <span class="ml-2 text-muted-foreground"
                                >{{ r.chemical }}<template v-if="r.amount > 0"> · {{ r.amount }} {{ r.unit }}</template></span
                            >
                        </div>
                        <Button type="button" size="sm" variant="ghost" @click="applyRec(r)"><Plus class="size-3.5" /></Button>
                    </li>
                </ul>
            </section>

            <!-- treatments: dosing applied from the water test -->
            <section class="rounded-xl border border-border p-4">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-medium">Treatments applied</h2>
                    <Button type="button" size="sm" variant="outline" @click="form.treatments.push({ name: '', amount: '', unit: 'oz' })"
                        ><Plus class="mr-1 size-3.5" /> Add</Button
                    >
                </div>
                <div v-if="form.treatments.length" class="space-y-2">
                    <div v-for="(t, i) in form.treatments" :key="i" class="flex gap-2">
                        <Input v-model="t.name" placeholder="Chemical" class="flex-1" />
                        <Input v-model="t.amount" type="number" step="0.1" placeholder="Qty" class="w-20" />
                        <Input v-model="t.unit" placeholder="oz" class="w-16" />
                        <Button type="button" size="icon" variant="outline" @click="form.treatments.splice(i, 1)"><X class="size-4" /></Button>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">None — add from the dosing suggestions above or manually.</p>
            </section>

            <Button class="h-12 text-base" :disabled="form.processing" @click="complete">
                <FlaskConical class="mr-2 size-5" /> {{ isEditing ? 'Update report' : 'Complete visit' }}
            </Button>
        </div>
    </AppLayout>
</template>
