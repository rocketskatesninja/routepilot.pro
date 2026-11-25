<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import Hint from '@/components/Hint.vue';
import NoteBubble from '@/components/NoteBubble.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneItem from '@/components/pane/PaneItem.vue';
import PaneList from '@/components/pane/PaneList.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { POOL_TYPES } from '@/lib/labels';
import { customerLink, telLink } from '@/lib/links';
import { cap } from '@/lib/portal';
import { formatDay } from '@/lib/utils';
import { firstWord, INBOX_TONE, LEAD_SOURCES, LEAD_STATUSES, type LeadDetail } from '@/types/inbox';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Trash2, UserPlus } from '@lucide/vue';
import { ref } from 'vue';

/** A website lead in the Requests pane, with the form that makes them a customer. The page keys it by lead, so it starts fresh for each. */
const props = defineProps<{ lead: LeadDetail }>();
const emit = defineEmits<{ remove: [] }>();

const setStatus = (status: unknown) => router.patch(`/leads/${props.lead.id}`, { status: String(status) }, { preserveScroll: true });

const converting = ref(false);
/** Close the open form; false when there was none (the page then closes the pane). */
const closeForm = () => converting.value && ((converting.value = false), true);
defineExpose({ closeForm });

const convertForm = useForm(() => ({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address_line1: '',
    city: '',
    state: '',
    zip: '',
    pool_name: 'Pool',
    pool_type: 'inground',
    pool_volume: '' as number | string,
}));

function openConvert() {
    const c = props.lead.convert;
    convertForm.reset();
    convertForm.clearErrors();
    Object.assign(convertForm, { ...c, pool_volume: c.pool_volume ?? '' });
    converting.value = true;
}

const submitConvert = () => convertForm.post(`/leads/${props.lead.id}/convert`, { onSuccess: () => (converting.value = false) });
</script>

<template>
    <div class="space-y-6">
        <PaneHeader :title="lead.name" :summary="`${LEAD_SOURCES[lead.source] ?? lead.source}, ${formatDay(lead.on)}.`">
            <template #media><IconTile :icon="UserPlus" size="lg" /></template>
            <template #actions>
                <Button size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="emit('remove')"
                    ><Trash2 class="mr-1 size-3.5" /> Delete</Button
                >
            </template>
            <template #badge>
                <Chip :tone="INBOX_TONE[lead.status] ?? 'muted'">{{ LEAD_STATUSES[lead.status] ?? lead.status }}</Chip>
            </template>
            <p v-if="lead.customer" class="mt-2 text-[15px]">
                Now a customer:
                <Link :href="customerLink(lead.customer.id)" class="text-primary font-medium underline-offset-4 hover:underline">{{
                    lead.customer.name
                }}</Link>
            </p>
        </PaneHeader>

        <PaneFacts
            :facts="[
                { label: 'Email', value: lead.email, href: lead.email ? `mailto:${lead.email}` : null },
                { label: 'Phone', value: lead.phone, href: lead.phone ? telLink(lead.phone) : null },
                ...lead.details.map((d) => ({
                    label: d.label,
                    value: d.label === 'Preferred date' ? formatDay(d.value) : cap(d.value),
                })),
            ]"
        />

        <PaneSection v-if="lead.service" title="Picked on your website">
            <PaneList>
                <PaneItem :title="lead.service.name">
                    <template #trailing
                        ><span class="text-primary text-xs font-semibold whitespace-nowrap">{{ lead.service.offer }}</span></template
                    >
                </PaneItem>
            </PaneList>
        </PaneSection>

        <NoteBubble v-if="lead.message" :author="`${firstWord(lead.name) ?? 'They'} wrote`">{{ lead.message }}</NoteBubble>

        <template v-if="!lead.customer">
            <div v-if="!converting" class="flex flex-wrap items-center gap-2">
                <Hint text="Opens a customer form with this lead’s details already filled in. Nothing is saved until you add them.">
                    <Button size="sm" @click="openConvert"><UserPlus class="mr-1 size-3.5" /> Convert to customer</Button>
                </Hint>
                <Select :model-value="lead.status" class="h-8 w-36" aria-label="Lead status" @update:model-value="setStatus">
                    <option value="new">New</option>
                    <option value="contacted">Contacted</option>
                    <option value="archived">Archived</option>
                </Select>
            </div>

            <!-- convert: the add-customer form, pre-filled -->
            <form v-else class="border-border space-y-4 rounded-2xl border p-4 text-sm" @submit.prevent="submitConvert">
                <div>
                    <h3 class="text-lg font-semibold tracking-tight">New customer</h3>
                    <p class="text-muted-foreground">
                        Adds them and their pool.<template v-if="lead.service">
                            Their pick — {{ lead.service.name }} — then waits in Customer requests for you to approve &amp; schedule.</template
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <FormField label="First name" for="cv_first" :error="convertForm.errors.first_name"
                        ><Input id="cv_first" v-model="convertForm.first_name" class="w-44"
                    /></FormField>
                    <FormField label="Last name" for="cv_last"><Input id="cv_last" v-model="convertForm.last_name" class="w-44" /></FormField>
                </div>
                <div class="flex flex-wrap gap-3">
                    <FormField label="Email" for="cv_email" :error="convertForm.errors.email"
                        ><Input id="cv_email" v-model="convertForm.email" type="email" class="w-60"
                    /></FormField>
                    <FormField label="Phone" for="cv_phone"><Input id="cv_phone" v-model="convertForm.phone" class="w-40" /></FormField>
                </div>
                <FormField label="Street address" for="cv_addr"><Input id="cv_addr" v-model="convertForm.address_line1" /></FormField>
                <div class="flex flex-wrap gap-3">
                    <FormField label="City" for="cv_city"><Input id="cv_city" v-model="convertForm.city" class="w-40" /></FormField>
                    <FormField label="State" for="cv_state" :error="convertForm.errors.state"
                        ><Input id="cv_state" v-model="convertForm.state" maxlength="2" class="w-16 uppercase"
                    /></FormField>
                    <FormField label="ZIP" for="cv_zip"><Input id="cv_zip" v-model="convertForm.zip" class="w-24" /></FormField>
                </div>
                <div class="flex flex-wrap gap-3">
                    <FormField label="Pool name" for="cv_pool" :error="convertForm.errors.pool_name"
                        ><Input id="cv_pool" v-model="convertForm.pool_name" class="w-44"
                    /></FormField>
                    <FormField label="Type" for="cv_type"
                        ><Select id="cv_type" v-model="convertForm.pool_type" class="w-36">
                            <option v-for="(label, key) in POOL_TYPES" :key="key" :value="key">{{ label }}</option>
                        </Select></FormField
                    >
                    <FormField label="Gallons" for="cv_gal"
                        ><Input id="cv_gal" v-model="convertForm.pool_volume" type="number" min="0" class="w-28"
                    /></FormField>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="converting = false">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="convertForm.processing">Add customer</Button>
                </div>
            </form>
        </template>
    </div>
</template>
