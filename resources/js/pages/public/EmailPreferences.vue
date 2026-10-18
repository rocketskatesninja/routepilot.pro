<script setup lang="ts">
import EmailChoices from '@/components/EmailChoices.vue';
import PortalHero from '@/components/portal/PortalHero.vue';
import { Button } from '@/components/ui/button';
import { hexToHslChannels } from '@/composables/useBrand';
import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Where "Stop visit reports" (and the like) in an email lands — no login. It
 * asks before changing anything, so link-checking scanners can't unsubscribe
 * anyone, then shows every optional email with a switch.
 */
const props = defineProps<{
    company: { name: string; color: string; logo: string | null };
    firstName: string;
    preferences: Record<string, boolean>;
    types: Record<string, [string, string]>;
    stop: string | null;
    updateUrl: string;
}>();

const page = usePage<SharedData>();
// The company's colour, set on the page itself so it's right from the first paint.
const brandVars = computed(() => {
    const c = hexToHslChannels(props.company.color);
    return c ? { '--brand': c, '--primary': c, '--ring': c } : {};
});

const busy = ref(false);
const save = (changes: Record<string, boolean>) =>
    router.post(
        props.updateUrl,
        { ...changes, stop: props.stop },
        { preserveScroll: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );

const stopName = computed(() => (props.stop ? props.types[props.stop][0].toLowerCase() : ''));
const stopOn = computed(() => (props.stop ? props.preferences[props.stop] : false));
</script>

<template>
    <Head :title="`Emails from ${props.company.name}`" />

    <div class="bg-background text-foreground min-h-svh" :style="brandVars">
        <main class="mx-auto flex max-w-xl flex-col gap-8 px-4 py-10">
            <img v-if="props.company.logo" :src="props.company.logo" :alt="props.company.name" class="h-10 w-auto self-start" />
            <p v-else class="font-display text-lg font-semibold">{{ props.company.name }}</p>

            <PortalHero :greeting="`Hi ${props.firstName},`">
                <template v-if="props.stop && stopOn">Stop getting {{ stopName }}?</template>
                <template v-else-if="props.stop">Done. You won’t get {{ stopName }} anymore.</template>
                <template v-else>Choose your emails from {{ props.company.name }}.</template>

                <template #sub>
                    <p v-if="props.stop && stopOn">{{ props.types[props.stop][1] }}</p>
                    <p v-else-if="props.stop">Changed your mind? You can turn them back on below, anytime.</p>
                    <p v-else>Turn any of these on or off. It takes effect right away.</p>
                </template>
                <template v-if="props.stop" #actions>
                    <Button v-if="stopOn" :disabled="busy" @click="save({ [props.stop]: false })">Stop {{ stopName }}</Button>
                    <Button v-else variant="outline" :disabled="busy" @click="save({ [props.stop]: true })">Turn them back on</Button>
                </template>
            </PortalHero>

            <section aria-labelledby="all">
                <div class="mb-3 flex items-baseline justify-between gap-3">
                    <h2 id="all" class="font-display text-xl font-semibold tracking-tight">All your emails</h2>
                    <p v-if="page.props.flash?.success" class="text-sm text-emerald-700 dark:text-emerald-400" role="status">Saved.</p>
                </div>
                <EmailChoices :types="props.types" :preferences="props.preferences" :busy="busy" @change="(type, on) => save({ [type]: on })" />
            </section>
        </main>
    </div>
</template>
