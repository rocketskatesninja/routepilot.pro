<script setup lang="ts">
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneAction from '@/components/pane/PaneAction.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import WaterChecks from '@/components/portal/WaterChecks.vue';
import { Button } from '@/components/ui/button';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { equipmentLabel, PLAN_STATUS, planTone, priceUnit } from '@/lib/labels';
import { agentLink, customerLink, mapsLink, reportLink } from '@/lib/links';
import { cap, checkWater, describePool, waterSummary } from '@/lib/portal';
import { formatDay, formatMoney } from '@/lib/utils';
import { type Equipment, type PoolDetail, type Subscription } from '@/types/pools';
import { Link, router } from '@inertiajs/vue3';
import { CalendarDays, CalendarX, FileText, Pencil, Plus, Trash2, Waves, Wrench } from '@lucide/vue';
import { computed } from 'vue';

/** One of the pool's forms, which the page hosts in this same pane. */
export type PoolFormRequest =
    { kind: 'pool' | 'equipment' | 'targets' } | { kind: 'plan'; plan: Subscription | null } | { kind: 'service'; equipmentId: number };

/** A pool's page in the Pools pane. `gone` = it was removed. */
const props = defineProps<{ pool: PoolDetail; canManage: boolean }>();
const emit = defineEmits<{ open: [form: PoolFormRequest]; gone: [] }>();

const billing = useCustomerBilling();
const healthTone = { green: 'good', amber: 'warn', red: 'bad' } as const;
// The latest water test, checked against this pool's own targets.
const waterChecks = computed(() => (props.pool.latest_reading ? checkWater(props.pool.latest_reading, props.pool.targets) : []));

function remove() {
    if (!confirm('Remove this pool? Its service history is preserved.')) return;
    router.delete(`/pools/${props.pool.id}`, { preserveScroll: true, onSuccess: () => emit('gone') });
}

