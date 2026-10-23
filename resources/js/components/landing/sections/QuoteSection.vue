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

const poolTypes = [
    { value: 'inground', label: 'In-ground' },
    { value: 'above_ground', label: 'Above-ground' },
    { value: 'spa', label: 'Spa / hot tub' },
    { value: 'infinity', label: 'Infinity' },
    { value: 'other', label: 'Other' },
];

const form = ref({ poolType: 'inground', gallons: '', serviceId: '', name: '', email: '', phone: '' });
const { sending, sent, error, send } = useLeadForm(props);

const selected = computed<LandingService | undefined>(() => services.value.find((s) => String(s.id) === form.value.serviceId));

// The estimate is the service's real price — the same one billing charges.
const estimate = computed(() => {
    const s = selected.value;
    return s && s.price > 0 ? s.price : null;
});

const money = (n: number) => `$${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const freqLabel = (f: string) =>
    ({ weekly: '/visit (weekly)', biweekly: '/visit (every 2 wks)', monthly: '/visit (monthly)', one_time: ' one-time' })[f] ?? '';

function submit() {
    const s = selected.value;
    if (form.value.name.trim() === '' || !s) return;
    const est = estimate.value;
    send({
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
    <SectionShell id="quote" :heading="c.heading || 'Get an instant estimate'" :intro="c.blurb || undefined">
        <p v-if="!services.length" class="border-border text-muted-foreground rounded-2xl border p-8 text-lg">
            Pricing is tailored to each pool. Use the contact form and we’ll send you a quote.
        </p>

        <LeadSent v-else-if="sent">
            Thanks! We’ve got your details{{ estimate ? ' and your estimate' : '' }}. We’ll reach out shortly to confirm your exact quote.
        </LeadSent>

        <form v-else class="grid gap-4 @3xl:grid-cols-2" @submit.prevent="submit">
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

            <!-- live estimate -->
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

            <p v-if="error" class="text-base text-red-600 @3xl:col-span-2 dark:text-red-400">{{ error }}</p>
            <div class="@3xl:col-span-2">
                <button type="submit" :disabled="sending || !selected" class="w-full @3xl:w-auto" :class="button">
                    {{ sending ? 'Sending…' : 'Get my quote' }}
                </button>
            </div>
        </form>
    </SectionShell>
</template>
