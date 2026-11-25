<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { saveRecord } from '@/composables/useCrudForm';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { POOL_TYPES } from '@/lib/labels';
import { type CustomerDetail } from '@/types/people';
import { useForm } from '@inertiajs/vue3';

/** Add a customer (with an optional first pool), or edit the one given. */
const props = defineProps<{ customer: CustomerDetail | null }>();
const emit = defineEmits<{ close: [] }>();

const billing = useCustomerBilling();
// The customer as they were when the form opened: picking another row doesn't retarget it.
const editing = props.customer;
const f = editing?.fields;
const form = useForm({
    first_name: f?.first_name ?? '',
    last_name: f?.last_name ?? '',
    email: f?.email ?? '',
    phone: f?.phone ?? '',
    address_line1: f?.address_line1 ?? '',
    city: f?.city ?? '',
    state: f?.state ?? '',
    zip: f?.zip ?? '',
    notes: f?.notes ?? '',
    bill_chemicals: f?.bill_chemicals ?? false,
    photo: null as File | null,
    pool_name: '',
    pool_type: 'inground',
    pool_volume: '',
    pool_sanitizer: 'chlorine',
});
const submit = () => saveRecord(form, '/customers', editing?.id ?? null, () => emit('close'));
</script>

<template>
    <form class="space-y-4 text-sm" @submit.prevent="submit">
        <PaneHeader
            :title="editing ? 'Edit customer' : 'New customer'"
            :summary="editing ? 'Update their contact details.' : 'Add a customer and, if you like, their first pool.'"
        />
        <ImageUpload
            :model-value="form.photo"
            :current="editing?.photo_url ?? null"
            :error="form.errors.photo"
            shape="circle"
            @update:model-value="(file) => (form.photo = file)"
        />
        <div class="grid grid-cols-2 gap-3">
            <FormField label="First name" for="first_name" :error="form.errors.first_name">
                <Input id="first_name" v-model="form.first_name" />
            </FormField>
            <FormField label="Last name" for="last_name"><Input id="last_name" v-model="form.last_name" /></FormField>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Email" for="email" :error="form.errors.email"><Input id="email" v-model="form.email" type="email" /></FormField>
            <FormField label="Phone" for="phone"><Input id="phone" v-model="form.phone" /></FormField>
        </div>
        <p v-if="!editing || editing.portal_status === 'none'" class="text-muted-foreground -mt-2 text-xs">
            Leave the email blank and they won’t get any emails from you: no visit reports, reminders or invoices.
        </p>
        <p v-else class="text-muted-foreground -mt-2 text-xs">
            They sign in with this email, so it can’t be blank. To stop their emails, switch them off under Emails on their page.
        </p>
        <FormField label="Address" for="address"><Input id="address" v-model="form.address_line1" /></FormField>
        <div class="grid grid-cols-3 gap-3">
            <FormField label="City" for="city"><Input id="city" v-model="form.city" /></FormField>
            <FormField label="State" for="state"><Input id="state" v-model="form.state" maxlength="2" /></FormField>
            <FormField label="ZIP" for="zip"><Input id="zip" v-model="form.zip" /></FormField>
        </div>
        <!-- Billed per visit: each visit's chemicals go on their bill at the chemicals' sell prices. -->
        <FormField v-if="billing" label="Chemicals" for="chemicals">
            <Select id="chemicals" v-model="form.bill_chemicals" class="w-64">
                <option :value="false">Included in their service</option>
                <option :value="true">Billed per visit</option>
            </Select>
        </FormField>
        <FormField label="Notes" for="notes"><Textarea id="notes" v-model="form.notes" rows="2" /></FormField>

        <fieldset v-if="!editing" class="border-border space-y-3 rounded-lg border p-3">
            <legend class="text-muted-foreground px-1 text-xs font-medium">First pool (optional)</legend>
            <FormField label="Pool name" for="pool_name">
                <Input id="pool_name" v-model="form.pool_name" placeholder="e.g. Backyard Pool" />
            </FormField>
            <div class="grid grid-cols-2 gap-3">
                <FormField label="Type" for="pool_type">
                    <Select id="pool_type" v-model="form.pool_type">
                        <option v-for="(label, type) in POOL_TYPES" :key="type" :value="type">{{ label }}</option>
                    </Select>
                </FormField>
                <FormField label="Volume (gal)" for="pool_volume">
                    <Input id="pool_volume" v-model="form.pool_volume" type="number" min="0" />
                </FormField>
            </div>
        </fieldset>

        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
            <Button type="submit" :disabled="form.processing">{{ editing ? 'Save' : 'Add customer' }}</Button>
        </div>
    </form>
</template>