function toggleSub(sub: Subscription) {
    router.patch(
        `/subscriptions/${sub.id}`,
        {
            service_type_id: sub.service_type_id,
            assigned_agent_id: sub.agent_id,
            frequency: sub.frequency,
            preferred_day: sub.preferred_day,
            day_of_month: sub.day_of_month,
            starts_on: sub.starts_on,
            ends_on: sub.ends_on,
            status: sub.status === 'active' ? 'paused' : 'active',
        },
        { preserveScroll: true },
    );
}
function removeSub(sub: Subscription) {
    if (!confirm('Remove this service plan? (Only for plans added by mistake — a plan with completed visits must be ended instead.)')) return;
    router.delete(`/subscriptions/${sub.id}`, { preserveScroll: true });
}
function removeEquip(item: Equipment) {
    if (!confirm('Remove this equipment? Its service history is kept.')) return;
    router.delete(`/equipment/${item.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <PaneHeader
            :title="pool.name"
            :summary="describePool({ volume: pool.volume_gallons, type: pool.type, sanitizer: pool.sanitizer, features: pool.features })"
        >
            <template #media>
                <img v-if="pool.photo_url" :src="pool.photo_url" :alt="pool.name" class="size-16 shrink-0 rounded-2xl object-cover" />
                <IconTile v-else :icon="Waves" size="lg" />
            </template>
            <template v-if="pool.latest_reading?.health" #badge>
                <Chip :tone="healthTone[pool.latest_reading.health.color]">{{ pool.latest_reading.health.label }}</Chip>
            </template>
            <p v-if="pool.customer.id" class="mt-2 text-[15px]">
                <Link :href="customerLink(pool.customer.id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                    pool.customer.name
                }}</Link><template v-if="pool.location?.city">, {{ pool.location.city }}</template>
            </p>
            <p v-if="pool.report_cc" class="text-muted-foreground mt-1 text-sm">Reports also go to {{ pool.report_cc }}</p>
            <template v-if="canManage" #actions>
                <Button size="sm" variant="outline" @click="emit('open', { kind: 'pool' })"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                <Button size="sm" variant="outline" @click="emit('open', { kind: 'targets' })">Water targets</Button>
                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="remove"
                    ><Trash2 class="mr-1 size-3.5" /> Remove</Button
                >
            </template>
        </PaneHeader>

        <PaneSection :title="pool.latest_reading?.taken_on ? `Water on ${formatDay(pool.latest_reading.taken_on)}` : 'Water'">
            <template v-if="pool.latest_reading?.last_report_id" #action>
                <PaneAction :href="reportLink(pool.latest_reading.last_report_id)" :icon="FileText">Last report</PaneAction>
            </template>
            <template v-if="waterChecks.length">
                <p class="text-muted-foreground mb-3 text-sm">{{ waterSummary(waterChecks, false) }}</p>
                <div class="border-border rounded-2xl border px-4">
                    <WaterChecks :checks="waterChecks" :explain="false" />
                </div>
            </template>
            <p v-else class="text-muted-foreground text-sm">No water test yet.</p>
        </PaneSection>

        <PaneSection title="Service plans">
            <template v-if="canManage" #action>
                <PaneAction :icon="Plus" @click="emit('open', { kind: 'plan', plan: null })">Add a plan</PaneAction>
            </template>
            <PaneList v-if="pool.subscriptions.length">
                <PaneItem v-for="sub in pool.subscriptions" :key="sub.id" :title="sub.service">
                    <template #icon><IconTile :icon="CalendarDays" size="sm" /></template>
                    <template #sub>
                        {{ sub.schedule
                        }}<template v-if="sub.agent_id">
                            with
                            <Link v-if="sub.agent_is_tech" :href="agentLink(sub.agent_id)" class="text-primary underline-offset-4 hover:underline">{{
                                sub.agent
                            }}</Link><template v-else>{{ sub.agent }}</template></template
                        ><template v-if="billing"> · {{ formatMoney(sub.price) }} {{ priceUnit(sub.frequency) }}</template>
                    </template>
                    <p v-if="sub.hold_label && sub.status !== 'cancelled'" class="mt-0.5 font-medium text-amber-700 dark:text-amber-400">
                        {{ sub.hold_label }}
                    </p>
                    <div v-if="canManage" class="mt-1.5 flex gap-4">
                        <PaneAction @click="emit('open', { kind: 'plan', plan: sub })">Edit</PaneAction>
                        <PaneAction @click="toggleSub(sub)">
                            {{ sub.status === 'active' ? 'Pause' : sub.status === 'paused' ? 'Resume' : 'Restart' }}
                        </PaneAction>
                        <PaneAction danger @click="removeSub(sub)">Remove</PaneAction>
                    </div>
                    <template #trailing>
                        <Chip :tone="planTone(sub.status)">{{ PLAN_STATUS[sub.status] ?? sub.status }}</Chip>
                    </template>
                </PaneItem>
            </PaneList>
            <p v-else class="text-muted-foreground text-sm">No service plans yet.</p>
        </PaneSection>

        <!-- Visits nobody made: stops left unfinished after their day. -->
        <PaneSection v-if="pool.missed.count" title="Missed visits">
            <template v-if="pool.missed.count > pool.missed.recent.length" #action>
                <span class="text-muted-foreground text-sm">{{ pool.missed.count }} in all</span>
            </template>
            <PaneList>
                <PaneItem
                    v-for="m in pool.missed.recent"
                    :key="m.id"
                    :title="m.on ? formatDay(m.on) : 'Missed visit'"
                    :sub="m.service"
                    :href="m.on ? `/schedule?date=${m.on}` : null"
                >
                    <template #icon><IconTile :icon="CalendarX" size="sm" /></template>
                    <template #trailing><Chip tone="bad">Missed</Chip></template>
                </PaneItem>
            </PaneList>
        </PaneSection>

        <PaneSection v-if="pool.location" title="Getting there">
            <PaneFacts
                :facts="[
                    {
                        label: 'Address',
                        value: [pool.location.address, pool.location.city].filter(Boolean).join(', '),
                        href: mapsLink(pool.location.full_address, pool.location.coords),
                    },
                    { label: 'Gate code', value: pool.location.gate_code },
                ]"
            />
            <p v-if="pool.location.access_notes" class="text-muted-foreground mt-2 text-sm">{{ pool.location.access_notes }}</p>
        </PaneSection>

        <PaneSection title="Equipment">
            <template v-if="canManage" #action>
                <PaneAction :icon="Plus" @click="emit('open', { kind: 'equipment' })">Add equipment</PaneAction>
            </template>
            <PaneList v-if="pool.equipment_items.length">
                <PaneItem
                    v-for="item in pool.equipment_items"
                    :key="item.id"
                    :title="cap(equipmentLabel(item.type))"
                    :sub="[item.make, item.model].filter(Boolean).join(' ') || null"
                >
                    <template #icon><IconTile :icon="Wrench" size="sm" /></template>
                    <ul v-if="item.service_log.length" class="text-muted-foreground mt-1 space-y-0.5">
                        <li v-for="log in item.service_log" :key="log.id" class="flex justify-between gap-3">
                            <span class="min-w-0 truncate" :title="`${formatDay(log.on)}: ${log.description}`"
                                >{{ formatDay(log.on) }}: {{ log.description }}</span
                            >
                            <span v-if="log.cost > 0" class="shrink-0 tabular-nums">{{ formatMoney(log.cost) }}</span>
                        </li>
                    </ul>
                    <div v-if="canManage" class="mt-1.5 flex gap-4">
                        <PaneAction @click="emit('open', { kind: 'service', equipmentId: item.id })">Log service</PaneAction>
                        <PaneAction danger @click="removeEquip(item)">Remove</PaneAction>
                    </div>
                </PaneItem>
            </PaneList>
            <p v-else class="text-muted-foreground text-sm">No equipment tracked yet.</p>
        </PaneSection>
    </div>
</template>
