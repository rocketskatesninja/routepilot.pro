<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { EQUIPMENT_TYPES, equipmentLabel } from '@/lib/labels';
import { type PoolDetail } from '@/types/pools';
import { useForm } from '@inertiajs/vue3';

/**
 * A pool's three small forms: add a piece of equipment, log a repair on one
 * (`equipmentId`), or set the pool's own water targets.
 */
const props = defineProps<{ pool: PoolDetail; kind: 'equipment' | 'service' | 'targets'; equipmentId?: number }>();
const emit = defineEmits<{ close: [] }>();

const billing = useCustomerBilling();
const done = { preserveScroll: true, onSuccess: () => emit('close') };

const equipment = useForm({ pool_id: props.pool.id, type: 'pump', make: '', model: '', serial: '', installed_on: '', warranty_until: '', notes: '' });
const service = useForm({ serviced_on: '', description: '', cost: '', bill: false });

const chemParams = [
    { key: 'free_chlorine', label: 'Free chlorine' },
    { key: 'ph', label: 'pH' },
    { key: 'alkalinity', label: 'Alkalinity' },
    { key: 'calcium_hardness', label: 'Calcium' },
    { key: 'cyanuric_acid', label: 'CYA' },
    { key: 'salt', label: 'Salt' },
];
const text = (n?: number) => (n != null ? String(n) : '');
const targets = useForm({
    targets: Object.fromEntries(
        chemParams.map((p) => [p.key, { min: text(props.pool.targets[p.key]?.min), max: text(props.pool.targets[p.key]?.max) }]),
    ) as Record<string, { min: string; max: string }>,
});
</script>

<template>
    <form v-if="kind === 'equipment'" class="space-y-4 text-sm" @submit.prevent="equipment.post('/equipment', done)">
        <PaneHeader title="Add equipment" summary="Keep track of a pump, filter, heater, salt cell and so on." />
        <FormField label="Type" for="eq_type">
            <Select id="eq_type" v-model="equipment.type" class="capitalize">
                <option v-for="t in EQUIPMENT_TYPES" :key="t" :value="t">{{ equipmentLabel(t) }}</option>
            </Select>
        </FormField>
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Make" for="eq_make"><Input id="eq_make" v-model="equipment.make" /></FormField>
            <FormField label="Model" for="eq_model"><Input id="eq_model" v-model="equipment.model" /></FormField>
        </div>
        <FormField label="Serial" for="eq_serial"><Input id="eq_serial" v-model="equipment.serial" /></FormField>
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Installed" for="eq_inst"><Input id="eq_inst" v-model="equipment.installed_on" type="date" /></FormField>
            <FormField label="Warranty until" for="eq_warr"><Input id="eq_warr" v-model="equipment.warranty_until" type="date" /></FormField>
        </div>
        <FormField label="Notes" for="eq_notes"><Input id="eq_notes" v-model="equipment.notes" /></FormField>
        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
            <Button type="submit" :disabled="equipment.processing">Add</Button>
        </div>
    </form>

    <form v-else-if="kind === 'service'" class="space-y-4 text-sm" @submit.prevent="service.post(`/equipment/${equipmentId}/service`, done)">
        <PaneHeader
            title="Log service"
            :summary="billing ? 'Record a repair or maintenance, and bill it if you like.' : 'Record a repair or maintenance.'"
        />
        <div class="grid grid-cols-2 gap-3">
            <FormField label="Date" for="sv_on"><Input id="sv_on" v-model="service.serviced_on" type="date" /></FormField>
            <FormField label="Cost ($)" for="sv_cost"><Input id="sv_cost" v-model="service.cost" type="number" step="0.01" min="0" /></FormField>
        </div>
        <FormField label="Description" for="sv_desc" :error="service.errors.description">
            <Input id="sv_desc" v-model="service.description" />
        </FormField>
        <label v-if="billing" class="flex items-center gap-2"
            ><input v-model="service.bill" type="checkbox" /> Bill this repair to the customer</label
        >
        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
            <Button type="submit" :disabled="service.processing">Save</Button>
        </div>
    </form>

    <form v-else class="space-y-3 text-sm" @submit.prevent="targets.post(`/pools/${pool.id}/targets`, done)">
        <PaneHeader title="Water targets" summary="Set this pool’s own healthy ranges. Leave a box blank to use the usual range." />
        <div v-for="p in chemParams" :key="p.key" class="grid grid-cols-3 items-center gap-3">
            <Label class="text-muted-foreground">{{ p.label }}</Label>
            <Input v-model="targets.targets[p.key].min" type="number" step="0.1" placeholder="min" />
            <Input v-model="targets.targets[p.key].max" type="number" step="0.1" placeholder="max" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
            <Button type="submit" :disabled="targets.processing">Save targets</Button>
        </div>
    </form>
</template>
