<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { FlaskConical, Plus, Sparkles, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Recommendation {
    parameter: string;
    chemical: string;
    amount: number;
    unit?: string;
    urgency: string;
}

const props = defineProps<{
    stop: { id: number; status: string };
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
}>({
    free_chlorine: '',
    total_chlorine: '',
    ph: '',
    alkalinity: '',
    calcium_hardness: '',
    cyanuric_acid: '',
    salt: '',
    water_temperature: '',
    tasks: props.service.tasks.map((name) => ({ name, done: false })),
    treatments: [],
    notes: '',
});

const recommendations = ref<Recommendation[]>([]);
const analyzing = ref(false);

function cookie(name: string): string {
    const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : '';
}

const num = (v: string) => (v === '' ? null : Number(v));

async function analyze() {
    analyzing.value = true;
    try {
        const res = await fetch(`/visit/${props.stop.id}/analyze`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                free_chlorine: num(form.free_chlorine),
                total_chlorine: num(form.total_chlorine),
                ph: num(form.ph),
                alkalinity: num(form.alkalinity),
                calcium_hardness: num(form.calcium_hardness),
                cyanuric_acid: num(form.cyanuric_acid),
                salt: num(form.salt),
                water_temperature: num(form.water_temperature),
            }),
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

            <!-- chemistry -->
            <section class="rounded-xl border border-border p-4">
                <h2 class="mb-3 font-medium">Water test</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div v-for="f in readingFields" :key="f.key" class="grid gap-1">
                        <Label :for="f.key" class="text-xs">{{ f.label }}</Label>
                        <Input :id="f.key" v-model="form[f.key]" type="number" step="0.1" inputmode="decimal" class="text-center" />
                    </div>
                </div>
                <Button type="button" variant="outline" size="sm" class="mt-3" :disabled="analyzing" @click="analyze"
                    ><Sparkles class="mr-1 size-4" /> {{ analyzing ? 'Analyzing…' : 'Analyze + dose' }}</Button
                >

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

            <!-- treatments -->
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

            <!-- tasks -->
            <section v-if="form.tasks.length" class="rounded-xl border border-border p-4">
                <h2 class="mb-2 font-medium">Checklist</h2>
                <label v-for="(t, i) in form.tasks" :key="i" class="flex items-center gap-2 py-1 text-sm">
                    <input v-model="t.done" type="checkbox" /> <span :class="{ 'text-muted-foreground line-through': t.done }">{{ t.name }}</span>
                </label>
            </section>

            <!-- notes + complete -->
            <section class="rounded-xl border border-border p-4">
                <Label for="notes" class="text-sm font-medium">Notes</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                ></textarea>
            </section>

            <Button class="h-12 text-base" :disabled="form.processing || stop.status === 'completed'" @click="complete">
                <FlaskConical class="mr-2 size-5" /> {{ stop.status === 'completed' ? 'Already completed' : 'Complete visit' }}
            </Button>
        </div>
    </AppLayout>
</template>
