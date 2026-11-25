<script setup lang="ts">
import Hint from '@/components/Hint.vue';
import NoteBubble from '@/components/NoteBubble.vue';
import PlanFields from '@/components/PlanFields.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { customerLink, poolLink, reportLink } from '@/lib/links';
import { formatDay } from '@/lib/utils';
import { firstWord, INBOX_TONE, type RequestDetail } from '@/types/inbox';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarCheck, Check, CirclePause, CircleStop, ClipboardList, type Inbox, MessageSquare, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';

/** A customer's request in the Requests pane, with the forms that answer it. The page keys it by request, so it starts fresh for each. */
const props = defineProps<{ request: RequestDetail; agents: { id: number; name: string }[] }>();
const emit = defineEmits<{ remove: [] }>();

const requestIcon: Record<string, typeof Inbox> = { service: ClipboardList, hold: CirclePause, cancel: CircleStop, other: MessageSquare };

// Answering: one form at a time.
const mode = ref<'none' | 'approve' | 'decline'>('none');
/** Close an open form; false when there was none (the page then closes the pane). */
const closeForm = () => mode.value !== 'none' && ((mode.value = 'none'), true);
defineExpose({ closeForm });

// Forms are built from functions so reset() always starts blank.
const approveForm = useForm(() => ({
    price: '' as number | string,
    frequency: 'weekly',
    preferred_day: '',
    day_of_month: '' as number | string,
    starts_on: '',
    ends_on: '',
    assigned_agent_id: '' as number | string,
    reply: '',
}));
const declineForm = useForm(() => ({ reply: '' }));

function openApprove() {
    const d = props.request.defaults;
    approveForm.reset();
    approveForm.clearErrors();
    if (d) {
        approveForm.frequency = d.frequency;
        approveForm.preferred_day = d.preferred_day;
        approveForm.day_of_month = d.day_of_month;
        approveForm.starts_on = d.starts_on;
        approveForm.price = d.price; // the service's price, to adjust for this pool
        approveForm.assigned_agent_id = d.assigned_agent_id ?? '';
    }
    mode.value = 'approve';
}

function openDecline() {
    declineForm.reset();
    declineForm.clearErrors();
    mode.value = 'decline';
}

const answered = { preserveScroll: true, onSuccess: () => (mode.value = 'none') };
const submitApprove = () => approveForm.post(`/requests/${props.request.id}/approve`, answered);
const submitDecline = () => declineForm.post(`/requests/${props.request.id}/decline`, answered);

// Approving a service the pool already has would add a second plan for it.
const duplicatePlan = computed(() => {
    const r = props.request;
    return r.for_service ? r.pool_plans.find((p) => p.status === 'active' && p.service_type_id === r.service?.id) : undefined;
});

// What "approve" does for this kind of request, in plain words.
const approveLabel = computed(() => {
    const r = props.request;
    if (r.for_service) return 'Approve & schedule';
    if (r.visit) return 'Reply'; // a message from a visit report
    return { hold: 'Set the pause', cancel: 'Stop service' }[r.type] ?? 'Mark done';
});
const approveExplainer = computed(() => {
    const r = props.request;
    if ((r.type === 'hold' || r.type === 'cancel') && !r.plan_service) {
        return 'That service has since been removed, so there is nothing to change — this just marks the request done.';
    }
    const service = `${r.plan_service} (${r.plan}) on ${r.pool}`;
    if (r.type === 'hold') {
        return `Pauses ${service} from ${formatDay(r.preferred_date)} through ${formatDay(r.ends_on)}; it comes back on its own after that. Their other services carry on.`;
    }
    if (r.type === 'cancel') {
        const when = r.preferred_date ? `after ${formatDay(r.preferred_date)}` : 'right away';
        return `Ends ${service} ${when}. Their other services carry on, and completed visits and billing history stay.`;
    }
    if (r.visit) return 'Emails your answer to them and marks the message done.';
    return 'Marks the request as handled. Add a note if you want to reply.';
});
</script>

