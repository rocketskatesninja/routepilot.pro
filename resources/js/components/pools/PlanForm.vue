<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PlanFields from '@/components/PlanFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { saveRecord } from '@/composables/useCrudForm';
import { PLAN_STATUS, startFromService } from '@/lib/labels';
import { localDay } from '@/lib/utils';
import { type Option, type ServiceType, type Subscription } from '@/types/pools';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/** Add a service plan to a pool, or edit the one given. */
const props = defineProps<{ poolId: number; plan: Subscription | null; serviceTypes: ServiceType[]; agents: Option[] }>();
const emit = defineEmits<{ close: [] }>();

const editing = props.plan;
const form = useForm({
    pool_id: props.poolId,
    service_type_id: (editing?.service_type_id ?? '') as number | string,
    price: (editing?.price ?? '') as number | string,
    assigned_agent_id: (editing?.agent_id ?? '') as number | string,
    frequency: editing?.frequency ?? 'weekly',
    preferred_day: editing?.preferred_day ?? '',
    day_of_month: (editing?.day_of_month ?? '') as number | string,
    starts_on: editing ? (editing.starts_on ?? '') : localDay(), // a new plan starts today
    ends_on: editing?.ends_on ?? '',
    status: editing?.status ?? 'active',
    hold_starts_at: editing?.hold_starts_at ?? '',
    hold_ends_at: editing?.hold_ends_at ?? '',
});
watch(
    () => form.service_type_id,
    (id) => !editing && startFromService(form, props.serviceTypes, id),
);

const submit = () => saveRecord(form, '/subscriptions', editing?.id ?? null, () => emit('close'));
</script>

<template>
    <form class="space-y-4 text-sm" @submit.prevent="submit">
        <PaneHeader
            :title="editing ? 'Edit service plan' : 'New service plan'"
            summary="Which service, how often, and who does it. Its visits go on the routes as soon as you save."
        />
        <FormField label="Service type" for="sub_service" :error="form.errors.service_type_id">
            <Select id="sub_service" v-model="form.service_type_id">
                <option value="">Select…</option>
                <option v-for="t in serviceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
            </Select>
        </FormField>
        <PlanFields v-model="form" :agents="agents" id-prefix="sub" />
        <template v-if="editing">
            <FormField label="Status" for="sub_status">
                <Select id="sub_status" v-model="form.status">
                    <option v-for="(label, key) in PLAN_STATUS" :key="key" :value="key">{{ label }}</option>
                </Select>
            </FormField>
            <div class="grid grid-cols-2 gap-3">
                <FormField label="Vacation hold from" for="hold_start"><Input id="hold_start" v-model="form.hold_starts_at" type="date" /></FormField>
                <FormField label="to" for="hold_end" :error="form.errors.hold_ends_at">
                    <Input id="hold_end" v-model="form.hold_ends_at" type="date" />
                </FormField>
            </div>
        </template>
        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
            <Button type="submit" :disabled="form.processing">{{ editing ? 'Save' : 'Add plan' }}</Button>
        </div>
    </form>
</template>
