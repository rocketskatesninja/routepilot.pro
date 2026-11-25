<script setup lang="ts">
import EmailChoices from '@/components/EmailChoices.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import FormField from '@/components/FormField.vue';
import Hint from '@/components/Hint.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { POOL_TYPES } from '@/lib/labels';
import { composeLink, poolLink, reportLink, telLink } from '@/lib/links';
import { andList, monthYear } from '@/lib/portal';
import { formatDay, formatMoney } from '@/lib/utils';
import { type CustomerDetail } from '@/types/people';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, RotateCcw, Trash2, UserX, Waves } from '@lucide/vue';
import { ref } from 'vue';

/** A customer's page in the People pane. `gone` = they left this list (archived, restored, deleted). */
const props = defineProps<{ customer: CustomerDetail; canManage: boolean }>();
const emit = defineEmits<{ edit: []; gone: [] }>();

const billing = useCustomerBilling();
const url = (path = '') => `/customers/${props.customer.id}${path}`;
const leave = { preserveScroll: true, onSuccess: () => emit('gone') };

/** "In-ground, 18,000 gal. Weekly Pool Service and Chemistry Check." */
const poolLine = (pool: CustomerDetail['pools'][number]) => {
    const size = [POOL_TYPES[pool.type] ?? pool.type, pool.volume ? `${pool.volume.toLocaleString('en-US')} gal` : ''].filter(Boolean).join(', ');
    return `${size}. ${pool.services.length ? `${andList(pool.services)}.` : 'No service yet.'}`;
};
/** "Weekly Pool Service at Beach House Pool with Ashley." */
const visitLine = (v: CustomerDetail['recent_visits'][number]) =>
    `${v.service ?? 'Service visit'}${v.pool ? ` at ${v.pool}` : ''}${v.agent ? ` with ${v.agent}` : ''}.`;

// Which optional emails the customer gets — the office can change them (say they asked on the phone).
const EMAIL_TYPES: Record<string, [string, string]> = {
    visit_reports: ['Visit reports', 'After each visit: what was done, the water test and photos.'],
    reminders: ['Visit reminders', 'The day before someone comes.'],
    news: ['News and offers', 'Emails you send from Email people.'],
};
const emailBusy = ref(false);
const setEmail = (type: string, on: boolean) =>
    router.patch(
        url('/emails'),
        { [type]: on },
        { preserveScroll: true, onStart: () => (emailBusy.value = true), onFinish: () => (emailBusy.value = false) },
    );

function archive() {
    const ok = confirm(
        'Archive this customer? Their pools and subscriptions are archived too (no more scheduling or metering). You can restore them from the Archived tab.',
    );
    if (ok) router.delete(url(), leave);
}
const restore = () => router.post(url('/restore'), {}, leave);
function anonymize() {
    const ok = confirm(
        'Remove this customer’s personal details? Their name, contact details, addresses, notes and work order text, photos, portal login and AI conversations are erased. ' +
            'Invoices, payments and visit records stay, under “Former customer”. This cannot be undone.',
    );
    if (ok) router.post(url('/anonymize'), {}, { preserveScroll: true });
}
function purge() {
    const s = props.customer.summary;
    const detail = s ? ` This removes ${s.pools} pool(s), ${s.visits} visit(s), ${s.invoices} invoice(s) and ${s.charges} charge(s).` : '';
    if (confirm(`Permanently delete this customer?${detail} This cannot be undone.`)) router.delete(url('/force'), leave);
}
// A first portal password, typed into a masked box (a browser prompt shows it in clear text).
const granting = ref(false);
const portal = useForm({ password: '' });
function closePortal() {
    granting.value = false;
    portal.reset();
    portal.clearErrors();
}
const grantPortal = () => portal.post(url('/portal'), { preserveScroll: true, onSuccess: closePortal });
const restorePortal = () => router.post(url('/portal/restore'), {}, { preserveScroll: true });
const exportData = () => window.open(url('/export'), '_blank');
</script>

