<script setup lang="ts">
import { postJson } from '@/lib/http';
import { computed, ref } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { LandingService, SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; blurb?: string });
const action = computed(() => props.live.contactAction || `/public/${props.brand.slug}/leads`);
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
const sending = ref(false);
const sent = ref(false);
const error = ref('');

const selected = computed<LandingService | undefined>(() => services.value.find((s) => String(s.id) === form.value.serviceId));
const canSubmit = computed(() => form.value.name.trim() !== '' && form.value.date !== '' && (form.value.email !== '' || form.value.phone !== ''));

const windowLabel = (v: string) => windows.find((w) => w.value === v)?.label ?? v;

async function submit() {
    if (sending.value || !canSubmit.value) return;
    sending.value = true;
    error.value = '';
    const svc = selected.value?.name ?? 'a visit';
    try {
        const res = await postJson(action.value, {
            name: form.value.name,
            email: form.value.email,
            phone: form.value.phone,
            source: 'booking',
            message: `Booking request: ${svc} on ${form.value.date} (${windowLabel(form.value.window)}).${form.value.notes ? ` Notes: ${form.value.notes}` : ''}`,
            details: {
                service_id: selected.value ? String(selected.value.id) : '',
                service_name: selected.value?.name ?? '',
                preferred_date: form.value.date,
                time_window: form.value.window,
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
    <SectionShell id="booking" width="sm" :heading="c.heading || 'Request your first visit'" :subheading="c.blurb || undefined">
        <div
            v-if="sent"
            class="reveal rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-center text-emerald-700 dark:text-emerald-400"
        >
            Thanks! We’ve got your request — we’ll confirm your appointment by phone or email shortly.
        </div>

        <form v-else class="reveal mx-auto max-w-lg space-y-4" @submit.prevent="submit">
            <label v-if="services.length" class="block">
                <span class="mb-1 block text-sm font-medium">Service</span>
                <select v-model="form.serviceId" class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm">
                    <option value="">Not sure yet — recommend one</option>
                    <option v-for="s in services" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                </select>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Preferred date</span>
                    <input
                        v-model="form.date"
                        type="date"
                        :min="minDate"
                        required
                        class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm"
                    />
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Time window</span>
                    <select v-model="form.window" class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm">
                        <option v-for="w in windows" :key="w.value" :value="w.value">{{ w.label }}</option>
                    </select>
                </label>
            </div>

            <input
                v-model="form.name"
                required
                placeholder="Your name"
                class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm"
            />
            <div class="grid gap-4 sm:grid-cols-2">
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
                    class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm"
                />
            </div>
            <textarea
                v-model="form.notes"
                rows="2"
                placeholder="Anything we should know? (gate code, pool size…)"
                class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm"
            ></textarea>

            <p class="text-center text-xs text-muted-foreground">
                Pick a date and we’ll confirm the exact time — we don’t hold the slot until confirmed.
            </p>
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <button
                type="submit"
                :disabled="sending || !canSubmit"
                class="btn-cta w-full rounded-xl bg-primary px-6 py-3 font-bold text-primary-foreground shadow-lg disabled:opacity-60"
            >
                {{ sending ? 'Sending…' : 'Request appointment' }}
            </button>
        </form>
    </SectionShell>
</template>
