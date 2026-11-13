<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { agentLink, customerLink, mapsLink, poolLink, reportLink } from '@/lib/links';
import { visitStatusClass } from '@/lib/statusColors';
import { formatDay } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import { MousePointerClick } from 'lucide-vue-next';
import { computed } from 'vue';
import type { RouteCard, Stop, StopReading } from './types';

/**
 * The panel under the Schedule map: the visit picked in the list or on the map.
 * A fixed height, so the map above never changes size as visits are picked.
 */
const props = defineProps<{
    stop: Stop | null;
    /** The card the visit is on: its tech (none on the unassigned bucket). */
    route: RouteCard | null;
}>();

const LEVELS: [Exclude<keyof StopReading, 'tested_on' | 'report_id'>, string][] = [
    ['free_chlorine', 'FC'],
    ['ph', 'pH'],
    ['alkalinity', 'TA'],
    ['cyanuric_acid', 'CYA'],
    ['calcium_hardness', 'CH'],
    ['salt', 'Salt'],
];

// The last water test: its day (a link to that visit's report) and the levels.
const lastTest = computed(() => {
    const r = props.stop?.reading;
    if (!r) return null;
    const levels = LEVELS.filter(([k]) => r[k] !== null)
        .map(([k, label]) => `${label} ${r[k]}`)
        .join(' · ');
    const day = r.tested_on ? formatDay(r.tested_on) : null;
    return { day, levels, report: r.report_id, text: day ? `${day}: ${levels}` : levels };
});
// A tech's name opens their People profile; an admin running their own route has none.
const techLink = computed(() => (props.route?.agent_role === 'agent' && props.route.agent_id !== null ? agentLink(props.route.agent_id) : null));

// The app's record-link look (as on the reports and pools pages).
const LINK = 'text-primary font-medium underline-offset-4 hover:underline';

const directions = computed(() => mapsLink(props.stop?.address ?? null));
const streetView = computed(() => {
    const s = props.stop;
    return s && s.lat !== null && s.lng !== null ? `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${s.lat},${s.lng}` : null;
});
</script>

<template>
    <!-- Wide enough (@xl), the visit sits on the left and the tech / last test on the right. -->
    <div class="@container shrink-0">
        <section class="border-border h-38 overflow-y-auto rounded-xl border p-4 text-sm @xl:h-23" aria-live="polite">
            <div v-if="!stop" class="text-muted-foreground flex h-full items-center justify-center gap-2 text-center">
                <MousePointerClick class="size-4 shrink-0" /> Click a visit to see its details here.
            </div>
            <div v-else class="grid gap-x-6 gap-y-2 @xl:grid-cols-2">
                <!-- The visit: its names open their records, a finished one links its report. -->
                <div class="flex min-w-0 gap-3">
                    <!-- As tall as the three lines beside it (3.25rem of line height). -->
                    <span class="size-13 shrink-0"><EntityAvatar :src="stop.pool_photo" type="pool" :name="stop.pool" size="fill" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <Link v-if="stop.pool_id" :href="poolLink(stop.pool_id)" :class="[LINK, 'truncate']" :title="stop.pool ?? undefined">{{
                                stop.pool
                            }}</Link>
                            <span v-else class="truncate font-medium">{{ stop.pool }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="visitStatusClass(stop.status)">{{
                                stop.status.replace('_', ' ')
                            }}</span>
                            <Link v-if="stop.report_id" :href="reportLink(stop.report_id)" :class="[LINK, 'shrink-0 text-xs']">View report</Link>
                        </div>
                        <p class="text-muted-foreground truncate text-xs" :title="[stop.customer, stop.service].filter(Boolean).join(' · ')">
                            <Link v-if="stop.customer_id" :href="customerLink(stop.customer_id)" :class="LINK">{{ stop.customer }}</Link>
                            <template v-else>{{ stop.customer }}</template>
                            <template v-if="stop.service"> · {{ stop.service }}</template>
                        </p>
                        <p class="flex min-w-0 gap-3 text-xs">
                            <a
                                v-if="directions"
                                :href="directions"
                                target="_blank"
                                rel="noopener"
                                class="truncate hover:underline"
                                :title="stop.address ?? undefined"
                                >{{ stop.address }}</a
                            >
                            <span v-else class="text-muted-foreground">No service address</span>
                            <a v-if="streetView" :href="streetView" target="_blank" rel="noopener" class="text-primary shrink-0 hover:underline"
                                >Street View</a
                            >
                        </p>
                    </div>
                </div>

                <dl class="grid grid-cols-[auto_minmax(0,1fr)] content-start gap-x-4 gap-y-1 text-xs">
                    <dt class="text-muted-foreground">Tech</dt>
                    <dd class="truncate">
                        <Link v-if="techLink" :href="techLink" :class="LINK">{{ route?.agent }}</Link>
                        <template v-else>{{ route?.agent ?? 'Unassigned' }}</template>
                    </dd>
                    <dt class="text-muted-foreground">Last test</dt>
                    <dd class="truncate" :title="lastTest?.text">
                        <template v-if="lastTest">
                            <template v-if="lastTest.day"
                                ><Link :href="reportLink(lastTest.report)" :class="LINK">{{ lastTest.day }}</Link
                                >: </template
                            >{{ lastTest.levels }}
                        </template>
                        <span v-else class="text-muted-foreground">Never tested</span>
                    </dd>
                    <template v-if="stop.skip_reason">
                        <dt class="text-muted-foreground">Skipped</dt>
                        <dd class="truncate" :title="stop.skip_reason">{{ stop.skip_reason }}</dd>
                    </template>
                </dl>
            </div>
        </section>
    </div>
</template>