<template>
    <div class="space-y-6">
        <PaneHeader :title="customer.name" :summary="customer.since ? `Customer since ${monthYear(customer.since)}.` : null">
            <template #media>
                <EntityAvatar :src="customer.photo_url" type="person" :name="customer.name" size="lg" shape="circle" />
            </template>
            <template #badge>
                <Chip v-if="customer.archived" tone="warn">Archived</Chip>
                <Chip v-else-if="customer.portal_status === 'active'" tone="good">Portal login</Chip>
            </template>
            <div class="mt-3 space-y-1 text-[15px]">
                <p v-if="customer.next_visit">
                    Next visit {{ formatDay(customer.next_visit.date)
                    }}<template v-if="customer.next_visit.agent"> with {{ customer.next_visit.agent }}</template
                    ><template v-if="customer.pools.length > 1"> at {{ customer.next_visit.pool }}</template
                    >.
                </p>
                <p v-if="billing">
                    <template v-if="customer.balance > 0">Owes {{ formatMoney(customer.balance) }}.</template>
                    <template v-else-if="customer.balance < 0">{{ formatMoney(-customer.balance) }} in credit.</template>
                    <template v-else>All paid up.</template>
                    <Link :href="`/balances?selected=${customer.id}`" class="text-primary ml-1 font-medium underline-offset-4 hover:underline">{{
                        customer.balance !== 0 ? 'See balance' : 'See payments'
                    }}</Link>
                </p>
            </div>
            <template v-if="canManage && !customer.archived" #actions>
                <Button size="sm" variant="outline" @click="emit('edit')"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                <Button v-if="customer.portal_status === 'none'" size="sm" variant="outline" :disabled="granting" @click="granting = true"
                    >Grant portal</Button
                >
                <Button v-else-if="customer.portal_status === 'revoked'" size="sm" variant="outline" @click="restorePortal"
                    ><RotateCcw class="mr-1 size-3.5" /> Restore access</Button
                >
                <Hint text="Downloads everything RoutePilot holds about this customer, for when they ask for their data.">
                    <Button size="sm" variant="outline" @click="exportData">Export</Button>
                </Hint>
                <Hint
                    text="Ends their service plans and moves them to the Archived tab. Their history and billing are kept, and you can restore them."
                >
                    <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="archive"
                        ><Trash2 class="mr-1 size-3.5" /> Archive</Button
                    >
                </Hint>
            </template>
        </PaneHeader>

        <form v-if="granting" class="bg-card space-y-3 rounded-2xl border p-4" @submit.prevent="grantPortal">
            <FormField
                label="First portal password"
                for="portal_pw"
                :error="portal.errors.password"
                hint="At least 10 characters. They sign in with their email and can change it afterwards."
            >
                <PasswordInput id="portal_pw" v-model="portal.password" autocomplete="new-password" class="max-w-xs" />
            </FormField>
            <div class="flex gap-2">
                <Button type="submit" size="sm" :disabled="portal.processing || !portal.password">Grant portal login</Button>
                <Button type="button" size="sm" variant="outline" @click="closePortal">Cancel</Button>
            </div>
        </form>

        <!-- archived: restore or permanently delete -->
        <div v-if="customer.archived && canManage" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm">
            <p class="font-medium">Archived{{ customer.archived_on ? ` on ${formatDay(customer.archived_on)}` : '' }}.</p>
            <p v-if="customer.anonymized_on" class="text-muted-foreground mt-0.5">
                Personal details removed on {{ formatDay(customer.anonymized_on) }}. Their billing records are kept.
            </p>
            <p v-if="customer.summary" class="text-muted-foreground mt-0.5">
                Still holds {{ customer.summary.pools }} pool(s), {{ customer.summary.visits }} visit(s), {{ customer.summary.invoices }} invoice(s)
                and {{ customer.summary.charges }} charge(s).
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <Button v-if="!customer.anonymized_on" size="sm" variant="outline" @click="restore"
                    ><RotateCcw class="mr-1 size-3.5" /> Restore</Button
                >
                <Button v-if="!customer.anonymized_on" size="sm" variant="outline" @click="anonymize"
                    ><UserX class="mr-1 size-3.5" /> Remove personal details</Button
                >
                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="purge"
                    ><Trash2 class="mr-1 size-3.5" /> Delete permanently</Button
                >
            </div>
        </div>

        <PaneSection title="Contact">
            <PaneFacts
                :facts="[
                    { label: 'Email', value: customer.email, href: composeLink(customer.id, 'customer') },
                    { label: 'Phone', value: customer.phone, href: customer.phone ? telLink(customer.phone) : null },
                    { label: 'Address', value: customer.address },
                ]"
            />
            <p v-if="!customer.email && !customer.phone && !customer.address" class="text-muted-foreground text-sm">No contact details yet.</p>
        </PaneSection>

        <PaneSection title="Emails">
            <EmailChoices :types="EMAIL_TYPES" :preferences="customer.emails" :busy="!canManage || emailBusy" who="they" @change="setEmail" />
        </PaneSection>

        <PaneSection :title="customer.pools.length === 1 ? 'Pool' : 'Pools'">
            <PaneList v-if="customer.pools.length">
                <PaneItem v-for="pool in customer.pools" :key="pool.id" :title="pool.name" :sub="poolLine(pool)" :href="poolLink(pool.id)">
                    <template #icon><IconTile :icon="Waves" size="sm" /></template>
                </PaneItem>
            </PaneList>
            <p v-else class="text-muted-foreground text-sm">No pools yet.</p>
        </PaneSection>

        <PaneSection v-if="customer.recent_visits.length" title="Recent visits">
            <PaneList>
                <PaneItem
                    v-for="visit in customer.recent_visits"
                    :key="visit.id"
                    :title="formatDay(visit.completed_on)"
                    :sub="visitLine(visit)"
                    :href="reportLink(visit.id)"
                />
            </PaneList>
        </PaneSection>
    </div>
</template>