<template>
    <div class="space-y-6">
        <PaneHeader :title="request.title" :summary="`Received ${formatDay(request.on)}.`">
            <template #media><IconTile :icon="requestIcon[request.type] ?? MessageSquare" size="lg" /></template>
            <template #actions>
                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="emit('remove')"
                    ><Trash2 class="mr-1 size-3.5" /> Delete</Button
                >
            </template>
            <template #badge>
                <Chip :tone="INBOX_TONE[request.status] ?? 'muted'">{{ request.status_label }}</Chip>
            </template>
            <p class="mt-2 text-[15px]">
                From
                <Link :href="customerLink(request.customer_id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                    request.customer
                }}</Link><template v-if="request.pool_id"
                    >, for
                    <Link :href="poolLink(request.pool_id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                        request.pool
                    }}</Link></template
                >
            </p>
        </PaneHeader>

        <PaneFacts
            :facts="[
                {
                    label: 'Would like to start',
                    value: request.type === 'service' ? (request.preferred_date ? formatDay(request.preferred_date) : 'As soon as possible') : null,
                },
                {
                    label: 'Service',
                    value: (request.type === 'hold' || request.type === 'cancel') && request.plan ? `${request.plan_service}, ${request.plan}` : null,
                },
                {
                    label: 'Pause',
                    value: request.type === 'hold' ? `${formatDay(request.preferred_date)} to ${formatDay(request.ends_on)}` : null,
                },
                {
                    label: 'Last service',
                    value: request.type === 'cancel' ? (request.preferred_date ? formatDay(request.preferred_date) : 'As soon as possible') : null,
                },
            ]"
        />

        <PaneList v-if="request.service">
            <PaneItem :title="request.service.name" :sub="request.service.description">
                <p v-if="!request.service.is_active" class="mt-1 text-amber-700 dark:text-amber-400">This service is no longer active.</p>
                <template #trailing
                    ><span class="text-primary text-xs font-semibold whitespace-nowrap">{{ request.service.offer }}</span></template
                >
            </PaneItem>
        </PaneList>

        <NoteBubble v-if="request.message" :author="`${firstWord(request.customer) ?? 'They'} wrote`">{{ request.message }}</NoteBubble>
        <p v-if="request.visit" class="text-sm">
            Sent from the report for the {{ formatDay(request.visit.on) }} visit.
            <Link :href="reportLink(request.visit.id)" class="text-primary font-medium underline-offset-4 hover:underline">See the report</Link>
        </p>

        <PaneSection v-if="request.pool_id" title="Already on this pool">
            <PaneList v-if="request.pool_plans.length">
                <PaneItem v-for="p in request.pool_plans" :key="p.id" :title="p.service" :sub="`${p.schedule}${p.agent ? ` with ${p.agent}` : ''}`">
                    <template v-if="p.status !== 'active'" #trailing>
                        <Chip>{{ p.status === 'cancelled' ? 'Ended' : 'Paused' }}</Chip>
                    </template>
                </PaneItem>
            </PaneList>
            <p v-else class="text-muted-foreground text-sm">No plans yet.</p>
        </PaneSection>

        <!-- answered -->
        <PaneSection v-if="request.status !== 'pending'" title="Your answer">
            <p class="text-sm">
                {{ request.status_label }}<template v-if="request.answered_on"> on {{ formatDay(request.answered_on) }}</template
                ><template v-if="request.plan && request.type === 'service'">: {{ request.plan }}</template
                >.
            </p>
            <NoteBubble v-if="request.reply" :author="request.status === 'declined' ? 'The reason you gave' : 'Your note'" class="mt-2">{{
                request.reply
            }}</NoteBubble>
        </PaneSection>

        <!-- answer it -->
        <template v-else>
            <p v-if="duplicatePlan" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-400">
                {{ request.pool }} already has {{ request.title }} ({{ duplicatePlan.schedule }}). Approving adds a second plan — decline instead if
                this is a mix-up.
            </p>
            <div v-if="mode === 'none'" class="flex flex-wrap gap-2">
                <Button size="sm" @click="openApprove"
                    ><component :is="request.visit ? MessageSquare : CalendarCheck" class="mr-1 size-3.5" /> {{ approveLabel }}</Button
                >
                <Hint text="Say no to this request, with a note the customer sees. It moves to Closed.">
                    <Button size="sm" variant="outline" @click="openDecline"><X class="mr-1 size-3.5" /> Decline</Button>
                </Hint>
            </div>

            <!-- approve: a service request gets its plan -->
            <form v-else-if="mode === 'approve'" class="border-border space-y-4 rounded-2xl border p-4 text-sm" @submit.prevent="submitApprove">
                <template v-if="request.for_service">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight">Schedule it</h3>
                        <p class="text-muted-foreground">Creates the plan on {{ request.pool }}. Its visits go on the route as soon as you save.</p>
                    </div>
                    <PlanFields
                        v-model="approveForm"
                        :agents="agents"
                        id-prefix="ap"
                        price-hint="What this pool pays, starting from the service’s price. Your answer tells them."
                    />
                </template>
                <p v-else class="text-muted-foreground">{{ approveExplainer }}</p>

                <div class="grid gap-1.5">
                    <Label for="ap_reply">Note to the customer <span class="text-muted-foreground font-normal">(optional)</span></Label>
                    <Textarea id="ap_reply" v-model="approveForm.reply" rows="2" />
                </div>
                <p class="text-muted-foreground text-xs">The customer is told in their portal and by email.</p>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="mode = 'none'">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="approveForm.processing"
                        ><Check class="mr-1 size-3.5" /> {{ request.for_service ? 'Schedule it' : approveLabel }}</Button
                    >
                </div>
            </form>

            <!-- decline -->
            <form v-else-if="mode === 'decline'" class="border-border space-y-3 rounded-2xl border p-4 text-sm" @submit.prevent="submitDecline">
                <div class="grid gap-1.5">
                    <Label for="dc_reply">Why? <span class="text-muted-foreground font-normal">The customer sees this.</span></Label>
                    <Textarea
                        id="dc_reply"
                        v-model="declineForm.reply"
                        rows="3"
                        placeholder="e.g. We're fully booked on Tuesdays — reply and we'll find another day."
                    />
                    <p v-if="declineForm.errors.reply" class="text-xs text-red-600">{{ declineForm.errors.reply }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="mode = 'none'">Cancel</Button>
                    <Button type="submit" size="sm" variant="destructive" :disabled="declineForm.processing">Decline request</Button>
                </div>
            </form>
        </template>
    </div>
</template>
