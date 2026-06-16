<script setup lang="ts">
import { postJson } from '@/lib/http';
import { computed, ref } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { LandingService, SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; blurb?: string });
const action = computed(() => props.live.contactAction || `/public/${props.brand.slug}/leads`);
const services = computed<LandingService[]>(() => props.live.services ?? []);

const poolTypes = [
    { value: 'inground', label: 'In-ground' },
    { value: 'above_ground', label: 'Above-ground' },
    { value: 'spa', label: 'Spa / hot tub' },
    { value: 'infinity', label: 'Infinity' },
    { value: 'other', label: 'Other' },
];

const form = ref({ poolType: 'inground', gallons: '', serviceId: '', name: '', email: '', phone: '' });
const sending = ref(false);
const sent = ref(false);
const error = ref('');

const selected = computed<LandingService | undefined>(() => services.value.find((s) => String(s.id) === form.value.serviceId));

// Bigger pools take more chemicals + time, so scale the base price by volume.
const sizeFactor = computed(() => {
    const g = Number(form.value.gallons);
    if (!g) return 1;
    if (g < 15000) return 1;
    if (g <= 30000) return 1.25;
    return 1.5;
});

const estimate = computed(() => {
    const s = selected.value;
    if (!s || s.price <= 0) return null;
    return Math.round(s.price * sizeFactor.value * 100) / 100;
});

const money = (n: number) => `$${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const freqLabel = (f: string) =>
    ({ weekly: '/visit (weekly)', biweekly: '/visit (every 2 wks)', monthly: '/month', one_time: ' one-time', seasonal: ' seasonal' })[f] ?? '';

async function submit() {
    if (sending.value || form.value.name.trim() === '' || !selected.value) return;
    sending.value = true;
    error.value = '';
    const s = selected.value;
    const est = estimate.value;
    try {
        const res = await postJson(action.value, {
            name: form.value.name,
            email: form.value.email,
            phone: form.value.phone,
            source: 'quote',
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
        if (!res.ok) throw new Error('failed');
        sent.value = true;
    } catch {
        error.value = 'Something went wrong — please try again, or give us a call.';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <SectionShell id="quote" width="sm" :heading="c.heading || 'Get an instant estimate'" :subheading="c.blurb || undefined">
        <div
            v-if="!services.length"
            class="reveal mx-auto max-w-md rounded-xl border border-input bg-background p-6 text-center text-sm text-muted-foreground"
        >
            Pricing is tailored to each pool — use the contact form below and we’ll send a quote.
        </div>

        <div
            v-else-if="sent"
            class="reveal rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-center text-emerald-700 dark:text-emerald-400"
        >
            Thanks! We’ve got your details{{ estimate ? ' and your estimate' : '' }} — we’ll reach out shortly to confirm your exact quote.
        </div>

        <form v-else class="reveal mx-auto max-w-lg space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Pool type</span>
                    <select v-model="form.poolType" class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm">
                        <option v-for="t in poolTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Approx. size (gallons)</span>
                    <input
                        v-model="form.gallons"
                        type="number"
                        inputmode="numeric"
                        placeholder="e.g. 20000"
                        class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm"
                    />
                </label>
            </div>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Service</span>
                <select v-model="form.serviceId" required class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm">
                    <option value="" disabled>Choose a service…</option>
                    <option v-for="s in services" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                </select>
            </label>

            <!-- live estimate -->
            <div v-if="selected" class="rounded-xl border border-primary/30 bg-primary/5 p-4 text-center">
                <template v-if="estimate">
                    <p class="text-sm text-muted-foreground">Estimated price</p>
                    <p class="text-3xl font-extrabold text-primary">
                        {{ money(estimate) }}<span class="text-base font-medium text-muted-foreground">{{ freqLabel(selected.frequency) }}</span>
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Ballpark only — we’ll confirm after a quick look at your pool.</p>
                </template>
                <p v-else class="text-sm text-muted-foreground">We’ll prepare a custom quote for this service.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <input
                    v-model="form.name"
                    required
                    placeholder="Your name"
                    class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm sm:col-span-3"
                />
                <input
                    v-model="form.email"
                    type="email"
                    placeholder="Email"
                    class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm"
                />
                <input
                    v-model="form.phone"
                    type="tel"
                    placeholder="Phone"
                    class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm sm:col-span-2"
                />
            </div>

            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <button
                type="submit"
                :disabled="sending || !selected"
                class="btn-cta w-full rounded-xl bg-primary px-6 py-3 font-bold text-primary-foreground shadow-lg disabled:opacity-60"
            >
                {{ sending ? 'Sending…' : 'Get my quote' }}
            </button>
        </form>
    </SectionShell>
</template>
