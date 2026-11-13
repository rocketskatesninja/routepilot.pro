<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { customerLink, mapsLink, poolLink, reportLink } from '@/lib/links';
import { visitStatusClass } from '@/lib/statusColors';
import { formatDay } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import { MousePointerClick } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Stop, StopReading } from './types';

/**
 * The panel under the Schedule map: the visit picked in the list or on the map.
 * A fixed height, so the map above never changes size as visits are picked.
 */
const props = defineProps<{
    stop: Stop | null;
    /** The tech on the visit's route (null: the unassigned bucket). */
    tech: string | null;
}>();

const LEVELS: [Exclude<keyof StopReading, 'tested_on'>, string][] = [
    ['free_chlorine', 'FC'],
    ['ph', 'pH'],
    ['alkalinity', 'TA'],
    ['cyanuric_acid', 'CYA'],
    ['calcium_hardness', 'CH'],
    ['salt', 'Salt'],
];

const lastTest = computed(() => {
    const r = props.stop?.reading;
    if (!r) return null;
    const levels = LEVELS.filter(([k]) => r[k] !== null)
        .map(([k, label]) => `${label} ${r[k]}`)
        .join(' · ');
    return r.tested_on ? `${formatDay(r.tested_on)}: ${levels}` : levels;
});

// The app's record-link look (as on the reports and pools pages).
const LINK = 'text-primary font-medium underline-offset-4 hover:underline';

const directions = computed(() => mapsLink(props.stop?.address ?? null));
const streetView = computed(() => {
    const s = props.stop;
    return s && s.lat !== null && s.lng !== null ? `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${s.lat},${s.lng}` : null;
});
</script>

<template>
    <section class="border-border h-40 shrink-0 overflow-y-auto rounded-xl border p-4 text-sm" aria-live="polite">
        <div v-if="!stop" class="text-muted-foreground flex h-full items-center justify-center gap-2 text-center">
            <MousePointerClick class="size-4 shrink-0" /> Click a visit to see its details here.
        </div>
        <template v-else>
            <div class="flex items-center gap-3">
                <EntityAvatar :src="stop.pool_photo" type="pool" :name="stop.pool" />
                <div class="min-w-0 flex-1">
                    <!-- The names open their records; a finished visit links its report. -->
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
                </div>
            </div>

            <dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-xs">
                <dt class="text-muted-foreground">Tech</dt>
                <dd>{{ tech ?? 'Unassigned' }}</dd>
                <dt class="text-muted-foreground">Address</dt>
                <dd class="flex min-w-0 gap-3">
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
                </dd>
                <dt class="text-muted-foreground">Last test</dt>
                <dd>
                    <template v-if="lastTest">{{ lastTest }}</template>
                    <span v-else class="text-muted-foreground">Never tested</span>
                </dd>
                <template v-if="stop.skip_reason">
                    <dt class="text-muted-foreground">Skipped</dt>
                    <dd>{{ stop.skip_reason }}</dd>
                </template>
            </dl>
        </template>
    </section>
</template>
