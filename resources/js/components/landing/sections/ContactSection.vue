<script setup lang="ts">
import { postJson } from '@/lib/http';
import { computed, ref } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; blurb?: string });
const action = computed(() => props.live.contactAction || `/public/${props.brand.slug}/leads`);

const form = ref({ name: '', email: '', phone: '', message: '' });
const sending = ref(false);
const sent = ref(false);
const error = ref('');

async function submit() {
    if (sending.value || form.value.name.trim() === '') {
        return;
    }
    sending.value = true;
    error.value = '';
    try {
        const res = await postJson(action.value, { ...form.value, source: 'contact' });
        if (!res.ok) {
            throw new Error('failed');
        }
        sent.value = true;
    } catch {
        error.value = 'Something went wrong — please try again, or give us a call.';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <SectionShell
        id="contact"
        width="xs"
        :heading="c.heading || 'Get in touch'"
        :subheading="c.blurb || 'Tell us about your pool and we’ll get right back to you.'"
    >
        <div
            v-if="sent"
            class="reveal rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-center text-emerald-700 dark:text-emerald-400"
        >
            Thanks! We’ve received your message and will be in touch shortly.
        </div>
        <form v-else class="reveal mx-auto max-w-md space-y-4" @submit.prevent="submit">
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
                v-model="form.message"
                rows="4"
                placeholder="Tell us about your pool and what you need…"
                class="w-full rounded-lg border border-input bg-background px-4 py-3 text-sm"
            ></textarea>
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <button
                type="submit"
                :disabled="sending"
                class="btn-cta w-full rounded-xl bg-primary px-6 py-3 font-bold text-primary-foreground shadow-lg disabled:opacity-60"
            >
                {{ sending ? 'Sending…' : 'Send message' }}
            </button>
        </form>
    </SectionShell>
</template>
