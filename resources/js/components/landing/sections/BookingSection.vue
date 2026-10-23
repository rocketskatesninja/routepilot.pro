<script setup lang="ts">
import { computed, ref } from 'vue';
import { button, field } from '../layout';
import LeadSent from '../primitives/LeadSent.vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { LandingService, SectionProps } from '../types';
import { useLeadForm } from '../useLeadForm';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; blurb?: string });
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

const form = ref({ serviceId: '', date: '', window: 'anytime', name: '', email: '', phone: '', notes: '' });
const { sending, sent, error, send } = useLeadForm(props);

const selected = computed<LandingService | undefined>(() => services.value.find((s) => String(s.id) === form.value.serviceId));
const canSubmit = computed(() => form.value.name.trim() !== '' && form.value.date !== '' && (form.value.email !== '' || form.value.phone !== ''));

const windowLabel = (v: string) => windows.find((w) => w.value === v)?.label ?? v;

function submit() {
    if (!canSubmit.value) return;
    const svc = selected.value?.name ?? 'a visit';
    send({
        name: form.value.name,
        email: form.value.email,
        phone: form.value.phone,
        source: 'booking',
        service_type_id: selected.value?.id ?? null,
        message: `Booking request: ${svc} on ${form.value.date} (${windowLabel(form.value.window)}).${form.value.notes ? ` Notes: ${form.value.notes}` : ''}`,
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
    <SectionShell id="booking" :heading="c.heading || 'Request your first visit'" :intro="c.blurb || undefined">
        <template #aside>
            <p class="text-muted-foreground mt-6 text-base">Pick a date and we’ll confirm the exact time. The slot isn’t held until we confirm it.</p>
        </template>

        <LeadSent v-if="sent">Thanks! We’ve got your request. We’ll confirm your appointment by phone or email shortly.</LeadSent>

        <form v-else class="grid gap-4 @3xl:grid-cols-2" @submit.prevent="submit">
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

            <label class="@3xl:col-span-2">
                <span class="sr-only">Your name</span>
                <input v-model="form.name" required autocomplete="name" placeholder="Your name" :class="field" />
            </label>
            <label>
                <span class="sr-only">Email</span>
                <input v-model="form.email" type="email" autocomplete="email" placeholder="Email" :class="field" />
            </label>
            <label>
                <span class="sr-only">Phone</span>
                <input v-model="form.phone" type="tel" autocomplete="tel" placeholder="Phone" :class="field" />
            </label>
            <label class="@3xl:col-span-2">
                <span class="sr-only">Notes</span>
                <textarea v-model="form.notes" rows="3" placeholder="Anything we should know? (gate code, pool size…)" :class="field"></textarea>
            </label>

            <p v-if="error" class="text-base text-red-600 @3xl:col-span-2 dark:text-red-400">{{ error }}</p>
            <div class="@3xl:col-span-2">
                <button type="submit" :disabled="sending || !canSubmit" class="w-full @3xl:w-auto" :class="button">
                    {{ sending ? 'Sending…' : 'Request appointment' }}
                </button>
            </div>
        </form>
    </SectionShell>
</template>
