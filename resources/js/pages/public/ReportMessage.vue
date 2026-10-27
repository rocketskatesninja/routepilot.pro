<script setup lang="ts">
import CompanyMark from '@/components/portal/CompanyMark.vue';
import PortalHero from '@/components/portal/PortalHero.vue';
import { Button } from '@/components/ui/button';
import { hexToHslChannels } from '@/composables/useBrand';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * "Message us" from a visit report email — no login. The message lands in the
 * company's Requests inbox with the visit attached; the answer comes by email.
 */
const props = defineProps<{
    company: { name: string; color: string; logo: string | null };
    firstName: string;
    visit: { date: string | null; pool: string | null };
    sendUrl: string;
}>();

// The company's colour, set on the page itself so it's right from the first paint.
const brandVars = computed(() => {
    const c = hexToHslChannels(props.company.color);
    return c ? { '--brand': c, '--primary': c, '--ring': c } : {};
});

const about = computed(() => [props.visit.date ? `your visit on ${props.visit.date}` : 'your visit', props.visit.pool].filter(Boolean).join(' · '));

const form = useForm({ message: '' });
const sent = ref(false);
const send = () =>
    form.post(props.sendUrl, {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            form.reset();
        },
    });
</script>

<template>
    <Head :title="`Message ${props.company.name}`" />

    <div class="bg-background text-foreground min-h-svh" :style="brandVars">
        <main class="mx-auto flex max-w-xl flex-col gap-8 px-4 py-10">
            <CompanyMark :company="props.company" />

            <PortalHero :greeting="`Hi ${props.firstName},`">
                <template v-if="sent">Thanks. {{ props.company.name }} got your message.</template>
                <template v-else>Message {{ props.company.name }}</template>

                <template #sub>
                    <p v-if="sent">They’ll reply by email.</p>
                    <p v-else>About {{ about }}.</p>
                </template>
                <template v-if="sent" #actions>
                    <Button variant="outline" @click="sent = false">Send another</Button>
                </template>
            </PortalHero>

            <form v-if="!sent" class="flex flex-col gap-3" @submit.prevent="send">
                <label for="message" class="font-medium">Your message</label>
                <textarea
                    id="message"
                    v-model="form.message"
                    rows="6"
                    maxlength="2000"
                    required
                    placeholder="A question, something we missed, or a thank-you"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary w-full rounded-xl border px-3 py-2.5 text-base focus:ring-1 focus:outline-none"
                ></textarea>
                <p v-if="form.errors.message" role="alert" class="text-sm text-red-600">{{ form.errors.message }}</p>
                <Button type="submit" class="self-start" :disabled="form.processing || !form.message.trim()">Send</Button>
            </form>
        </main>
    </div>
</template>
