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

const poolTypes = [
    { value: 'inground', label: 'In-ground' },
    { value: 'above_ground', label: 'Above-ground' },
    { value: 'spa', label: 'Spa / hot tub' },
    { value: 'infinity', label: 'Infinity' },
    { value: 'other', label: 'Other' },
];

const form = ref({ poolType: 'inground', gallons: '', serviceId: '', name: '', email: '', phone: '' });
const selected = computed<LandingService | undefined>(() => services.value.find((s) => String(s.id) === form.value.serviceId));
// The estimate is the service's real price — the same one billing charges.
const estimate = computed(() => (selected.value && selected.value.price > 0 ? selected.value.price : null));

const money = (n: number) => `$${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const freqLabel = (f: string) =>
    ({ weekly: '/visit (weekly)', biweekly: '/visit (every 2 wks)', monthly: '/visit (monthly)', one_time: ' one-time' })[f] ?? '';

function submit() {
    const s = selected.value;
    if (form.value.name.trim() === '' || !s) return;
    const est = estimate.value;
    lead.send({
        name: form.value.name,
        email: form.value.email,
        phone: form.value.phone,
        source: 'quote',
        service_type_id: s.id,
        message: `Instant-quote request: ${s.name} for a ${form.value.poolType.replace('_', ' ')} pool${form.value.gallons ? ` (~${form.value.gallons} gal)` : ''}${est ? ` — estimated ${money(est)}${freqLabel(s.frequency)}` : ''}.`,
        details: {
            pool_type: form.value.poolType,
            volume_gallons: form.value.gallons,
            service_id: String(s.id),
            service_name: s.name,
            frequency: s.frequency,
            estimate: est ? money(est) : 'custom',
        },
    });
}
</script>

<template>
    <SectionShell :section="content">
        <p v-if="!services.length" class="border-border text-muted-foreground rounded-2xl border p-8 text-lg">
            Pricing is tailored to each pool. Use the contact form and we’ll send you a quote.
        </p>

        <LeadForm v-else :lead="lead" label="Get my quote" :disabled="!selected" @submit="submit">
            <template #sent>
                Thanks! We’ve got your details{{ estimate ? ' and your estimate' : '' }}. We’ll reach out shortly to confirm your exact quote.
            </template>
            <label>
                <span class="text-foreground mb-1.5 block text-base font-medium">Pool type</span>
                <select v-model="form.poolType" :class="field">
                    <option v-for="t in poolTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </label>
            <label>
                <span class="text-foreground mb-1.5 block text-base font-medium">Approx. size (gallons)</span>
                <input v-model="form.gallons" type="number" inputmode="numeric" placeholder="e.g. 20000" :class="field" />
            </label>
            <label class="@3xl:col-span-2">
                <span class="text-foreground mb-1.5 block text-base font-medium">Service</span>
                <select v-model="form.serviceId" required :class="field">
                    <option value="" disabled>Choose a service…</option>
                    <option v-for="s in services" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                </select>
            </label>

            <div v-if="selected" class="site-tint rounded-2xl p-6 @3xl:col-span-2" aria-live="polite">
                <template v-if="estimate">
                    <p class="text-muted-foreground text-base">Estimated price</p>
                    <p class="site-font text-primary mt-1 text-4xl font-bold tracking-tight">
                        {{ money(estimate)
                        }}<span class="text-muted-foreground font-sans text-base font-medium">{{ freqLabel(selected.frequency) }}</span>
                    </p>
                    <p class="text-muted-foreground mt-2 text-sm">Ballpark only. We’ll confirm after a quick look at your pool.</p>
                </template>
                <p v-else class="text-muted-foreground text-base">We’ll prepare a custom quote for this service.</p>
            </div>

            <ContactFields v-model:name="form.name" v-model:email="form.email" v-model:phone="form.phone" />
        </LeadForm>
    </SectionShell>
</template>
