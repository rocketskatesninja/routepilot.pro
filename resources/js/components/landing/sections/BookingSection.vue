<script setup lang="ts">
import { computed, ref } from 'vue';
import { field } from '../layout';
import ContactFields from '../primitives/ContactFields.vue';
import LeadForm from '../primitives/LeadForm.vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { LandingService, SectionProps } from '../types';
import { useLeadForm } from '../useLeadForm';

const props = defineProps<SectionProps>();
const lead = useLeadForm(props);
const services = computed<LandingService[]>(() => props.live.services ?? []);

// Earliest selectable date is tomorrow (no same-day booking).
const minDate = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.toISOString().slice(0, 10);
});

const windows = [
    { value: 'anytime', label: 'Anytime' },
    { value: 'morning', label: 'Morning (8am–12pm)' },
    { value: 'afternoon', label: 'Afternoon (12pm–5pm)' },
];
const windowLabel = (v: string) => windows.find((w) => w.value === v)?.label ?? v;

const form = ref({ serviceId: '', date: '', window: 'anytime', name: '', email: '', phone: '', notes: '' });
const selected = computed<LandingService | undefined>(() => services.value.find((s) => String(s.id) === form.value.serviceId));
const canSubmit = computed(() => form.value.name.trim() !== '' && form.value.date !== '' && (form.value.email !== '' || form.value.phone !== ''));

function submit() {
    if (!canSubmit.value) return;
    lead.send({
        name: form.value.name,
        email: form.value.email,
        phone: form.value.phone,
        source: 'booking',
        service_type_id: selected.value?.id ?? null,
        message: `Booking request: ${selected.value?.name ?? 'a visit'} on ${form.value.date} (${windowLabel(form.value.window)}).${form.value.notes ? ` Notes: ${form.value.notes}` : ''}`,
        details: {
            service_id: selected.value ? String(selected.value.id) : '',
            service_name: selected.value?.name ?? '',
            preferred_date: form.value.date,
            time_window: form.value.window,
        },
    });
}
</script>

<template>
    <SectionShell :section="content">
        <template #aside>
            <p class="text-muted-foreground mt-6 text-base">Pick a date and we’ll confirm the exact time. The slot isn’t held until we confirm it.</p>
        </template>

        <LeadForm :lead="lead" label="Request appointment" :disabled="!canSubmit" @submit="submit">
            <template #sent>Thanks! We’ve got your request. We’ll confirm your appointment by phone or email shortly.</template>
            <label v-if="services.length" class="@3xl:col-span-2">
                <span class="text-foreground mb-1.5 block text-base font-medium">Service</span>
                <select v-model="form.serviceId" :class="field">
                    <option value="">Not sure yet — recommend one</option>
                    <option v-for="s in services" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                </select>
            </label>
            <label>
                <span class="text-foreground mb-1.5 block text-base font-medium">Preferred date</span>
                <input v-model="form.date" type="date" :min="minDate" required :class="field" />
            </label>
            <label>
                <span class="text-foreground mb-1.5 block text-base font-medium">Time window</span>
                <select v-model="form.window" :class="field">
                    <option v-for="w in windows" :key="w.value" :value="w.value">{{ w.label }}</option>
                </select>
            </label>
            <ContactFields v-model:name="form.name" v-model:email="form.email" v-model:phone="form.phone" />
            <label class="@3xl:col-span-2">
                <span class="sr-only">Notes</span>
                <textarea v-model="form.notes" rows="3" placeholder="Anything we should know? (gate code, pool size…)" :class="field"></textarea>
            </label>
        </LeadForm>
    </SectionShell>
</template>
