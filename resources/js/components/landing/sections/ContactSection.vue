<script setup lang="ts">
import { MapPin } from 'lucide-vue-next';
import { ref } from 'vue';
import { field } from '../layout';
import ContactFields from '../primitives/ContactFields.vue';
import LeadForm from '../primitives/LeadForm.vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';
import { useLeadForm } from '../useLeadForm';

const props = defineProps<SectionProps>();
const lead = useLeadForm(props);
const form = ref({ name: '', email: '', phone: '', message: '' });
const submit = () => form.value.name.trim() !== '' && lead.send({ ...form.value, source: 'contact' });
</script>

<template>
    <SectionShell :section="content">
        <template #aside>
            <div class="text-muted-foreground mt-8 space-y-3 text-base">
                <p v-if="live.serviceArea?.formattedAddress" class="flex items-start gap-2">
                    <MapPin class="text-primary mt-0.5 size-5 shrink-0" /> {{ live.serviceArea.formattedAddress }}
                </p>
                <p>
                    Already a customer?
                    <a href="/login" class="text-primary font-medium underline-offset-4 hover:underline">Log in to your account.</a>
                </p>
            </div>
        </template>

        <LeadForm :lead="lead" label="Send message" @submit="submit">
            <template #sent>Thanks! We’ve received your message and will be in touch shortly.</template>
            <ContactFields v-model:name="form.name" v-model:email="form.email" v-model:phone="form.phone" />
            <label class="@3xl:col-span-2">
                <span class="sr-only">Message</span>
                <textarea v-model="form.message" rows="5" placeholder="Tell us about your pool and what you need…" :class="field"></textarea>
            </label>
        </LeadForm>
    </SectionShell>
</template>
