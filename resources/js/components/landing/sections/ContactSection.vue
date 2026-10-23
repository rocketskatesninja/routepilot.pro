<script setup lang="ts">
import { MapPin } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { button, field } from '../layout';
import LeadSent from '../primitives/LeadSent.vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';
import { useLeadForm } from '../useLeadForm';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; blurb?: string });
const address = computed(() => props.live.serviceArea?.formattedAddress ?? null);

const form = ref({ name: '', email: '', phone: '', message: '' });
const { sending, sent, error, send } = useLeadForm(props);
const submit = () => form.value.name.trim() !== '' && send({ ...form.value, source: 'contact' });
</script>

<template>
    <SectionShell id="contact" :heading="c.heading || 'Get in touch'" :intro="c.blurb || 'Tell us about your pool and we’ll get right back to you.'">
        <template #aside>
            <div class="text-muted-foreground mt-8 space-y-3 text-base">
                <p v-if="address" class="flex items-start gap-2"><MapPin class="text-primary mt-0.5 size-5 shrink-0" /> {{ address }}</p>
                <p>
                    Already a customer?
                    <a href="/login" class="text-primary font-medium underline-offset-4 hover:underline">Log in to your account.</a>
                </p>
            </div>
        </template>

        <LeadSent v-if="sent">Thanks! We’ve received your message and will be in touch shortly.</LeadSent>
        <form v-else class="grid gap-4 @3xl:grid-cols-2" @submit.prevent="submit">
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
                <span class="sr-only">Message</span>
                <textarea v-model="form.message" rows="5" placeholder="Tell us about your pool and what you need…" :class="field"></textarea>
            </label>
            <p v-if="error" class="text-base text-red-600 @3xl:col-span-2 dark:text-red-400">{{ error }}</p>
            <div class="@3xl:col-span-2">
                <button type="submit" :disabled="sending" class="w-full @3xl:w-auto" :class="button">
                    {{ sending ? 'Sending…' : 'Send message' }}
                </button>
            </div>
        </form>
    </SectionShell>
</template>
