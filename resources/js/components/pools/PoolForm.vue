<script setup lang="ts">
import Combobox from '@/components/Combobox.vue';
import FormField from '@/components/FormField.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PlanFields, { type PlanForm } from '@/components/PlanFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { saveRecord } from '@/composables/useCrudForm';
import { FILTERS, POOL_TYPES, SANITIZERS, startFromService } from '@/lib/labels';
import { localDay } from '@/lib/utils';
import { type Option, type PoolDetail, type ServiceType } from '@/types/pools';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/** Add a pool (with an optional first service plan), or edit the one given. */
const props = defineProps<{ pool: PoolDetail | null; customers: Option[]; serviceTypes: ServiceType[]; agents: Option[] }>();
const emit = defineEmits<{ close: [] }>();

// The pool as it was when the form opened: picking another row doesn't retarget it.
const editing = props.pool;
const f = editing?.fields;

const equipmentToggles = [
    { key: 'has_heater', label: 'Heater' },
    { key: 'has_automation', label: 'Automation' },
    { key: 'has_pool_cleaner', label: 'Cleaner' },
    { key: 'has_cover', label: 'Cover' },
    { key: 'has_water_feature', label: 'Water feature' },
    { key: 'has_auto_fill', label: 'Auto-fill' },
] as const;

// Ids and numbers are '' until picked or typed.
const form = useForm({
    customer_id: (f?.customer_id ?? '') as number | string,
    name: f?.name ?? '',
    type: f?.type ?? 'inground',
    volume_gallons: (f?.volume_gallons ?? '') as number | string,
    surface_type: f?.surface_type ?? '',
    sanitizer_type: f?.sanitizer_type ?? 'chlorine',
    filter_type: f?.filter_type ?? '',
    pump_type: f?.pump_type ?? '',
    has_heater: f?.has_heater ?? false,
    has_automation: f?.has_automation ?? false,
    has_pool_cleaner: f?.has_pool_cleaner ?? false,
    has_cover: f?.has_cover ?? false,
    has_water_feature: f?.has_water_feature ?? false,
    has_auto_fill: f?.has_auto_fill ?? false,
    notes: f?.notes ?? '',
    address_line1: f?.address_line1 ?? '',
    city: f?.city ?? '',
    state: f?.state ?? '',
    zip: f?.zip ?? '',
    gate_code: f?.gate_code ?? '',
    access_notes: f?.access_notes ?? '',
    report_cc_email: f?.report_cc_email ?? '',
    photo: null as File | null,
    // A new pool's optional first service plan (left out unless a service is picked). It would start today.
    service_type_id: '' as number | string,
    price: '' as number | string,
    assigned_agent_id: '' as number | string,
    frequency: 'weekly',
    preferred_day: '',
    day_of_month: '' as number | string,
    starts_on: localDay(),
    ends_on: '',
});

// The new pool's plan fields, in the shape PlanFields edits (in place, on this same form).
const newPlan = form as unknown as PlanForm;
watch(
    () => form.service_type_id,
    (id) => startFromService(form, props.serviceTypes, id),
);

const submit = () => saveRecord(form, '/pools', editing?.id ?? null, () => emit('close'));
</script>

<template>
    <form class="space-y-4 text-sm" @submit.prevent="submit">
        <PaneHeader :title="editing ? 'Edit pool' : 'New pool'" summary="What it is, its equipment, and where it is." />
        <ImageUpload
            :model-value="form.photo"
            :current="editing?.photo_url ?? null"
            :error="form.errors.photo"
            @update:model-value="(file) => (form.photo = file)"
        />
        <FormField label="Customer" for="customer_id" :error="form.errors.customer_id">
            <Combobox id="customer_id" v-model="form.customer_id" :options="customers" :disabled="!!editing" placeholder="Search customers…" />
        </FormField>
        <FormField label="Pool name" for="name" :error="form.errors.name"><Input id="name" v-model="form.name" /></FormField>
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Type" for="type">
                <Select id="type" v-model="form.type">
                    <option v-for="(label, type) in POOL_TYPES" :key="type" :value="type">{{ label }}</option>
                </Select>
            </FormField>
            <FormField label="Volume (gal)" for="volume"><Input id="volume" v-model="form.volume_gallons" type="number" min="0" /></FormField>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Sanitizer" for="sanitizer">
                <Select id="sanitizer" v-model="form.sanitizer_type">
                    <option v-for="(label, key) in SANITIZERS" :key="key" :value="key">{{ label }}</option>
                </Select>
            </FormField>
            <FormField label="Filter" for="filter">
                <Select id="filter" v-model="form.filter_type">
                    <option value="">—</option>
                    <option v-for="(label, key) in FILTERS" :key="key" :value="key">{{ label }}</option>
                </Select>
            </FormField>
        </div>

        <div>
            <span class="text-muted-foreground text-xs font-medium">Equipment</span>
            <div class="mt-1 grid grid-cols-2 gap-1">
                <label v-for="e in equipmentToggles" :key="e.key" class="flex items-center gap-2"
                    ><input v-model="form[e.key]" type="checkbox" /> {{ e.label }}</label
                >
            </div>
        </div>

        <FormField label="Service address" for="paddr"><Input id="paddr" v-model="form.address_line1" /></FormField>
        <div class="grid grid-cols-3 gap-3">
            <FormField label="City" for="pcity"><Input id="pcity" v-model="form.city" /></FormField>
            <FormField label="State" for="pstate"><Input id="pstate" v-model="form.state" maxlength="2" /></FormField>
            <FormField label="ZIP" for="pzip"><Input id="pzip" v-model="form.zip" /></FormField>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Gate code" for="gate"><Input id="gate" v-model="form.gate_code" /></FormField>
        </div>
        <FormField label="Access notes" for="access"><Textarea id="access" v-model="form.access_notes" rows="2" /></FormField>
        <FormField
            label="Also email reports to"
            for="report_cc"
            :error="form.errors.report_cc_email"
            hint="They get a copy of every visit report for this pool."
        >
            <Input id="report_cc" v-model="form.report_cc_email" type="email" placeholder="Optional, e.g. a landlord" />
        </FormField>

        <!-- a new pool can start with its service plan -->
        <div v-if="!editing" class="border-border space-y-4 rounded-2xl border p-4">
            <div>
                <p class="font-medium">Service plan <span class="text-muted-foreground font-normal">(optional)</span></p>
                <p class="text-muted-foreground text-xs">Pick a service to put this pool on the routes now. You can add more plans later.</p>
            </div>
            <FormField label="Service" for="new_service" :error="form.errors.service_type_id">
                <Select id="new_service" v-model="form.service_type_id" class="w-64 max-w-full">
                    <option value="">None for now</option>
                    <option v-for="t in serviceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </Select>
            </FormField>
            <PlanFields v-if="form.service_type_id" :model-value="newPlan" :agents="agents" id-prefix="new" />
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
            <Button type="submit" :disabled="form.processing">{{ editing ? 'Save' : 'Add pool' }}</Button>
        </div>
    </form>
</template>
