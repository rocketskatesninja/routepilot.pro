<script setup lang="ts">
/**
 * The notifications page's Settings tab: this device (pop-ups on a computer,
 * real notifications on a phone), then one switch per type the user gets.
 */
import EmailChoices from '@/components/EmailChoices.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { isDesktop, popupsOn, pushState, setPopups, turnOffPush, turnOnPush, type PushState } from '@/lib/push';
import { Link, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    types: Record<string, [string, string]>;
    preferences: Record<string, boolean>;
    /** The server's web-push key; null when phone notifications aren't set up. */
    pushKey: string | null;
}>();

const auth = usePage().props.auth;

const busy = ref(false);
const setType = (type: string, on: boolean) =>
    router.patch(
        '/notifications/types',
        { [type]: on },
        { preserveScroll: true, preserveState: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );

// Which device this is is known only in the browser, so it's settled after mount (SSR renders neither).
const device = ref<'computer' | 'phone' | null>(null);
const popups = ref(true);
const phone = ref<PushState>('off');
const phoneBusy = ref(false);
onMounted(async () => {
    if (isDesktop()) {
        device.value = 'computer';
        popups.value = popupsOn();
    } else if (props.pushKey && !auth.impersonating) {
        device.value = 'phone';
        phone.value = await pushState();
    }
});

function setComputer(_: string, on: boolean) {
    setPopups(on);
    popups.value = on;
}

async function setPhone(_: string, on: boolean) {
    phoneBusy.value = true;
    try {
        phone.value = on ? await turnOnPush(props.pushKey as string) : await turnOffPush();
    } catch {
        phone.value = await pushState();
    } finally {
        phoneBusy.value = false;
    }
}
</script>

<template>
    <div class="space-y-8">
        <section v-if="device === 'computer'" class="space-y-3">
            <HeadingSmall title="This computer" />
            <EmailChoices
                :types="{ popups: ['Pop-ups on this computer', 'A note in the corner when one arrives while RoutePilot is open.'] }"
                :preferences="{ popups }"
                @change="setComputer"
            >
                <template #note>
                    Only affects this computer. To get notifications on your phone, open this Settings tab on your phone and turn them on there.
                </template>
            </EmailChoices>
        </section>

        <section v-else-if="device === 'phone'" class="space-y-3">
            <HeadingSmall title="This phone" />
            <EmailChoices
                :types="{ phone: ['Notifications on this phone', 'On your lock screen, even when RoutePilot is closed.'] }"
                :preferences="{ phone: phone === 'on' }"
                :busy="phoneBusy || phone === 'blocked' || phone === 'unsupported'"
                @change="setPhone"
            >
                <template #note>
                    <template v-if="phone === 'blocked'">
                        Notifications are blocked for this site. Allow them in your browser’s site settings, then come back.
                    </template>
                    <template v-else-if="phone === 'unsupported'">
                        This browser can’t show notifications. On an iPhone, add RoutePilot to your Home Screen and open it from there.
                    </template>
                    <template v-else>Only affects this phone. It gets the same types you choose below.</template>
                </template>
            </EmailChoices>
        </section>

        <section class="space-y-3">
            <HeadingSmall title="What to tell me about" description="Each switch saves right away." />
            <EmailChoices :types="props.types" :preferences="props.preferences" :busy="busy" @change="setType">
                <template #note>
                    Types you turn off won’t show up here, pop up or reach your phone.
                    <template v-if="auth.role === 'customer'">
                        Emails are set separately in
                        <Link href="/settings/emails" class="text-foreground underline underline-offset-4">Email settings</Link>.
                    </template>
                </template>
            </EmailChoices>
        </section>
    </div>
</template>
