<script setup lang="ts">
import PortalHero from '@/components/portal/PortalHero.vue';
import { Button } from '@/components/ui/button';
import { hexToHslChannels } from '@/composables/useBrand';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Where "Stop these copies" in a copy of someone's visit report lands — no
 * login. It asks before stopping, so link-checking scanners can't.
 */
const props = defineProps<{
    company: { name: string; color: string; logo: string | null };
    customer: string;
    email: string;
    stopped: boolean;
    stopUrl: string;
}>();

// The company's colour, set on the page itself so it's right from the first paint.
const brandVars = computed(() => {
    const c = hexToHslChannels(props.company.color);
    return c ? { '--brand': c, '--primary': c, '--ring': c } : {};
});

const busy = ref(false);
const stop = () => router.post(props.stopUrl, {}, { onStart: () => (busy.value = true), onFinish: () => (busy.value = false) });
</script>

<template>
    <Head :title="`Report copies from ${props.company.name}`" />

    <div class="bg-background text-foreground min-h-svh" :style="brandVars">
        <main class="mx-auto flex max-w-xl flex-col gap-8 px-4 py-10">
            <img v-if="props.company.logo" :src="props.company.logo" :alt="props.company.name" class="h-10 w-auto self-start" />
            <p v-else class="font-display text-lg font-semibold">{{ props.company.name }}</p>

            <PortalHero :greeting="props.email">
                <template v-if="props.stopped">Done. You won’t get copies of {{ props.customer }}’s pool reports anymore.</template>
                <template v-else>Stop getting copies of {{ props.customer }}’s pool reports?</template>

                <template #sub>
                    <p v-if="props.stopped">If that was a mistake, ask {{ props.customer }} or {{ props.company.name }} to add you again.</p>
                    <p v-else>{{ props.customer }} asked {{ props.company.name }} to send you a copy after each visit.</p>
                </template>
                <template v-if="!props.stopped" #actions>
                    <Button :disabled="busy" @click="stop">Stop the copies</Button>
                </template>
            </PortalHero>
        </main>
    </div>
</template>
