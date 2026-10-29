<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarX,
    CheckCircle2,
    CloudLightning,
    CloudRain,
    CloudSnow,
    DollarSign,
    FlaskConical,
    Image,
    Inbox,
    Info,
    Lightbulb,
    MailWarning,
    MapPinOff,
    Reply,
    Snowflake,
    TestTube,
    ThermometerSun,
    UserPlus,
    Users,
    UserX,
    Wrench,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Advisory {
    sev: 'red' | 'amber' | 'info';
    icon: string;
    msg: string;
    href: string | null;
}

// Worst first, already sorted (App\Dashboard\Advisories); `tech` picks the tips.
const props = defineProps<{ data: { items: Advisory[]; tech: boolean } }>();

const ICONS: Record<string, LucideIcon> = {
    CalendarClock,
    CalendarX,
    CloudLightning,
    CloudRain,
    CloudSnow,
    DollarSign,
    FlaskConical,
    Image,
    Inbox,
    MailWarning,
    MapPinOff,
    Reply,
    Snowflake,
    TestTube,
    ThermometerSun,
    UserPlus,
    Users,
    UserX,
    Wrench,
};
const tone = (sev: Advisory['sev']): string =>
    sev === 'red' ? 'text-red-600 dark:text-red-400' : sev === 'amber' ? 'text-amber-600 dark:text-amber-400' : 'text-sky-600 dark:text-sky-400';

const ADMIN_TIPS = [
    'Customize the dashboard to add, remove and resize widgets; drag one by its grip to move it.',
    'Your phone and desktop dashboards are arranged separately.',
    'Tick several leads or requests to delete them together.',
    'The office can fill in a report for any tech: open the field app, then pick the tech and the day.',
    'Let customers message you from their visit reports, in Company settings. Their messages land in Requests.',
    'Mark a work order done and bill it in one go: the charge comes up already filled in.',
    'Each kind of notification has its own switch, on the Notifications page under Settings.',
    'Tick notifications to mark them read or dismiss them all at once.',
];
const TECH_TIPS = [
    'The field app works offline: finished visits wait on your phone and send when you’re back in signal.',
    'A visit you finish offline keeps its own day, even if it only sends the next morning.',
    'Add a CC email at the top of a visit and it’s remembered for that pool.',
    'Something needs fixing later? Raise a work order from the visit.',
    'Photos are shrunk on your phone before they upload, so take as many as you need.',
    'Your phone and desktop dashboards are arranged separately; drag a widget by its grip to move it.',
];
const tips = computed(() => (props.data.tech ? TECH_TIPS : ADMIN_TIPS));

// A new tip every 9 seconds; with reduced motion, one tip that stays put.
const tipIdx = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    tipIdx.value = Math.floor(Math.random() * tips.value.length);
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
    timer = setInterval(() => (tipIdx.value = (tipIdx.value + 1) % tips.value.length), 9000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div class="flex h-full flex-col gap-3">
        <ul v-if="data.items.length" class="min-h-0 flex-1 space-y-0.5 overflow-y-auto text-sm">
            <li v-for="(a, i) in data.items" :key="i">
                <component
                    :is="a.href ? Link : 'div'"
                    v-bind="a.href ? { href: a.href } : {}"
                    class="flex items-start gap-2.5 rounded-md px-1 py-1.5"
                    :class="a.href ? 'hover:bg-muted/50' : ''"
                >
                    <component :is="ICONS[a.icon] ?? Info" class="mt-0.5 size-4 shrink-0" :class="tone(a.sev)" />
                    <span class="min-w-0 flex-1">{{ a.msg }}</span>
                </component>
            </li>
        </ul>
        <p v-else class="flex flex-1 items-center gap-2 px-1 text-sm text-emerald-700 dark:text-emerald-400">
            <CheckCircle2 class="size-4 shrink-0" /> All clear — nothing needs attention.
        </p>

        <div class="border-border text-muted-foreground flex items-start gap-2 border-t px-1 pt-2 text-xs">
            <Lightbulb class="mt-px size-3.5 shrink-0 text-amber-500" />
            <Transition
                mode="out-in"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
                enter-active-class="transition-opacity duration-300"
                leave-active-class="transition-opacity duration-300"
            >
                <span :key="tipIdx">{{ tips[tipIdx] }}</span>
            </Transition>
        </div>
    </div>
</template>
