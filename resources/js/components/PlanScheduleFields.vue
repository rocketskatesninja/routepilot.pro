<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SCHEDULES, WEEKDAYS } from '@/lib/labels';

/**
 * A plan's schedule: which agent does it, how often, which day, and when it starts and
 * ends. Shared by the pool's plan form and approving a customer's request, so
 * both ask the same way. Bind it to a useForm() that has these fields.
 */
interface ScheduleForm {
    assigned_agent_id: number | string;
    frequency: string;
    preferred_day: string;
    day_of_month: number | string;
    starts_on: string;
    ends_on: string;
    errors: Partial<Record<string, string>>;
}

const form = defineModel<ScheduleForm>({ required: true });
defineProps<{ agents: { id: number; name: string }[]; idPrefix: string }>();
</script>

<template>
    <div class="grid gap-1.5">
        <Label :for="`${idPrefix}_agent`">Agent</Label>
        <select
            :id="`${idPrefix}_agent`"
            v-model="form.assigned_agent_id"
            class="border-input bg-background h-9 w-64 max-w-full rounded-md border px-2 text-sm"
        >
            <option value="">Unassigned</option>
            <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
    </div>
    <div class="flex flex-wrap items-start gap-3">
        <div class="grid gap-1.5">
            <Label :for="`${idPrefix}_freq`">Schedule</Label>
            <select :id="`${idPrefix}_freq`" v-model="form.frequency" class="border-input bg-background h-9 w-40 rounded-md border px-2 text-sm">
                <option v-for="(label, key) in SCHEDULES" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>
        <div v-if="form.frequency === 'weekly' || form.frequency === 'biweekly'" class="grid gap-1.5">
            <Label :for="`${idPrefix}_day`">Day</Label>
            <select :id="`${idPrefix}_day`" v-model="form.preferred_day" class="border-input bg-background h-9 w-36 rounded-md border px-2 text-sm">
                <option value="" disabled>Pick a day…</option>
                <option v-for="d in WEEKDAYS" :key="d" :value="d">{{ d.charAt(0).toUpperCase() + d.slice(1) }}</option>
            </select>
            <p v-if="form.errors.preferred_day" class="text-xs text-red-600">{{ form.errors.preferred_day }}</p>
        </div>
        <div v-else-if="form.frequency === 'monthly'" class="grid gap-1.5">
            <Label :for="`${idPrefix}_dom`">Day of month</Label>
            <Input :id="`${idPrefix}_dom`" v-model="form.day_of_month" type="number" min="1" max="31" class="w-20" />
            <p v-if="form.errors.day_of_month" class="text-xs text-red-600">{{ form.errors.day_of_month }}</p>
            <p v-else class="text-muted-foreground text-xs">31 = last day of short months</p>
        </div>
    </div>
    <div class="flex flex-wrap items-start gap-3">
        <div class="grid gap-1.5">
            <Label :for="`${idPrefix}_start`">{{ form.frequency === 'one_time' ? 'Date' : 'Starts' }}</Label>
            <Input :id="`${idPrefix}_start`" v-model="form.starts_on" type="date" class="w-40" />
            <p v-if="form.errors.starts_on" class="text-xs text-red-600">{{ form.errors.starts_on }}</p>
        </div>
        <div v-if="form.frequency !== 'one_time'" class="grid gap-1.5">
            <Label :for="`${idPrefix}_end`">Ends <span class="text-muted-foreground font-normal">(optional)</span></Label>
            <Input :id="`${idPrefix}_end`" v-model="form.ends_on" type="date" class="w-40" />
            <p v-if="form.errors.ends_on" class="text-xs text-red-600">{{ form.errors.ends_on }}</p>
            <p v-else class="text-muted-foreground text-xs">For seasonal service</p>
        </div>
    </div>
</template>
